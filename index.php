<?php
require_once 'config.php';

// Obtener parámetros
$tenant = isset($_GET['t']) ? $_GET['t'] : 'aldp';
$codigo = isset($_GET['e']) ? $_GET['e'] : 'condiciones-detencion-2026';

$db = getDB();

// Obtener encuesta
$stmt = $db->prepare("
    SELECT e.*, t.nombre as tenant_nombre
    FROM encuestas e
    JOIN tenants t ON e.tenant_slug = t.slug
    WHERE e.tenant_slug = ? AND e.codigo = ? AND e.activa = 1
");
$stmt->execute([$tenant, $codigo]);
$encuesta = $stmt->fetch();

if (!$encuesta) {
    die("Encuesta no encontrada o no activa.");
}

// Obtener secciones con preguntas y opciones
$stmt = $db->prepare("
    SELECT s.*,
           p.id as pregunta_id, p.codigo as pregunta_codigo, p.texto as pregunta_texto,
           p.tipo as pregunta_tipo, p.requerida, p.orden as pregunta_orden, p.config as pregunta_config,
           o.id as opcion_id, o.valor as opcion_valor, o.texto as opcion_texto,
           o.orden as opcion_orden, o.permite_texto_adicional
    FROM secciones s
    LEFT JOIN preguntas p ON s.id = p.seccion_id
    LEFT JOIN opciones o ON p.id = o.pregunta_id
    WHERE s.encuesta_id = ?
    ORDER BY s.orden, p.orden, o.orden
");
$stmt->execute([$encuesta['id']]);
$rows = $stmt->fetchAll();

// Organizar datos
$secciones = [];
foreach ($rows as $row) {
    $secId = $row['id'];
    if (!isset($secciones[$secId])) {
        $secciones[$secId] = [
            'id' => $row['id'],
            'numero' => $row['numero'],
            'titulo' => $row['titulo'],
            'descripcion' => $row['descripcion'],
            'preguntas' => []
        ];
    }

    if ($row['pregunta_id']) {
        $pregId = $row['pregunta_id'];
        if (!isset($secciones[$secId]['preguntas'][$pregId])) {
            $secciones[$secId]['preguntas'][$pregId] = [
                'id' => $row['pregunta_id'],
                'codigo' => $row['pregunta_codigo'],
                'texto' => $row['pregunta_texto'],
                'tipo' => $row['pregunta_tipo'],
                'requerida' => $row['requerida'],
                'config' => json_decode($row['pregunta_config'], true) ?? [],
                'opciones' => []
            ];
        }

        if ($row['opcion_id']) {
            $secciones[$secId]['preguntas'][$pregId]['opciones'][] = [
                'id' => $row['opcion_id'],
                'valor' => $row['opcion_valor'],
                'texto' => $row['opcion_texto'],
                'permite_texto_adicional' => $row['permite_texto_adicional']
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($encuesta['titulo']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/") ?>/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Pantalla de inicio -->
        <div id="intro-screen" class="screen active">
            <div class="intro-content">
                <div class="logo-circle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h1><?= htmlspecialchars($encuesta['titulo']) ?></h1>
                <p class="subtitle"><?= htmlspecialchars($encuesta['descripcion']) ?></p>

                <div class="instructions-box">
                    <h3>Antes de comenzar:</h3>
                    <ul>
                        <?php foreach(preg_split('/\n\s*\n/', $encuesta['instrucciones']) as $instruccion): ?>
                            <?php if(trim($instruccion)): ?>
                                <li><?= htmlspecialchars(trim(preg_replace('/\s+/', ' ', $instruccion))) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <button type="button" class="btn-primary btn-large" onclick="startSurvey()">
                    Comenzar Encuesta
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Formulario de encuesta -->
        <form id="survey-form" class="screen" data-encuesta-id="<?= $encuesta['id'] ?>">
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
            <div class="progress-text">
                <span id="current-section">1</span> de <span id="total-sections"><?= count($secciones) ?></span>
            </div>

            <?php $secIndex = 0; foreach ($secciones as $seccion): ?>
            <div class="section" data-section="<?= $secIndex ?>" <?= $secIndex === 0 ? '' : 'style="display:none"' ?>>
                <div class="section-header">
                    <span class="section-number">Sección <?= $seccion['numero'] ?></span>
                    <h2><?= htmlspecialchars($seccion['titulo']) ?></h2>
                    <?php if ($seccion['descripcion']): ?>
                        <div class="section-description"><?= $seccion['descripcion'] ?></div>
                    <?php endif; ?>
                </div>

                <div class="questions">
                    <?php foreach ($seccion['preguntas'] as $pregunta): ?>
                    <div class="question" data-pregunta-id="<?= $pregunta['id'] ?>" data-tipo="<?= $pregunta['tipo'] ?>">
                        <label class="question-label">
                            <?= htmlspecialchars($pregunta['texto']) ?>
                            <?php if ($pregunta['requerida']): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>

                        <?php if ($pregunta['tipo'] === 'radio'): ?>
                            <div class="options-group">
                                <?php foreach ($pregunta['opciones'] as $opcion): ?>
                                <label class="option-card">
                                    <input type="radio"
                                           name="pregunta_<?= $pregunta['id'] ?>"
                                           value="<?= $opcion['id'] ?>"
                                           <?= $pregunta['requerida'] ? 'required' : '' ?>>
                                    <span class="option-indicator"></span>
                                    <span class="option-text"><?= htmlspecialchars($opcion['texto']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($pregunta['tipo'] === 'checkbox'): ?>
                            <div class="options-group">
                                <?php foreach ($pregunta['opciones'] as $opcion): ?>
                                <label class="option-card checkbox">
                                    <input type="checkbox"
                                           name="pregunta_<?= $pregunta['id'] ?>[]"
                                           value="<?= $opcion['id'] ?>"
                                           data-permite-texto="<?= $opcion['permite_texto_adicional'] ?>">
                                    <span class="option-indicator"></span>
                                    <span class="option-text"><?= htmlspecialchars($opcion['texto']) ?></span>
                                    <?php if ($opcion['permite_texto_adicional']): ?>
                                        <input type="text"
                                               class="texto-adicional"
                                               name="pregunta_<?= $pregunta['id'] ?>_texto_<?= $opcion['id'] ?>"
                                               placeholder="Especificá..."
                                               disabled>
                                    <?php endif; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif ($pregunta['tipo'] === 'number'): ?>
                            <input type="number"
                                   name="pregunta_<?= $pregunta['id'] ?>"
                                   class="input-field"
                                   min="<?= $pregunta['config']['min'] ?? '' ?>"
                                   max="<?= $pregunta['config']['max'] ?? '' ?>"
                                   placeholder="<?= htmlspecialchars($pregunta['config']['placeholder'] ?? '') ?>"
                                   <?= $pregunta['requerida'] ? 'required' : '' ?>>

                        <?php elseif ($pregunta['tipo'] === 'text'): ?>
                            <input type="text"
                                   name="pregunta_<?= $pregunta['id'] ?>"
                                   class="input-field"
                                   placeholder="<?= htmlspecialchars($pregunta['config']['placeholder'] ?? '') ?>"
                                   <?= $pregunta['requerida'] ? 'required' : '' ?>>

                        <?php elseif ($pregunta['tipo'] === 'textarea'): ?>
                            <textarea name="pregunta_<?= $pregunta['id'] ?>"
                                      class="input-field textarea"
                                      rows="<?= $pregunta['config']['rows'] ?? 4 ?>"
                                      placeholder="<?= htmlspecialchars($pregunta['config']['placeholder'] ?? '') ?>"
                                      <?= $pregunta['requerida'] ? 'required' : '' ?>></textarea>

                        <?php elseif ($pregunta['tipo'] === 'select'): ?>
                            <select name="pregunta_<?= $pregunta['id'] ?>"
                                    class="input-field select-field"
                                    <?= $pregunta['requerida'] ? 'required' : '' ?>>
                                <option value="">Seleccioná una opción</option>
                                <?php foreach ($pregunta['opciones'] as $opcion): ?>
                                    <option value="<?= $opcion['id'] ?>"><?= htmlspecialchars($opcion['texto']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="nav-buttons">
                    <?php if ($secIndex > 0): ?>
                    <button type="button" class="btn-secondary" onclick="prevSection()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                        </svg>
                        Anterior
                    </button>
                    <?php else: ?>
                    <div></div>
                    <?php endif; ?>

                    <?php if ($secIndex < count($secciones) - 1): ?>
                    <button type="button" class="btn-primary" onclick="nextSection()">
                        Siguiente
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </button>
                    <?php else: ?>
                    <button type="submit" class="btn-primary btn-submit">
                        Enviar Encuesta
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php $secIndex++; endforeach; ?>
        </form>

        <!-- Pantalla de agradecimiento -->
        <div id="thank-you-screen" class="screen">
            <div class="thank-you-content">
                <div class="success-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h1>¡Gracias!</h1>
                <p>Tu respuesta fue enviada correctamente.</p>
                <p class="small">Tu participación es muy valiosa para mejorar las condiciones y defender los derechos de todas.</p>

                <div class="thank-you-buttons">
                    <button type="button" class="btn-secondary" onclick="location.reload()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Completar otra vez
                    </button>
                    <a href="<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/") ?>/resultados.php?t=<?= htmlspecialchars($tenant) ?>&e=<?= htmlspecialchars($codigo) ?>" class="btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Ver resultados
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/") ?>';
    </script>
    <script src="<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/") ?>/js/app.js"></script>
</body>
</html>
