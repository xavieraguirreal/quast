<?php
require_once 'config.php';

// Clave de acceso
define('DOWNLOAD_KEY', 'estef170226lib');

$tenant = isset($_GET['t']) ? $_GET['t'] : 'aldp';
$codigo = isset($_GET['e']) ? $_GET['e'] : 'condiciones-detencion-2026';
$clave = isset($_POST['clave']) ? $_POST['clave'] : '';
$formato = isset($_POST['formato']) ? $_POST['formato'] : 'csv';
$error = '';
$authenticated = false;

// Verificar clave
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($clave === DOWNLOAD_KEY) {
        $authenticated = true;
    } else {
        $error = 'Clave incorrecta';
    }
}

$db = getDB();

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

// Si está autenticado y quiere descargar
if ($authenticated && isset($_POST['descargar'])) {
    // Obtener todas las preguntas
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

    // Obtener todas las respuestas
    $stmt = $db->prepare("
        SELECT r.id, r.uuid, r.created_at, r.completed_at
        FROM respuestas r
        WHERE r.encuesta_id = ? AND r.completada = 1
        ORDER BY r.created_at
    ");
    $stmt->execute([$encuesta['id']]);
    $respuestas = $stmt->fetchAll();

    // Obtener detalles de respuestas
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

    // Generar CSV
    $filename = 'encuesta_' . $codigo . '_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM para Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Encabezados
    $headers = ['ID', 'Fecha'];
    foreach ($preguntas as $p) {
        $headers[] = $p['codigo'];
    }
    fputcsv($output, $headers, ';');

    // Datos
    foreach ($respuestas as $resp) {
        $row = [
            $resp['uuid'],
            $resp['completed_at'] ?? $resp['created_at']
        ];

        foreach ($preguntas as $p) {
            $valor = '';
            if (isset($detalles[$resp['id']][$p['id']])) {
                $respDetalles = $detalles[$resp['id']][$p['id']];

                if ($p['tipo'] === 'textarea' || $p['tipo'] === 'text' || $p['tipo'] === 'number') {
                    // Texto libre
                    $valor = $respDetalles[0]['valor'] ?? '';
                } elseif ($p['tipo'] === 'radio' || $p['tipo'] === 'select') {
                    // Una opción
                    $opId = $respDetalles[0]['opcion_id'] ?? null;
                    $valor = $opId && isset($opciones[$opId]) ? $opciones[$opId] : '';
                    if ($respDetalles[0]['texto_adicional']) {
                        $valor .= ' - ' . $respDetalles[0]['texto_adicional'];
                    }
                } elseif ($p['tipo'] === 'checkbox') {
                    // Múltiples opciones
                    $valores = [];
                    foreach ($respDetalles as $rd) {
                        $opId = $rd['opcion_id'];
                        $texto = $opId && isset($opciones[$opId]) ? $opciones[$opId] : '';
                        if ($rd['texto_adicional']) {
                            $texto .= ' (' . $rd['texto_adicional'] . ')';
                        }
                        if ($texto) $valores[] = $texto;
                    }
                    $valor = implode(' | ', $valores);
                }
            }
            $row[] = $valor;
        }

        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
}

$baseUrl = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descargar Datos - <?= htmlspecialchars($encuesta['titulo']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
    <style>
        .download-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .download-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 32px;
            box-shadow: var(--shadow);
        }

        .download-card h1 {
            font-size: 1.3rem;
            margin-bottom: 8px;
            text-align: center;
        }

        .download-card p {
            color: var(--text-light);
            text-align: center;
            margin-bottom: 24px;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-group input[type="password"] {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
        }

        .error-msg {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }

        .success-box {
            background: #f0fdf4;
            border: 2px solid #22c55e;
            border-radius: var(--radius);
            padding: 24px;
            text-align: center;
        }

        .success-box h2 {
            color: #16a34a;
            font-size: 1.1rem;
            margin-bottom: 16px;
        }

        .btn-download {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.2s;
        }

        .btn-download:hover {
            transform: translateY(-2px);
        }

        .btn-download svg {
            width: 20px;
            height: 20px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-link:hover {
            color: var(--primary);
        }

        .lock-icon {
            width: 60px;
            height: 60px;
            background: var(--bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--primary);
        }

        .lock-icon svg {
            width: 28px;
            height: 28px;
        }
    </style>
</head>
<body>
    <div class="download-container">
        <div class="download-card">
            <?php if (!$authenticated): ?>
                <div class="lock-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0110 0v4"/>
                    </svg>
                </div>
                <h1>Descargar Datos</h1>
                <p>Ingresá la clave para acceder a la descarga de datos completos de la encuesta.</p>

                <?php if ($error): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="clave">Clave de acceso</label>
                        <input type="password" id="clave" name="clave" required autofocus>
                    </div>
                    <button type="submit" class="btn-primary btn-large">
                        Verificar
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </button>
                </form>
            <?php else: ?>
                <div class="success-box">
                    <h2>Acceso autorizado</h2>
                    <p style="margin-bottom: 20px; color: var(--text-light);">
                        Descargá los datos completos de la encuesta incluyendo todas las respuestas de texto.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="clave" value="<?= htmlspecialchars($clave) ?>">
                        <input type="hidden" name="descargar" value="1">
                        <button type="submit" class="btn-download">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Descargar CSV
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <a href="<?= $baseUrl ?>/resultados.php?t=<?= htmlspecialchars($tenant) ?>&e=<?= htmlspecialchars($codigo) ?>" class="back-link">
                ← Volver a resultados
            </a>
        </div>
    </div>
</body>
</html>
