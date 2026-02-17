<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    $encuestaId = filter_input(INPUT_POST, 'encuesta_id', FILTER_VALIDATE_INT);

    if (!$encuestaId) {
        throw new Exception('ID de encuesta inválido');
    }

    // Verificar que la encuesta existe y está activa
    $stmt = $db->prepare("SELECT id FROM encuestas WHERE id = ? AND activa = 1");
    $stmt->execute([$encuestaId]);
    if (!$stmt->fetch()) {
        throw new Exception('Encuesta no encontrada o no activa');
    }

    // Crear registro de respuesta
    $uuid = generateUUID();
    $ipHash = hashIP($_SERVER['REMOTE_ADDR'] ?? '');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $db->prepare("
        INSERT INTO respuestas (encuesta_id, uuid, ip_hash, user_agent, completada, completed_at)
        VALUES (?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$encuestaId, $uuid, $ipHash, $userAgent]);
    $respuestaId = $db->lastInsertId();

    // Obtener todas las preguntas de la encuesta
    $stmt = $db->prepare("
        SELECT p.id, p.tipo
        FROM preguntas p
        JOIN secciones s ON p.seccion_id = s.id
        WHERE s.encuesta_id = ?
    ");
    $stmt->execute([$encuestaId]);
    $preguntas = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Preparar statement para insertar respuestas
    $stmtDetalle = $db->prepare("
        INSERT INTO respuestas_detalle (respuesta_id, pregunta_id, opcion_id, valor, texto_adicional)
        VALUES (?, ?, ?, ?, ?)
    ");

    // Procesar cada respuesta
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'pregunta_') !== 0) continue;

        // Extraer ID de pregunta
        preg_match('/pregunta_(\d+)/', $key, $matches);
        if (!isset($matches[1])) continue;

        $preguntaId = (int)$matches[1];

        if (!isset($preguntas[$preguntaId])) continue;

        $tipo = $preguntas[$preguntaId];

        if ($tipo === 'checkbox' && is_array($value)) {
            // Múltiples opciones
            foreach ($value as $opcionId) {
                $opcionId = (int)$opcionId;
                // Buscar texto adicional si existe
                $textoKey = "pregunta_{$preguntaId}_texto_{$opcionId}";
                $textoAdicional = isset($_POST[$textoKey]) ? trim($_POST[$textoKey]) : null;

                $stmtDetalle->execute([
                    $respuestaId,
                    $preguntaId,
                    $opcionId,
                    null,
                    $textoAdicional
                ]);
            }
        } elseif ($tipo === 'radio' || $tipo === 'select') {
            // Una opción (radio o select)
            $opcionId = (int)$value;
            $stmtDetalle->execute([
                $respuestaId,
                $preguntaId,
                $opcionId,
                null,
                null
            ]);
        } elseif (in_array($tipo, ['text', 'textarea', 'number'])) {
            // Texto libre
            $valor = trim($value);
            if ($valor !== '') {
                $stmtDetalle->execute([
                    $respuestaId,
                    $preguntaId,
                    null,
                    $valor,
                    null
                ]);
            }
        }
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'uuid' => $uuid
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
