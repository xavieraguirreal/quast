<?php
require_once 'config.php';

$tenant = getTenant('aldp');
$codigo = isset($_GET['e']) ? $_GET['e'] : '';

// Verificar sesion admin
session_start();
$authenticated = isset($_SESSION['quast_admin']) && $_SESSION['quast_admin']
    && (time() - ($_SESSION['quast_admin_time'] ?? 0)) < 3600;

if (!$authenticated) {
    header('Location: admin.php?t=' . urlencode($tenant) . '&e=' . urlencode($codigo));
    exit;
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

// Obtener preguntas
$stmt = $db->prepare("
    SELECT p.id, p.codigo, p.texto, p.tipo, s.titulo as seccion_titulo, s.numero as seccion_numero
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

// Obtener respuestas
$stmt = $db->prepare("
    SELECT r.id, r.uuid, r.created_at, r.completed_at
    FROM respuestas r
    WHERE r.encuesta_id = ? AND r.completada = 1
    ORDER BY r.completed_at ASC
");
$stmt->execute([$encuesta['id']]);
$respuestas = $stmt->fetchAll();

// Obtener detalles
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

// Buscar pregunta de nombre
$preguntaNombre = null;
foreach ($preguntas as $p) {
    if ($p['codigo'] === 'nombre_integrante') {
        $preguntaNombre = $p;
        break;
    }
}

// Generar HTML para el PDF
$html = '
<style>
    body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
    h1 { font-size: 18px; color: #4f46e5; margin-bottom: 4px; }
    h2 { font-size: 14px; color: #4f46e5; margin-top: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px; }
    .subtitle { font-size: 12px; color: #666; margin-bottom: 16px; }
    .meta { font-size: 10px; color: #888; margin-bottom: 20px; }
    .person { margin-bottom: 20px; page-break-inside: avoid; }
    .person-header { background: #f1f5f9; padding: 8px 12px; border-radius: 6px; margin-bottom: 8px; }
    .person-name { font-size: 13px; font-weight: bold; color: #1e293b; }
    .person-date { font-size: 10px; color: #64748b; }
    .answer { padding: 4px 0; border-bottom: 1px solid #f1f5f9; }
    .answer-q { font-size: 10px; color: #64748b; margin-bottom: 2px; }
    .answer-v { font-size: 11px; color: #1e293b; }
    .stats { background: #f8fafc; padding: 12px; border-radius: 6px; margin-bottom: 16px; text-align: center; }
    .stats-number { font-size: 24px; font-weight: bold; color: #4f46e5; }
    .stats-label { font-size: 10px; color: #64748b; }
</style>
';

$html .= '<h1>' . htmlspecialchars($encuesta['titulo']) . '</h1>';
$html .= '<div class="subtitle">' . htmlspecialchars($encuesta['tenant_nombre']) . '</div>';
$html .= '<div class="meta">Generado el ' . date('d/m/Y H:i') . ' | ' . count($respuestas) . ' respuesta(s)</div>';

$html .= '<div class="stats"><div class="stats-number">' . count($respuestas) . '</div><div class="stats-label">Respuestas totales</div></div>';

foreach ($respuestas as $resp) {
    $nombre = 'Anonimo #' . substr($resp['uuid'], 0, 8);

    if ($preguntaNombre && isset($detalles[$resp['id']][$preguntaNombre['id']])) {
        $opId = $detalles[$resp['id']][$preguntaNombre['id']][0]['opcion_id'] ?? null;
        if ($opId && isset($opciones[$opId])) {
            $nombre = $opciones[$opId];
        }
    }

    $fecha = date('d/m/Y H:i', strtotime($resp['completed_at'] ?? $resp['created_at']));

    $html .= '<div class="person">';
    $html .= '<div class="person-header">';
    $html .= '<span class="person-name">' . htmlspecialchars($nombre) . '</span>';
    $html .= ' &nbsp; <span class="person-date">' . $fecha . '</span>';
    $html .= '</div>';

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
            $html .= '<div class="answer">';
            $html .= '<div class="answer-q">' . htmlspecialchars($p['texto']) . '</div>';
            $html .= '<div class="answer-v">' . htmlspecialchars($valor) . '</div>';
            $html .= '</div>';
        }
    }

    $html .= '</div>';
}

// Cargar mPDF desde appVerumax (mismo hosting, carpeta hermana)
// Local: E:\appEncuestas -> E:\appVerumax
// Produccion: public_html/quast -> public_html (verumax root)
$vendorPath = __DIR__ . '/../appVerumax/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php'; // Produccion: quast es subcarpeta de verumax
}
if (!file_exists($vendorPath)) {
    die('Error: mPDF no disponible. Contactar al administrador.');
}
require_once $vendorPath;

$mpdf = new \Mpdf\Mpdf([
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 15,
    'margin_bottom' => 15,
    'format' => 'A4',
]);

$mpdf->SetTitle($encuesta['titulo'] . ' - Resultados');
$mpdf->SetAuthor('Quast - VERUMax');
$mpdf->WriteHTML($html);

$filename = 'encuesta_' . $codigo . '_' . date('Y-m-d') . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
