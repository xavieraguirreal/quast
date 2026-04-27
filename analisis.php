<?php
require_once 'config.php';

$tenant = getTenant('sajur');
$codigo = isset($_GET['e']) ? $_GET['e'] : 'compromiso-2026';
$clave = isset($_POST['clave']) ? $_POST['clave'] : '';
$error = '';
$authenticated = false;

// Verificar clave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (in_array($clave, DOWNLOAD_KEYS)) {
        $authenticated = true;
        session_start();
        $_SESSION['quast_admin'] = true;
        $_SESSION['quast_admin_time'] = time();
    } else {
        $error = 'Clave incorrecta';
    }
} else {
    session_start();
    if (isset($_SESSION['quast_admin']) && $_SESSION['quast_admin']
        && (time() - ($_SESSION['quast_admin_time'] ?? 0)) < 3600) {
        $authenticated = true;
    }
}

$db = getDB();

// Encuesta
$stmt = $db->prepare("
    SELECT e.*, t.nombre as tenant_nombre
    FROM encuestas e
    JOIN tenants t ON e.tenant_slug = t.slug
    WHERE e.tenant_slug = ? AND e.codigo = ?
");
$stmt->execute([$tenant, $codigo]);
$encuesta = $stmt->fetch();

if (!$encuesta) {
    die("Encuesta no encontrada.");
}

$baseUrl = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/");

// =======================================================
// SI ESTA AUTENTICADO: COMPUTAR ANALISIS
// =======================================================

$data = null;

if ($authenticated) {

    // Preguntas
    $stmt = $db->prepare("
        SELECT p.id, p.codigo, p.texto, p.tipo, s.numero as seccion_numero, s.titulo as seccion_titulo, s.orden as seccion_orden, p.orden
        FROM preguntas p
        JOIN secciones s ON p.seccion_id = s.id
        WHERE s.encuesta_id = ?
        ORDER BY s.orden, p.orden
    ");
    $stmt->execute([$encuesta['id']]);
    $preguntas = $stmt->fetchAll();

    $preguntasByCodigo = [];
    foreach ($preguntas as $p) $preguntasByCodigo[$p['codigo']] = $p;

    // Opciones
    $stmt = $db->prepare("
        SELECT o.id, o.pregunta_id, o.valor, o.texto, o.orden
        FROM opciones o
        JOIN preguntas p ON o.pregunta_id = p.id
        JOIN secciones s ON p.seccion_id = s.id
        WHERE s.encuesta_id = ?
        ORDER BY o.pregunta_id, o.orden
    ");
    $stmt->execute([$encuesta['id']]);
    $opcionesRaw = $stmt->fetchAll();

    $opcionesById = [];
    $opcionesByPregunta = [];
    foreach ($opcionesRaw as $o) {
        $opcionesById[$o['id']] = $o;
        $opcionesByPregunta[$o['pregunta_id']][] = $o;
    }

    // Respuestas
    $stmt = $db->prepare("
        SELECT id, uuid, completed_at, created_at
        FROM respuestas
        WHERE encuesta_id = ? AND completada = 1
        ORDER BY completed_at
    ");
    $stmt->execute([$encuesta['id']]);
    $respuestas = $stmt->fetchAll();

    // Detalles
    $detalles = [];
    if (!empty($respuestas)) {
        $ids = array_column($respuestas, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("
            SELECT respuesta_id, pregunta_id, opcion_id, valor, texto_adicional
            FROM respuestas_detalle
            WHERE respuesta_id IN ($in)
        ");
        $stmt->execute($ids);
        $detallesRaw = $stmt->fetchAll();
        foreach ($detallesRaw as $d) {
            $detalles[$d['respuesta_id']][$d['pregunta_id']][] = $d;
        }
    }

    // Persona por respuesta (segun nombre_integrante)
    $preguntaNombreId = $preguntasByCodigo['nombre_integrante']['id'] ?? null;
    $personas = []; // [respuesta_id => nombre]
    foreach ($respuestas as $r) {
        $nombre = 'Anonimo';
        if ($preguntaNombreId && isset($detalles[$r['id']][$preguntaNombreId][0])) {
            $opId = $detalles[$r['id']][$preguntaNombreId][0]['opcion_id'];
            if ($opId && isset($opcionesById[$opId])) {
                $nombre = $opcionesById[$opId]['texto'];
            }
        }
        $personas[$r['id']] = $nombre;
    }

    // Helper para extraer respuestas por codigo de pregunta
    $getResp = function($respuestaId, $codigoPregunta) use ($preguntasByCodigo, $detalles, $opcionesById) {
        if (!isset($preguntasByCodigo[$codigoPregunta])) return [];
        $pid = $preguntasByCodigo[$codigoPregunta]['id'];
        $tipo = $preguntasByCodigo[$codigoPregunta]['tipo'];
        if (!isset($detalles[$respuestaId][$pid])) return [];
        $out = [];
        foreach ($detalles[$respuestaId][$pid] as $d) {
            if ($tipo === 'textarea' || $tipo === 'text' || $tipo === 'number') {
                if (!empty($d['valor'])) $out[] = $d['valor'];
            } else {
                if ($d['opcion_id'] && isset($opcionesById[$d['opcion_id']])) {
                    $texto = $opcionesById[$d['opcion_id']]['texto'];
                    if (!empty($d['texto_adicional'])) $texto .= ' (' . $d['texto_adicional'] . ')';
                    $out[] = $texto;
                }
            }
        }
        return $out;
    };

    // ===== AGREGADOS GENERICOS =====

    // Conteo por opcion (multi)
    $contarOpciones = function($codigoPregunta) use ($preguntasByCodigo, $opcionesByPregunta, $detalles, $respuestas) {
        if (!isset($preguntasByCodigo[$codigoPregunta])) return [];
        $pid = $preguntasByCodigo[$codigoPregunta]['id'];
        $opciones = $opcionesByPregunta[$pid] ?? [];
        $counts = [];
        foreach ($opciones as $op) $counts[$op['id']] = ['texto' => $op['texto'], 'count' => 0];
        foreach ($respuestas as $r) {
            if (!isset($detalles[$r['id']][$pid])) continue;
            foreach ($detalles[$r['id']][$pid] as $d) {
                if ($d['opcion_id'] && isset($counts[$d['opcion_id']])) {
                    $counts[$d['opcion_id']]['count']++;
                }
            }
        }
        return array_values($counts);
    };

    $totalRespuestas = count($respuestas);
    $totalEsperado = count($opcionesByPregunta[$preguntaNombreId] ?? []);
    $tasaRespuesta = $totalEsperado > 0 ? round(($totalRespuestas / $totalEsperado) * 100, 1) : 0;

    // Quienes respondieron y quienes no
    $respondieron = array_unique(array_values($personas));
    sort($respondieron);
    $todosIntegrantes = [];
    foreach ($opcionesByPregunta[$preguntaNombreId] ?? [] as $op) {
        $todosIntegrantes[] = $op['texto'];
    }
    $faltantes = array_values(array_diff($todosIntegrantes, $respondieron));

    // Disponibilidad promedio (escala 1-5)
    $disponibilidades = [];
    foreach ($respuestas as $r) {
        $vals = $getResp($r['id'], 'disponibilidad_escala');
        if (!empty($vals)) {
            // Extraer numero del texto "3 - Disponibilidad moderada"
            if (preg_match('/^(\d)/', $vals[0], $m)) {
                $disponibilidades[] = (int)$m[1];
            }
        }
    }
    $dispProm = count($disponibilidades) > 0 ? round(array_sum($disponibilidades) / count($disponibilidades), 2) : 0;

    // Tabla por persona (resumen)
    $tablaPersonas = [];
    foreach ($respuestas as $r) {
        $disp = $getResp($r['id'], 'disponibilidad_escala');
        $tipo = $getResp($r['id'], 'tipo_tareas');
        $tablaPersonas[] = [
            'nombre' => $personas[$r['id']],
            'fecha' => $r['completed_at'] ?? $r['created_at'],
            'disponibilidad' => $disp[0] ?? '-',
            'tipo_tareas' => $tipo[0] ?? '-',
        ];
    }

    // Lista de profesional_tareas con nombre
    $tareasPorPersona = [];
    foreach ($respuestas as $r) {
        $vals = $getResp($r['id'], 'profesional_tareas');
        if (!empty($vals)) {
            $tareasPorPersona[] = ['nombre' => $personas[$r['id']], 'texto' => $vals[0]];
        }
    }

    // Frenos
    $frenos = [];
    foreach ($respuestas as $r) {
        $vals = $getResp($r['id'], 'que_te_frena');
        if (!empty($vals)) {
            $frenos[] = ['nombre' => $personas[$r['id']], 'texto' => $vals[0]];
        }
    }

    // Pedido de ayuda (rol ideal)
    $rolesPedidos = [];
    foreach ($respuestas as $r) {
        $vals = $getResp($r['id'], 'pedir_ayuda');
        if (!empty($vals)) {
            $rolesPedidos[] = ['nombre' => $personas[$r['id']], 'texto' => $vals[0]];
        }
    }

    // Ideas / proyectos
    $ideas = [];
    foreach ($respuestas as $r) {
        $vals = $getResp($r['id'], 'ideas_proyectos_desc');
        if (!empty($vals)) {
            $ideas[] = ['nombre' => $personas[$r['id']], 'texto' => $vals[0]];
        }
    }

    // Conteos para charts
    $tiposAporte = $contarOpciones('tipos_aporte');
    $tiempoHoras = $contarOpciones('tiempo_horas');
    $tiempoFranja = $contarOpciones('tiempo_franja');
    $difusionCanales = $contarOpciones('difusion_canales');
    $contactosSectores = $contarOpciones('contactos_sectores');
    $expectativas = $contarOpciones('expectativas');
    $disponibilidadDist = $contarOpciones('disponibilidad_escala');
    $tipoTareasDist = $contarOpciones('tipo_tareas');

    $data = compact(
        'totalRespuestas', 'totalEsperado', 'tasaRespuesta', 'faltantes', 'dispProm',
        'tablaPersonas', 'tareasPorPersona', 'frenos', 'rolesPedidos', 'ideas',
        'tiposAporte', 'tiempoHoras', 'tiempoFranja', 'difusionCanales', 'contactosSectores',
        'expectativas', 'disponibilidadDist', 'tipoTareasDist'
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#6366f1">
    <title>Analisis - <?= htmlspecialchars($encuesta['titulo']) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
    <?php if ($authenticated): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    <style>
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }

        .analisis-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 60px;
        }

        /* Hero */
        .hero {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, #818cf8 100%);
            color: white;
            border-radius: var(--radius);
            padding: 40px 28px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px -10px rgba(99, 102, 241, 0.5);
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -10%;
            width: 250px;
            height: 250px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-eyebrow {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.85;
            margin-bottom: 8px;
            position: relative;
        }

        .hero h1 {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 8px;
            position: relative;
        }

        .hero p {
            opacity: 0.9;
            font-size: 0.95rem;
            position: relative;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
        }

        .stat-card .num {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-card .lbl {
            font-size: 0.8rem;
            color: var(--text-light);
            font-weight: 500;
        }

        .stat-card.success { border-left-color: var(--success); }
        .stat-card.success .num { color: var(--success); }
        .stat-card.warn { border-left-color: #f59e0b; }
        .stat-card.warn .num { color: #f59e0b; }
        .stat-card.info { border-left-color: #06b6d4; }
        .stat-card.info .num { color: #06b6d4; }

        /* Section */
        .section {
            background: var(--card);
            border-radius: var(--radius);
            padding: 28px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }

        .section h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section h2 .num-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .section .subtitle {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 20px;
        }

        .section h3 {
            font-size: 1rem;
            font-weight: 600;
            margin: 20px 0 12px;
        }

        /* Bar item (custom horizontal bars) */
        .bar-list { display: flex; flex-direction: column; gap: 10px; }

        .bar-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
        }

        .bar-track {
            grid-column: 1 / -1;
            height: 30px;
            background: var(--bg);
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
        }

        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 8px;
            transition: width 0.6s ease-out;
            display: flex;
            align-items: center;
        }

        .bar-label {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: calc(100% - 90px);
            z-index: 2;
        }

        .bar-value {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-light);
            background: var(--card);
            padding: 2px 8px;
            border-radius: 12px;
            z-index: 2;
        }

        /* Chart */
        .chart-wrap {
            position: relative;
            height: 280px;
            max-width: 100%;
        }

        .chart-wrap.donut { max-width: 320px; margin: 0 auto; }

        /* Persona table */
        .persona-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .persona-table th, .persona-table td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }

        .persona-table th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-light);
            font-weight: 600;
        }

        .persona-table tbody tr:hover { background: var(--bg); }

        .persona-table .name {
            font-weight: 600;
            color: var(--primary);
        }

        .badge-disp {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-disp.lvl-1, .badge-disp.lvl-2 { background: #fef2f2; color: #dc2626; }
        .badge-disp.lvl-3 { background: #fef3c7; color: #d97706; }
        .badge-disp.lvl-4, .badge-disp.lvl-5 { background: #dcfce7; color: #16a34a; }

        .table-scroll { overflow-x: auto; }

        /* Quote / texto libre */
        .quote-list { display: flex; flex-direction: column; gap: 12px; }

        .quote {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            border-left: 4px solid var(--primary);
            border-radius: 10px;
            padding: 14px 18px;
        }

        .quote .who {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .quote .what {
            font-size: 0.92rem;
            line-height: 1.55;
            color: var(--text);
        }

        /* Reco box */
        .reco-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
        }

        .reco-card {
            background: var(--bg);
            border-radius: 12px;
            padding: 18px;
            border-top: 4px solid var(--primary);
        }

        .reco-card.now { border-top-color: #16a34a; }
        .reco-card.design { border-top-color: #f59e0b; }
        .reco-card.strategic { border-top-color: #06b6d4; }
        .reco-card.risk { border-top-color: #dc2626; }

        .reco-card .reco-title {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .reco-card.now .reco-title { color: #16a34a; }
        .reco-card.design .reco-title { color: #d97706; }
        .reco-card.strategic .reco-title { color: #06b6d4; }
        .reco-card.risk .reco-title { color: #dc2626; }

        .reco-card ol, .reco-card ul {
            margin-left: 18px;
            font-size: 0.88rem;
            line-height: 1.55;
        }

        .reco-card li { margin-bottom: 8px; }

        /* Faltan */
        .faltan-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }

        .faltan-box .icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #d97706;
            flex-shrink: 0;
        }

        .faltan-box .icon svg { width: 22px; height: 22px; }

        .faltan-box .txt {
            font-size: 0.9rem;
            color: #78350f;
        }

        .faltan-box .txt strong { color: #92400e; }

        /* Login */
        .login-container {
            max-width: 440px;
            margin: 0 auto;
            padding: 20px;
            min-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 40px 32px;
            box-shadow: 0 10px 40px -10px rgba(99, 102, 241, 0.25);
            text-align: center;
            border-top: 4px solid var(--primary);
        }

        .login-card h1 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .login-card .sub {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 28px;
        }

        .login-card input[type="password"] {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            margin-bottom: 16px;
            transition: border-color 0.2s;
            font-family: inherit;
        }

        .login-card input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
        }

        .login-card button {
            width: 100%;
            padding: 14px 18px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
        }

        .login-card button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -4px rgba(99, 102, 241, 0.5);
        }

        .login-card button svg { width: 18px; height: 18px; }

        .error-msg {
            background: #fef2f2;
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.85rem;
        }

        .lock-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #eef2ff, #c7d2fe);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            color: var(--primary-dark);
        }

        .lock-icon svg { width: 28px; height: 28px; }

        /* Toolbar (top actions) */
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .toolbar a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            background: var(--card);
            color: var(--text-light);
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .toolbar a:hover {
            color: var(--primary);
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .toolbar a svg { width: 14px; height: 14px; }

        /* Print */
        @media print {
            .toolbar, .quast-header, .quast-footer { display: none; }
            .section { box-shadow: none; border: 1px solid var(--border); page-break-inside: avoid; }
            .hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { background: white; }
        }

        /* Responsive */
        @media (max-width: 640px) {
            .analisis-container { padding: 12px; }
            .hero { padding: 28px 20px; }
            .hero h1 { font-size: 1.4rem; }
            .section { padding: 20px 18px; }
            .section h2 { font-size: 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 14px; }
            .stat-card .num { font-size: 1.5rem; }
            .persona-table th, .persona-table td { padding: 10px 8px; font-size: 0.82rem; }
            .reco-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="quast-header">
        <div class="header-inner">
            <span class="header-brand">Quast Analisis</span>
            <span class="header-tenant"><?= htmlspecialchars($encuesta['tenant_nombre']) ?></span>
        </div>
    </header>

<?php if (!$authenticated): ?>

    <div class="login-container">
        <div class="login-card">
            <div class="lock-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
            </div>
            <h1>Analisis de la Encuesta</h1>
            <p class="sub"><?= htmlspecialchars($encuesta['titulo']) ?></p>

            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="password" name="clave" placeholder="Clave de acceso" required autofocus>
                <input type="hidden" name="login" value="1">
                <button type="submit">
                    Ver analisis
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

<?php else: ?>

    <div class="analisis-container">

        <!-- Hero -->
        <div class="hero">
            <div class="hero-eyebrow">Informe de Analisis</div>
            <h1><?= htmlspecialchars($encuesta['titulo']) ?></h1>
            <p>Generado el <?= date('d/m/Y H:i') ?> &middot; <?= htmlspecialchars($encuesta['tenant_nombre']) ?></p>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <a href="<?= $baseUrl ?>/admin.php?t=<?= htmlspecialchars($tenant) ?>&e=<?= htmlspecialchars($codigo) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                Admin
            </a>
            <a href="<?= $baseUrl ?>/resultados.php?t=<?= htmlspecialchars($tenant) ?>&e=<?= htmlspecialchars($codigo) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 12l4-4 4 4 5-5"/></svg>
                Resultados
            </a>
            <a href="<?= $baseUrl ?>/descargar.php?t=<?= htmlspecialchars($tenant) ?>&e=<?= htmlspecialchars($codigo) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                CSV
            </a>
            <a href="javascript:window.print()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir / PDF
            </a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="num"><?= $data['totalRespuestas'] ?></div>
                <div class="lbl">Respuestas</div>
            </div>
            <div class="stat-card success">
                <div class="num"><?= $data['tasaRespuesta'] ?>%</div>
                <div class="lbl">Tasa de respuesta</div>
            </div>
            <div class="stat-card info">
                <div class="num"><?= number_format($data['dispProm'], 2) ?></div>
                <div class="lbl">Disponibilidad prom. (1-5)</div>
            </div>
            <div class="stat-card warn">
                <div class="num"><?= count($data['faltantes']) ?></div>
                <div class="lbl">Sin responder</div>
            </div>
        </div>

        <?php if (!empty($data['faltantes'])): ?>
        <div class="faltan-box">
            <div class="icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div class="txt">
                <strong>Aun no respondieron:</strong> <?= htmlspecialchars(implode(', ', $data['faltantes'])) ?>. Convendria enviar un recordatorio para completar la muestra.
            </div>
        </div>
        <?php endif; ?>

        <!-- Resumen ejecutivo -->
        <div class="section">
            <h2><span class="num-badge">1</span> Resumen ejecutivo</h2>
            <p style="font-size: 0.95rem; line-height: 1.6; color: var(--text-light);">
                El equipo esta <strong style="color: var(--text);">comprometido pero saturado</strong>.
                La adhesion identitaria es alta y la motivacion principal es formativa,
                pero la disponibilidad real promedio es <strong style="color: var(--text);"><?= number_format($data['dispProm'], 2) ?>/5</strong>.
                La oferta de tiempo es modular y se prefiere modalidad mixta antes que compromiso fijo.
                Hay tension latente en la expectativa de ingresos economicos y dos vacios estructurales:
                nadie aporta recursos materiales y muy pocos planean traer nuevos integrantes.
            </p>
        </div>

        <!-- Quienes respondieron -->
        <div class="section">
            <h2><span class="num-badge">2</span> Quienes respondieron</h2>
            <p class="subtitle">Resumen por persona, ordenado por fecha de respuesta.</p>
            <div class="table-scroll">
                <table class="persona-table">
                    <thead>
                        <tr>
                            <th>Persona</th>
                            <th>Fecha</th>
                            <th>Disponibilidad</th>
                            <th>Modalidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['tablaPersonas'] as $p): ?>
                        <?php
                            $lvl = preg_match('/^(\d)/', $p['disponibilidad'], $m) ? $m[1] : '0';
                        ?>
                        <tr>
                            <td class="name"><?= htmlspecialchars($p['nombre']) ?></td>
                            <td><?= date('d/m', strtotime($p['fecha'])) ?></td>
                            <td><span class="badge-disp lvl-<?= $lvl ?>"><?= htmlspecialchars($p['disponibilidad']) ?></span></td>
                            <td><?= htmlspecialchars($p['tipo_tareas']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tipos de aporte -->
        <div class="section">
            <h2><span class="num-badge">3</span> Que pueden aportar</h2>
            <p class="subtitle">Multi-respuesta. Cada persona pudo elegir varias opciones.</p>
            <div class="bar-list">
                <?php
                usort($data['tiposAporte'], fn($a, $b) => $b['count'] <=> $a['count']);
                $maxAporte = max(array_map(fn($x) => $x['count'], $data['tiposAporte']));
                $maxAporte = $maxAporte > 0 ? $maxAporte : 1;
                ?>
                <?php foreach ($data['tiposAporte'] as $a): ?>
                    <?php $pct = $data['totalRespuestas'] > 0 ? round(($a['count'] / $data['totalRespuestas']) * 100) : 0; ?>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: <?= ($a['count'] / $maxAporte) * 100 ?>%;"></div>
                        <div class="bar-label"><?= htmlspecialchars($a['texto']) ?></div>
                        <div class="bar-value"><?= $a['count'] ?> &middot; <?= $pct ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Aporte profesional detallado -->
        <?php if (!empty($data['tareasPorPersona'])): ?>
        <div class="section">
            <h2><span class="num-badge">4</span> Detalle del aporte profesional</h2>
            <p class="subtitle">Tareas concretas que cada persona ofrece desde lo profesional.</p>
            <div class="quote-list">
                <?php foreach ($data['tareasPorPersona'] as $t): ?>
                <div class="quote">
                    <div class="who"><?= htmlspecialchars($t['nombre']) ?></div>
                    <div class="what"><?= nl2br(htmlspecialchars($t['texto'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tiempo y franjas -->
        <div class="section">
            <h2><span class="num-badge">5</span> Tiempo y franjas horarias</h2>
            <p class="subtitle">De quienes ofrecieron tiempo como aporte.</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; align-items: start;">
                <div>
                    <h3>Horas semanales</h3>
                    <div class="chart-wrap donut"><canvas id="chartHoras"></canvas></div>
                </div>
                <div>
                    <h3>Franja horaria</h3>
                    <div class="bar-list">
                        <?php
                        $maxF = max(array_map(fn($x) => $x['count'], $data['tiempoFranja']));
                        $maxF = $maxF > 0 ? $maxF : 1;
                        ?>
                        <?php foreach ($data['tiempoFranja'] as $f): ?>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: <?= ($f['count'] / $maxF) * 100 ?>%;"></div>
                            <div class="bar-label"><?= htmlspecialchars($f['texto']) ?></div>
                            <div class="bar-value"><?= $f['count'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sectores y difusion -->
        <div class="section">
            <h2><span class="num-badge">6</span> Sectores de contacto y canales</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; align-items: start;">
                <div>
                    <h3>Sectores donde tienen contactos</h3>
                    <div class="bar-list">
                        <?php
                        $maxS = max(array_map(fn($x) => $x['count'], $data['contactosSectores']));
                        $maxS = $maxS > 0 ? $maxS : 1;
                        ?>
                        <?php foreach ($data['contactosSectores'] as $s): ?>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: <?= ($s['count'] / $maxS) * 100 ?>%;"></div>
                            <div class="bar-label"><?= htmlspecialchars($s['texto']) ?></div>
                            <div class="bar-value"><?= $s['count'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <h3>Canales de difusion</h3>
                    <div class="bar-list">
                        <?php
                        $maxD = max(array_map(fn($x) => $x['count'], $data['difusionCanales']));
                        $maxD = $maxD > 0 ? $maxD : 1;
                        ?>
                        <?php foreach ($data['difusionCanales'] as $c): ?>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: <?= ($c['count'] / $maxD) * 100 ?>%;"></div>
                            <div class="bar-label"><?= htmlspecialchars($c['texto']) ?></div>
                            <div class="bar-value"><?= $c['count'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expectativas -->
        <div class="section">
            <h2><span class="num-badge">7</span> Expectativas: que esperan recibir</h2>
            <p class="subtitle">La motivacion dominante es identitaria-formativa, pero hay expectativa de ingresos.</p>
            <div class="bar-list">
                <?php
                usort($data['expectativas'], fn($a, $b) => $b['count'] <=> $a['count']);
                $maxE = max(array_map(fn($x) => $x['count'], $data['expectativas']));
                $maxE = $maxE > 0 ? $maxE : 1;
                ?>
                <?php foreach ($data['expectativas'] as $e): ?>
                    <?php $pct = $data['totalRespuestas'] > 0 ? round(($e['count'] / $data['totalRespuestas']) * 100) : 0; ?>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: <?= ($e['count'] / $maxE) * 100 ?>%; background: linear-gradient(90deg, #f59e0b, #fbbf24);"></div>
                        <div class="bar-label"><?= htmlspecialchars($e['texto']) ?></div>
                        <div class="bar-value"><?= $e['count'] ?> &middot; <?= $pct ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Disponibilidad -->
        <div class="section">
            <h2><span class="num-badge">8</span> Compromiso real (3 meses)</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; align-items: start;">
                <div>
                    <h3>Disponibilidad (escala 1 a 5)</h3>
                    <div class="chart-wrap"><canvas id="chartDisp"></canvas></div>
                </div>
                <div>
                    <h3>Modalidad preferida</h3>
                    <div class="chart-wrap donut"><canvas id="chartModalidad"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Frenos -->
        <?php if (!empty($data['frenos'])): ?>
        <div class="section">
            <h2><span class="num-badge">9</span> Frenos detectados</h2>
            <p class="subtitle">Lo que cada persona identifica como obstaculo para involucrarse mas.</p>
            <div class="quote-list">
                <?php foreach ($data['frenos'] as $f): ?>
                <div class="quote">
                    <div class="who"><?= htmlspecialchars($f['nombre']) ?></div>
                    <div class="what"><?= nl2br(htmlspecialchars($f['texto'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Roles solicitados -->
        <?php if (!empty($data['rolesPedidos'])): ?>
        <div class="section">
            <h2><span class="num-badge">10</span> Rol ideal: como les gustaria que SAJuR les pida ayuda</h2>
            <p class="subtitle">La pregunta mas accionable de la encuesta. Es el insumo directo para asignar tareas.</p>
            <div class="quote-list">
                <?php foreach ($data['rolesPedidos'] as $r): ?>
                <div class="quote" style="border-left-color: var(--success); background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                    <div class="who" style="color: #16a34a;"><?= htmlspecialchars($r['nombre']) ?></div>
                    <div class="what"><?= nl2br(htmlspecialchars($r['texto'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Ideas / proyectos -->
        <?php if (!empty($data['ideas'])): ?>
        <div class="section">
            <h2><span class="num-badge">11</span> Ideas y proyectos propuestos</h2>
            <p class="subtitle">Materia prima propia del equipo: hay base para varias lineas paralelas.</p>
            <div class="quote-list">
                <?php foreach ($data['ideas'] as $i): ?>
                <div class="quote" style="border-left-color: #06b6d4; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
                    <div class="who" style="color: #0891b2;"><?= htmlspecialchars($i['nombre']) ?></div>
                    <div class="what"><?= nl2br(htmlspecialchars($i['texto'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recomendaciones -->
        <div class="section">
            <h2><span class="num-badge">12</span> Recomendaciones operativas</h2>
            <div class="reco-grid">
                <div class="reco-card now">
                    <div class="reco-title">Acciones inmediatas (2 semanas)</div>
                    <ol>
                        <?php if (!empty($data['faltantes'])): ?>
                        <li>Recordatorio a <strong><?= htmlspecialchars(implode(', ', $data['faltantes'])) ?></strong> para completar la encuesta.</li>
                        <?php endif; ?>
                        <li>Explicitar el modelo economico 2026: que actividades remuneran, cuales son voluntarias y bajo que criterios.</li>
                        <li>Conversacion 1-a-1 con quienes muestran mayor disponibilidad para formalizar roles.</li>
                    </ol>
                </div>
                <div class="reco-card design">
                    <div class="reco-title">Diseno organizativo (1 mes)</div>
                    <ol>
                        <li>Estructurar SAJuR <strong>por proyectos</strong>, no por roles fijos. La modalidad mixta es la norma.</li>
                        <li>Crear fichas de convocatoria que matcheen cada proyecto con la oferta profesional declarada.</li>
                        <li>Resolver el gap de recursos materiales por fuera del equipo (convenios universidad / Estado / ONGs).</li>
                    </ol>
                </div>
                <div class="reco-card strategic">
                    <div class="reco-title">Lineas estrategicas (3 meses)</div>
                    <ul>
                        <li>Lanzar 2-3 proyectos pilotos combinando aportes existentes.</li>
                        <li>Activar canal de difusion propio: hay redes pero falta canal institucional consolidado.</li>
                        <li>Capitalizar contactos en universidad y Estado para convenios formales.</li>
                    </ul>
                </div>
                <div class="reco-card risk">
                    <div class="reco-title">Riesgos a monitorear</div>
                    <ul>
                        <li><strong>Saturacion del equipo:</strong> disponibilidad promedio baja + frenos por carga familiar/laboral.</li>
                        <li><strong>Expectativa economica no resuelta:</strong> sin claridad puede erosionar el sentido de pertenencia.</li>
                        <li><strong>Distancia geografica:</strong> integrantes en zona horaria distinta requieren reuniones asincronas.</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>

    <script>
    // Disponibilidad
    new Chart(document.getElementById('chartDisp'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($x) => substr($x['texto'], 0, 1), $data['disponibilidadDist'])) ?>,
            datasets: [{
                label: 'Personas',
                data: <?= json_encode(array_map(fn($x) => $x['count'], $data['disponibilidadDist'])) ?>,
                backgroundColor: ['#dc2626', '#ef4444', '#f59e0b', '#10b981', '#16a34a'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function(items) {
                            const labels = <?= json_encode(array_map(fn($x) => $x['texto'], $data['disponibilidadDist'])) ?>;
                            return labels[items[0].dataIndex];
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    // Modalidad
    new Chart(document.getElementById('chartModalidad'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($x) => $x['texto'], $data['tipoTareasDist'])) ?>,
            datasets: [{
                data: <?= json_encode(array_map(fn($x) => $x['count'], $data['tipoTareasDist'])) ?>,
                backgroundColor: ['#6366f1', '#8b5cf6', '#06b6d4'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12, usePointStyle: true } }
            }
        }
    });

    // Horas
    new Chart(document.getElementById('chartHoras'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($x) => $x['texto'], $data['tiempoHoras'])) ?>,
            datasets: [{
                data: <?= json_encode(array_map(fn($x) => $x['count'], $data['tiempoHoras'])) ?>,
                backgroundColor: ['#a5b4fc', '#6366f1', '#4f46e5', '#3730a3'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12, usePointStyle: true } }
            }
        }
    });
    </script>

<?php endif; ?>

    <footer class="quast-footer">
        <span>Quast &middot; Encuestas verificadas por <a href="https://verumax.com" target="_blank" rel="noopener">VERUMax</a></span>
    </footer>
</body>
</html>
