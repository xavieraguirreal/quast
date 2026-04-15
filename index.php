<?php
require_once 'config.php';

// Obtener parámetros
$tenant = getTenant('aldp');
$codigo = isset($_GET['e']) ? $_GET['e'] : 'condiciones-detencion-2026';

$db = getDB();

// Obtener encuesta (sin filtrar por activa para distinguir cerrada vs inexistente)
$stmt = $db->prepare("
    SELECT e.*, t.nombre as tenant_nombre
    FROM encuestas e
    JOIN tenants t ON e.tenant_slug = t.slug
    WHERE e.tenant_slug = ? AND e.codigo = ?
");
$stmt->execute([$tenant, $codigo]);
$encuesta = $stmt->fetch();

if (!$encuesta || !$encuesta['activa']) {
    $baseUrl = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/");
    $cerrada = $encuesta && !$encuesta['activa'];
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#6366f1">
        <title><?= $cerrada ? htmlspecialchars($encuesta['titulo']) . ' — Cerrada' : 'Encuesta no encontrada' ?></title>
        <link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/favicon.svg">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
    </head>
    <body data-tenant="<?= htmlspecialchars($tenant) ?>">
        <header class="quast-header">
            <div class="header-inner">
                <span class="header-brand">Quast</span>
                <?php if ($cerrada): ?>
                <span class="header-tenant"><?= htmlspecialchars($encuesta['tenant_nombre']) ?></span>
                <?php endif; ?>
            </div>
        </header>

        <div class="container">
            <div class="screen active" style="justify-content:center; align-items:center; padding:24px; text-align:center;">
                <div style="max-width:440px;">
                    <div style="width:80px; height:80px; border-radius:50%; margin:0 auto 24px; display:flex; align-items:center; justify-content:center;
                        background:<?= $cerrada ? 'linear-gradient(135deg, #f59e0b, #d97706)' : 'linear-gradient(135deg, #94a3b8, #64748b)' ?>;">
                        <?php if ($cerrada): ?>
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                        <?php else: ?>
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                        </svg>
                        <?php endif; ?>
                    </div>

                    <?php if ($cerrada): ?>
                    <h1 style="font-size:1.5rem; font-weight:700; margin-bottom:12px; color:var(--text);">Encuesta cerrada</h1>
                    <p style="color:var(--text-light); font-size:1rem; margin-bottom:8px;">
                        <strong><?= htmlspecialchars($encuesta['titulo']) ?></strong>
                    </p>
                    <p style="color:var(--text-light); font-size:0.9rem; margin-bottom:8px;">
                        Esta encuesta ya no está recibiendo respuestas.
                    </p>
                    <?php if ($encuesta['updated_at']): ?>
                    <p style="color:var(--text-light); font-size:0.85rem; margin-bottom:24px;">
                        Cerrada el <?= date('d/m/Y', strtotime($encuesta['updated_at'])) ?>
                    </p>
                    <?php endif; ?>
                    <p style="color:var(--text-light); font-size:0.85rem;">
                        Si tenés consultas, contactá a la organización responsable.
                    </p>
                    <?php else: ?>
                    <h1 style="font-size:1.5rem; font-weight:700; margin-bottom:12px; color:var(--text);">Encuesta no encontrada</h1>
                    <p style="color:var(--text-light); font-size:0.9rem; margin-bottom:24px;">
                        La encuesta que buscás no existe o la dirección es incorrecta.
                    </p>
                    <p style="color:var(--text-light); font-size:0.85rem;">
                        Verificá el enlace e intentá nuevamente.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <footer class="quast-footer">
            <span>Quast &middot; Encuestas verificadas por <a href="https://verumax.com" target="_blank" rel="noopener">VERUMax</a></span>
        </footer>
    </body>
    </html>
    <?php
    exit;
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
<?php $baseUrl = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="<?= htmlspecialchars($encuesta['descripcion']) ?>">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#6366f1">
    <meta property="og:title" content="<?= htmlspecialchars($encuesta['titulo']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($encuesta['descripcion']) ?>">
    <meta property="og:type" content="website">
    <title><?= htmlspecialchars($encuesta['titulo']) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
</head>
<body data-tenant="<?= htmlspecialchars($tenant) ?>">
    <!-- Header -->
    <header class="quast-header">
        <div class="header-inner">
            <span class="header-brand">Quast</span>
            <div class="header-right">
                <span class="header-tenant"><?= htmlspecialchars($encuesta['tenant_nombre']) ?></span>
                <a href="<?= $baseUrl ?>/admin.php?t=<?= htmlspecialchars($tenant) ?>&e=<?= htmlspecialchars($codigo) ?>" class="header-download" title="Administrar">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                    </svg>
                </a>
            </div>
        </div>
    </header>

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
                    <?php
                        $dependsOn = $pregunta['config']['depends_on'] ?? null;
                        $dependsAttr = $dependsOn ? ' data-depends-on="' . htmlspecialchars($dependsOn) . '"' : '';
                        $dependsStyle = $dependsOn ? ' style="display:none"' : '';
                    ?>
                    <div class="question" data-pregunta-id="<?= $pregunta['id'] ?>" data-codigo="<?= htmlspecialchars($pregunta['codigo']) ?>" data-tipo="<?= $pregunta['tipo'] ?>"<?= $dependsAttr ?><?= $dependsStyle ?>>
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
                                           data-opcion-valor="<?= htmlspecialchars($opcion['valor']) ?>"
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
                                           data-opcion-valor="<?= htmlspecialchars($opcion['valor']) ?>"
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
                    <?php if ($tenant !== 'sajur'): ?>
                    <a href="<?= $baseUrl ?>/resultados.php?t=<?= htmlspecialchars($tenant) ?>&e=<?= htmlspecialchars($codigo) ?>" class="btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Ver resultados
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="quast-footer">
        <span>Quast &middot; Encuestas verificadas por <a href="https://verumax.com" target="_blank" rel="noopener">VERUMax</a></span>
    </footer>

    <script>
        const BASE_URL = '<?= $baseUrl ?>';
    </script>
    <script src="<?= $baseUrl ?>/js/app.js"></script>
</body>
</html>
