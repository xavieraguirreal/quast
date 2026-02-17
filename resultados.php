<?php
require_once 'config.php';

$tenant = isset($_GET['t']) ? $_GET['t'] : 'aldp';
$codigo = isset($_GET['e']) ? $_GET['e'] : 'condiciones-detencion-2026';

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

// Total de respuestas
$stmt = $db->prepare("SELECT COUNT(*) FROM respuestas WHERE encuesta_id = ? AND completada = 1");
$stmt->execute([$encuesta['id']]);
$totalRespuestas = $stmt->fetchColumn();

// Obtener todas las preguntas con sus opciones
$stmt = $db->prepare("
    SELECT s.id as seccion_id, s.numero as seccion_numero, s.titulo as seccion_titulo,
           p.id as pregunta_id, p.codigo, p.texto as pregunta_texto, p.tipo,
           o.id as opcion_id, o.valor as opcion_valor, o.texto as opcion_texto
    FROM secciones s
    JOIN preguntas p ON s.id = p.seccion_id
    LEFT JOIN opciones o ON p.id = o.pregunta_id
    WHERE s.encuesta_id = ?
    ORDER BY s.orden, p.orden, o.orden
");
$stmt->execute([$encuesta['id']]);
$rows = $stmt->fetchAll();

// Organizar preguntas
$preguntas = [];
foreach ($rows as $row) {
    $pid = $row['pregunta_id'];
    if (!isset($preguntas[$pid])) {
        $preguntas[$pid] = [
            'id' => $pid,
            'codigo' => $row['codigo'],
            'texto' => $row['pregunta_texto'],
            'tipo' => $row['tipo'],
            'seccion_numero' => $row['seccion_numero'],
            'seccion_titulo' => $row['seccion_titulo'],
            'opciones' => []
        ];
    }
    if ($row['opcion_id']) {
        $preguntas[$pid]['opciones'][$row['opcion_id']] = [
            'valor' => $row['opcion_valor'],
            'texto' => $row['opcion_texto'],
            'count' => 0
        ];
    }
}

// Contar respuestas por opción
$stmt = $db->prepare("
    SELECT rd.pregunta_id, rd.opcion_id, COUNT(*) as total
    FROM respuestas_detalle rd
    JOIN respuestas r ON rd.respuesta_id = r.id
    WHERE r.encuesta_id = ? AND r.completada = 1 AND rd.opcion_id IS NOT NULL
    GROUP BY rd.pregunta_id, rd.opcion_id
");
$stmt->execute([$encuesta['id']]);
$conteos = $stmt->fetchAll();

foreach ($conteos as $c) {
    if (isset($preguntas[$c['pregunta_id']]['opciones'][$c['opcion_id']])) {
        $preguntas[$c['pregunta_id']]['opciones'][$c['opcion_id']]['count'] = $c['total'];
    }
}

// Obtener respuestas de texto (para textareas)
$stmt = $db->prepare("
    SELECT rd.pregunta_id, rd.valor, rd.texto_adicional
    FROM respuestas_detalle rd
    JOIN respuestas r ON rd.respuesta_id = r.id
    WHERE r.encuesta_id = ? AND r.completada = 1 AND rd.valor IS NOT NULL AND rd.valor != ''
");
$stmt->execute([$encuesta['id']]);
$textosLibres = $stmt->fetchAll();

$textosPorPregunta = [];
foreach ($textosLibres as $t) {
    if (!isset($textosPorPregunta[$t['pregunta_id']])) {
        $textosPorPregunta[$t['pregunta_id']] = [];
    }
    $textosPorPregunta[$t['pregunta_id']][] = $t['valor'];
}

// Estadísticas de edad
$stmt = $db->prepare("
    SELECT rd.valor
    FROM respuestas_detalle rd
    JOIN respuestas r ON rd.respuesta_id = r.id
    JOIN preguntas p ON rd.pregunta_id = p.id
    WHERE r.encuesta_id = ? AND r.completada = 1 AND p.codigo = 'edad' AND rd.valor IS NOT NULL
");
$stmt->execute([$encuesta['id']]);
$edades = $stmt->fetchAll(PDO::FETCH_COLUMN);

$edadPromedio = count($edades) > 0 ? round(array_sum($edades) / count($edades), 1) : 0;
$edadMin = count($edades) > 0 ? min($edades) : 0;
$edadMax = count($edades) > 0 ? max($edades) : 0;

// Colores para gráficos
$colores = [
    'rgba(99, 102, 241, 0.8)',
    'rgba(16, 185, 129, 0.8)',
    'rgba(245, 158, 11, 0.8)',
    'rgba(239, 68, 68, 0.8)',
    'rgba(139, 92, 246, 0.8)',
    'rgba(6, 182, 212, 0.8)',
    'rgba(236, 72, 153, 0.8)',
    'rgba(34, 197, 94, 0.8)'
];

$baseUrl = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados - <?= htmlspecialchars($encuesta['titulo']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .results-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .results-header {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: var(--radius);
            margin-bottom: 24px;
        }

        .results-header h1 {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .results-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 8px;
        }

        .section-results {
            background: var(--card);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }

        .section-results h2 {
            font-size: 1.1rem;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }

        .question-result {
            margin-bottom: 32px;
        }

        .question-result:last-child {
            margin-bottom: 0;
        }

        .question-result h3 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .chart-container {
            position: relative;
            max-width: 100%;
            margin: 0 auto;
        }

        .chart-container.pie {
            max-width: 280px;
        }

        .chart-container.bar {
            height: 250px;
        }

        .text-responses {
            background: var(--bg);
            border-radius: 8px;
            padding: 16px;
            max-height: 300px;
            overflow-y: auto;
        }

        .text-response-item {
            padding: 12px;
            background: var(--card);
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            line-height: 1.5;
            border-left: 3px solid var(--primary);
        }

        .text-response-item:last-child {
            margin-bottom: 0;
        }

        .no-data {
            text-align: center;
            color: var(--text-light);
            padding: 20px;
            font-style: italic;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .back-button:hover {
            color: var(--primary);
        }

        .back-button svg {
            width: 20px;
            height: 20px;
        }

        .legend-custom {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            justify-content: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        @media (max-width: 480px) {
            .results-container {
                padding: 12px;
            }

            .section-results {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat-number {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="results-container">
        <a href="<?= $baseUrl ?>/index.php?t=<?= htmlspecialchars($tenant) ?>&e=<?= htmlspecialchars($codigo) ?>" class="back-button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            Volver a la encuesta
        </a>

        <div class="results-header">
            <h1>Resultados de la Encuesta</h1>
            <p><?= htmlspecialchars($encuesta['titulo']) ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $totalRespuestas ?></div>
                <div class="stat-label">Respuestas totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $edadPromedio ?></div>
                <div class="stat-label">Edad promedio</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $edadMin ?></div>
                <div class="stat-label">Edad mínima</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $edadMax ?></div>
                <div class="stat-label">Edad máxima</div>
            </div>
        </div>

        <?php
        $seccionActual = null;
        $chartIndex = 0;

        foreach ($preguntas as $pregunta):
            // Saltar edad (ya mostrada en stats)
            if ($pregunta['codigo'] === 'edad') continue;

            // Nueva sección
            if ($seccionActual !== $pregunta['seccion_numero']):
                if ($seccionActual !== null) echo '</div>';
                $seccionActual = $pregunta['seccion_numero'];
        ?>
        <div class="section-results">
            <h2>Sección <?= $pregunta['seccion_numero'] ?>: <?= htmlspecialchars($pregunta['seccion_titulo']) ?></h2>
        <?php endif; ?>

            <div class="question-result">
                <h3><?= htmlspecialchars($pregunta['texto']) ?></h3>

                <?php if ($pregunta['tipo'] === 'textarea'): ?>
                    <?php if (isset($textosPorPregunta[$pregunta['id']]) && count($textosPorPregunta[$pregunta['id']]) > 0): ?>
                        <div class="text-responses">
                            <?php foreach ($textosPorPregunta[$pregunta['id']] as $texto): ?>
                                <div class="text-response-item"><?= htmlspecialchars($texto) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">No hay respuestas de texto</div>
                    <?php endif; ?>

                <?php elseif (count($pregunta['opciones']) > 0): ?>
                    <?php
                    $labels = [];
                    $data = [];
                    $totalPregunta = 0;
                    foreach ($pregunta['opciones'] as $op) {
                        $labels[] = $op['texto'];
                        $data[] = $op['count'];
                        $totalPregunta += $op['count'];
                    }
                    $chartId = 'chart_' . $chartIndex;
                    $chartIndex++;
                    $isPie = count($pregunta['opciones']) <= 4 && $pregunta['tipo'] === 'radio';
                    ?>

                    <?php if ($totalPregunta > 0): ?>
                        <div class="chart-container <?= $isPie ? 'pie' : 'bar' ?>">
                            <canvas id="<?= $chartId ?>"></canvas>
                        </div>
                        <script>
                            new Chart(document.getElementById('<?= $chartId ?>'), {
                                type: '<?= $isPie ? 'doughnut' : 'bar' ?>',
                                data: {
                                    labels: <?= json_encode($labels) ?>,
                                    datasets: [{
                                        data: <?= json_encode($data) ?>,
                                        backgroundColor: <?= json_encode(array_slice($colores, 0, count($data))) ?>,
                                        borderWidth: 0,
                                        borderRadius: <?= $isPie ? '0' : '6' ?>
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: <?= $isPie ? 'true' : 'false' ?>,
                                    plugins: {
                                        legend: {
                                            display: <?= $isPie ? 'true' : 'false' ?>,
                                            position: 'bottom',
                                            labels: {
                                                padding: 16,
                                                usePointStyle: true,
                                                font: { size: 11 }
                                            }
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                    const value = context.raw;
                                                    const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                                    return context.label + ': ' + value + ' (' + pct + '%)';
                                                }
                                            }
                                        }
                                    },
                                    <?php if (!$isPie): ?>
                                    indexAxis: 'y',
                                    scales: {
                                        x: {
                                            beginAtZero: true,
                                            ticks: { stepSize: 1 },
                                            grid: { display: false }
                                        },
                                        y: {
                                            ticks: {
                                                font: { size: 11 },
                                                callback: function(value) {
                                                    const label = this.getLabelForValue(value);
                                                    if (label.length > 40) {
                                                        return label.substring(0, 40) + '...';
                                                    }
                                                    return label;
                                                }
                                            },
                                            grid: { display: false }
                                        }
                                    }
                                    <?php endif; ?>
                                }
                            });
                        </script>
                    <?php else: ?>
                        <div class="no-data">No hay respuestas aún</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php endforeach; ?>
        <?php if ($seccionActual !== null): ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
