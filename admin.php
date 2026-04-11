<?php
require_once 'config.php';

$tenant = getTenant('aldp');
$codigo = isset($_GET['e']) ? $_GET['e'] : '';
$clave = isset($_POST['clave']) ? $_POST['clave'] : '';
$error = '';
$authenticated = false;

// Verificar clave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (in_array($clave, DOWNLOAD_KEYS)) {
        $authenticated = true;
        // Guardar en sesión
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

// Procesar toggle de estado (activar/cerrar)
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_estado'])) {
    $nuevoEstado = $_POST['toggle_estado'] === 'cerrar' ? 0 : 1;
    $stmt = $db->prepare("UPDATE encuestas SET activa = ? WHERE tenant_slug = ? AND codigo = ?");
    $stmt->execute([$nuevoEstado, $tenant, $codigo]);
}

// Obtener encuesta
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

$respuestasData = [];
$totalRespuestas = 0;

if ($authenticated) {
    // Obtener preguntas
    $stmt = $db->prepare("
        SELECT p.id, p.codigo, p.texto, p.tipo
        FROM preguntas p
        JOIN secciones s ON p.seccion_id = s.id
        WHERE s.encuesta_id = ?
        ORDER BY s.orden, p.orden
    ");
    $stmt->execute([$encuesta['id']]);
    $preguntas = $stmt->fetchAll();

    // Obtener opciones
    $stmt = $db->prepare("
        SELECT o.id, o.pregunta_id, o.texto
        FROM opciones o
        JOIN preguntas p ON o.pregunta_id = p.id
        JOIN secciones s ON p.seccion_id = s.id
        WHERE s.encuesta_id = ?
    ");
    $stmt->execute([$encuesta['id']]);
    $opcionesRaw = $stmt->fetchAll();
    $opciones = [];
    foreach ($opcionesRaw as $o) {
        $opciones[$o['id']] = $o['texto'];
    }

    // Obtener respuestas con detalles
    $stmt = $db->prepare("
        SELECT r.id, r.uuid, r.created_at, r.completed_at, r.ip_hash
        FROM respuestas r
        WHERE r.encuesta_id = ? AND r.completada = 1
        ORDER BY r.completed_at DESC
    ");
    $stmt->execute([$encuesta['id']]);
    $respuestas = $stmt->fetchAll();
    $totalRespuestas = count($respuestas);

    // Obtener todos los detalles
    $stmt = $db->prepare("
        SELECT rd.respuesta_id, rd.pregunta_id, rd.opcion_id, rd.valor, rd.texto_adicional
        FROM respuestas_detalle rd
        JOIN respuestas r ON rd.respuesta_id = r.id
        WHERE r.encuesta_id = ? AND r.completada = 1
    ");
    $stmt->execute([$encuesta['id']]);
    $detallesRaw = $stmt->fetchAll();

    $detalles = [];
    foreach ($detallesRaw as $d) {
        if (!isset($detalles[$d['respuesta_id']])) {
            $detalles[$d['respuesta_id']] = [];
        }
        if (!isset($detalles[$d['respuesta_id']][$d['pregunta_id']])) {
            $detalles[$d['respuesta_id']][$d['pregunta_id']] = [];
        }
        $detalles[$d['respuesta_id']][$d['pregunta_id']][] = $d;
    }

    // Buscar pregunta de nombre (si existe)
    $preguntaNombre = null;
    foreach ($preguntas as $p) {
        if ($p['codigo'] === 'nombre_integrante') {
            $preguntaNombre = $p;
            break;
        }
    }

    // Armar datos por persona
    foreach ($respuestas as $resp) {
        $nombre = 'Anonimo #' . substr($resp['uuid'], 0, 8);

        // Buscar nombre si existe la pregunta
        if ($preguntaNombre && isset($detalles[$resp['id']][$preguntaNombre['id']])) {
            $opId = $detalles[$resp['id']][$preguntaNombre['id']][0]['opcion_id'] ?? null;
            if ($opId && isset($opciones[$opId])) {
                $nombre = $opciones[$opId];
            }
        }

        // Armar resumen de respuestas
        $resumen = [];
        foreach ($preguntas as $p) {
            if ($p['codigo'] === 'nombre_integrante') continue;
            if (!isset($detalles[$resp['id']][$p['id']])) continue;

            $respDetalles = $detalles[$resp['id']][$p['id']];
            $valor = '';

            if ($p['tipo'] === 'textarea' || $p['tipo'] === 'text' || $p['tipo'] === 'number') {
                $valor = $respDetalles[0]['valor'] ?? '';
            } elseif ($p['tipo'] === 'radio' || $p['tipo'] === 'select') {
                $opId = $respDetalles[0]['opcion_id'] ?? null;
                $valor = $opId && isset($opciones[$opId]) ? $opciones[$opId] : '';
                if (!empty($respDetalles[0]['texto_adicional'])) {
                    $valor .= ' - ' . $respDetalles[0]['texto_adicional'];
                }
            } elseif ($p['tipo'] === 'checkbox') {
                $valores = [];
                foreach ($respDetalles as $rd) {
                    $opId = $rd['opcion_id'];
                    $texto = $opId && isset($opciones[$opId]) ? $opciones[$opId] : '';
                    if (!empty($rd['texto_adicional'])) {
                        $texto .= ' (' . $rd['texto_adicional'] . ')';
                    }
                    if ($texto) $valores[] = $texto;
                }
                $valor = implode(', ', $valores);
            }

            if ($valor !== '') {
                $resumen[] = [
                    'pregunta' => $p['texto'],
                    'valor' => $valor
                ];
            }
        }

        $respuestasData[] = [
            'id' => $resp['id'],
            'nombre' => $nombre,
            'fecha' => $resp['completed_at'] ?? $resp['created_at'],
            'resumen' => $resumen
        ];
    }
}

$baseUrl = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/");
$isCustom = isCustomDomain();
$encuestaUrl = $isCustom
    ? $baseUrl . '/' . $codigo
    : $baseUrl . '/' . $tenant . '/' . $codigo;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin - <?= htmlspecialchars($encuesta['titulo']) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
    <style>
        .admin-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 60px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-top: 12px;
        }

        .admin-header h1 {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .admin-stats {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .admin-stat {
            background: var(--card);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            flex: 1;
            text-align: center;
        }

        .admin-stat .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .admin-stat .label {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 4px;
        }

        .admin-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .admin-actions a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .admin-actions a svg {
            width: 16px;
            height: 16px;
        }

        .btn-csv {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .btn-csv:hover {
            background: #dcfce7;
        }

        .btn-pdf {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-pdf:hover {
            background: #fee2e2;
        }

        .toggle-estado {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding: 16px 20px;
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .estado-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .estado-badge.activa {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .estado-badge.cerrada {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .btn-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-toggle svg {
            width: 14px;
            height: 14px;
        }

        .btn-cerrar {
            background: #fef2f2;
            color: #dc2626;
        }

        .btn-cerrar:hover {
            background: #fee2e2;
        }

        .btn-activar {
            background: #f0fdf4;
            color: #16a34a;
        }

        .btn-activar:hover {
            background: #dcfce7;
        }

        .btn-back {
            background: var(--bg);
            color: var(--text-light);
            border: 1px solid var(--border);
        }

        .btn-back:hover {
            color: var(--primary);
            border-color: var(--primary);
        }

        .response-card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 16px;
            overflow: hidden;
        }

        .response-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .response-header:hover {
            background: var(--bg);
        }

        .response-name {
            font-weight: 600;
            font-size: 1rem;
        }

        .response-date {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .response-toggle {
            width: 24px;
            height: 24px;
            color: var(--text-light);
            transition: transform 0.2s;
        }

        .response-header.open .response-toggle {
            transform: rotate(180deg);
        }

        .response-details {
            display: none;
            padding: 0 20px 20px;
            border-top: 1px solid var(--border);
        }

        .response-details.open {
            display: block;
        }

        .response-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .response-item:last-child {
            border-bottom: none;
        }

        .response-item .pregunta {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-bottom: 4px;
            font-weight: 500;
        }

        .response-item .valor {
            font-size: 0.9rem;
            color: var(--text);
            line-height: 1.5;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .login-container {
            max-width: 400px;
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
            padding: 32px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .login-card h1 {
            font-size: 1.2rem;
            margin-bottom: 8px;
        }

        .login-card p {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 24px;
        }

        .login-card input[type="password"] {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            margin-bottom: 16px;
            transition: border-color 0.2s;
        }

        .login-card input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
        }

        .error-msg {
            background: #fef2f2;
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.85rem;
        }

        .lock-icon {
            width: 50px;
            height: 50px;
            background: var(--bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: var(--primary);
        }

        .lock-icon svg {
            width: 24px;
            height: 24px;
        }

        @media (max-width: 480px) {
            .admin-stats {
                flex-direction: column;
            }
            .admin-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="quast-header">
        <div class="header-inner">
            <span class="header-brand">Quast Admin</span>
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
            <h1>Administrar Encuesta</h1>
            <p><?= htmlspecialchars($encuesta['titulo']) ?></p>

            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="password" name="clave" placeholder="Clave de acceso" required autofocus>
                <input type="hidden" name="login" value="1">
                <button type="submit" class="btn-primary btn-large">
                    Ingresar
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <div class="admin-container">
        <div class="admin-header">
            <h1><?= htmlspecialchars($encuesta['titulo']) ?></h1>
        </div>

        <div class="admin-stats">
            <div class="admin-stat">
                <div class="number"><?= $totalRespuestas ?></div>
                <div class="label">Respuestas</div>
            </div>
            <div class="admin-stat">
                <div class="number"><?= $totalRespuestas > 0 ? date('d/m', strtotime($respuestasData[0]['fecha'])) : '-' ?></div>
                <div class="label">Ultima respuesta</div>
            </div>
        </div>

        <div class="admin-actions">
            <a href="<?= $encuestaUrl ?>" class="btn-back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                Volver a encuesta
            </a>
            <a href="<?= $baseUrl ?>/descargar.php?t=<?= htmlspecialchars($tenant) ?>&e=<?= htmlspecialchars($codigo) ?>" class="btn-csv">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                CSV
            </a>
            <a href="<?= $baseUrl ?>/descargar_pdf.php?t=<?= htmlspecialchars($tenant) ?>&e=<?= htmlspecialchars($codigo) ?>" class="btn-pdf">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                PDF
            </a>
        </div>

        <!-- Toggle estado -->
        <div class="toggle-estado">
            <form method="POST" style="display:inline">
                <?php if ($encuesta['activa']): ?>
                    <span class="estado-badge activa">Activa</span>
                    <input type="hidden" name="toggle_estado" value="cerrar">
                    <button type="submit" class="btn-toggle btn-cerrar" onclick="return confirm('Cerrar la encuesta? No se podran enviar mas respuestas.')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        Cerrar encuesta
                    </button>
                <?php else: ?>
                    <span class="estado-badge cerrada">Cerrada</span>
                    <input type="hidden" name="toggle_estado" value="activar">
                    <button type="submit" class="btn-toggle btn-activar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        Activar encuesta
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($respuestasData) > 0): ?>
            <?php foreach ($respuestasData as $resp): ?>
            <div class="response-card">
                <div class="response-header" onclick="toggleResponse(this)">
                    <div>
                        <div class="response-name"><?= htmlspecialchars($resp['nombre']) ?></div>
                        <div class="response-date"><?= date('d/m/Y H:i', strtotime($resp['fecha'])) ?></div>
                    </div>
                    <svg class="response-toggle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </div>
                <div class="response-details">
                    <?php foreach ($resp['resumen'] as $item): ?>
                    <div class="response-item">
                        <div class="pregunta"><?= htmlspecialchars($item['pregunta']) ?></div>
                        <div class="valor"><?= htmlspecialchars($item['valor']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>Todavia no hay respuestas.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <footer class="quast-footer">
        <span>Quast &middot; Encuestas verificadas por <a href="https://verumax.com" target="_blank" rel="noopener">VERUMax</a></span>
    </footer>

    <script>
    function toggleResponse(header) {
        header.classList.toggle('open');
        const details = header.nextElementSibling;
        details.classList.toggle('open');
    }
    </script>
</body>
</html>
