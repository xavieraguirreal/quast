<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';

$encuestaId = filter_input(INPUT_GET, 'encuesta_id', FILTER_VALIDATE_INT);
$opcionId = filter_input(INPUT_GET, 'opcion_id', FILTER_VALIDATE_INT);

if (!$encuestaId || !$opcionId) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    $db = getDB();

    // Buscar si existe una respuesta donde la opcion seleccionada sea la del nombre
    $stmt = $db->prepare("
        SELECT r.id, r.uuid, r.created_at
        FROM respuestas r
        JOIN respuestas_detalle rd ON r.id = rd.respuesta_id
        WHERE r.encuesta_id = ? AND rd.opcion_id = ? AND r.completada = 1
        ORDER BY r.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$encuestaId, $opcionId]);
    $respuesta = $stmt->fetch();

    if (!$respuesta) {
        echo json_encode(['success' => true, 'exists' => false]);
        exit;
    }

    // Cargar todas las respuestas detalle
    $stmt = $db->prepare("
        SELECT rd.pregunta_id, rd.opcion_id, rd.valor, rd.texto_adicional
        FROM respuestas_detalle rd
        WHERE rd.respuesta_id = ?
    ");
    $stmt->execute([$respuesta['id']]);
    $detalles = $stmt->fetchAll();

    // Organizar por pregunta
    $respuestasPorPregunta = [];
    foreach ($detalles as $d) {
        $pid = $d['pregunta_id'];
        if (!isset($respuestasPorPregunta[$pid])) {
            $respuestasPorPregunta[$pid] = [
                'opciones' => [],
                'valor' => null,
                'textos_adicionales' => []
            ];
        }
        if ($d['opcion_id']) {
            $respuestasPorPregunta[$pid]['opciones'][] = (int)$d['opcion_id'];
            if ($d['texto_adicional']) {
                $respuestasPorPregunta[$pid]['textos_adicionales'][(int)$d['opcion_id']] = $d['texto_adicional'];
            }
        }
        if ($d['valor'] !== null) {
            $respuestasPorPregunta[$pid]['valor'] = $d['valor'];
        }
    }

    echo json_encode([
        'success' => true,
        'exists' => true,
        'respuesta_id' => $respuesta['id'],
        'fecha' => $respuesta['created_at'],
        'respuestas' => $respuestasPorPregunta
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
