<?php
require_once 'config.php';

$tenant = isset($_GET['t']) ? $_GET['t'] : null;

if (!$tenant) {
    die("Tenant no especificado.");
}

$db = getDB();

// Obtener tenant
$stmt = $db->prepare("SELECT * FROM tenants WHERE slug = ? AND activo = 1");
$stmt->execute([$tenant]);
$tenantData = $stmt->fetch();

if (!$tenantData) {
    die("Organizacion no encontrada.");
}

// Obtener encuestas activas
$stmt = $db->prepare("
    SELECT e.*,
           (SELECT COUNT(*) FROM respuestas r WHERE r.encuesta_id = e.id AND r.completada = 1) as total_respuestas
    FROM encuestas e
    WHERE e.tenant_slug = ? AND e.activa = 1
    ORDER BY e.created_at DESC
");
$stmt->execute([$tenant]);
$encuestas = $stmt->fetchAll();

$baseUrl = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Encuestas disponibles de <?= htmlspecialchars($tenantData['nombre']) ?>">
    <meta name="theme-color" content="#6366f1">
    <title>Encuestas - <?= htmlspecialchars($tenantData['nombre']) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
    <style>
        .lista-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            min-height: calc(100vh - 100px);
        }

        .lista-header {
            text-align: center;
            margin-bottom: 32px;
            padding-top: 20px;
        }

        .lista-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .lista-header p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .encuesta-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            display: block;
            text-decoration: none;
            color: inherit;
            border-left: 4px solid var(--primary);
        }

        .encuesta-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.15);
        }

        .encuesta-card h2 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
        }

        .encuesta-card .descripcion {
            color: var(--text-light);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .encuesta-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .encuesta-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .encuesta-meta svg {
            width: 14px;
            height: 14px;
        }

        .badge-activa {
            display: inline-block;
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .empty-state svg {
            width: 60px;
            height: 60px;
            margin-bottom: 16px;
            opacity: 0.4;
        }
    </style>
</head>
<body>
    <header class="quast-header">
        <div class="header-inner">
            <span class="header-brand">Quast</span>
            <span class="header-tenant"><?= htmlspecialchars($tenantData['nombre']) ?></span>
        </div>
    </header>

    <div class="lista-container">
        <div class="lista-header">
            <h1>Encuestas disponibles</h1>
            <p><?= htmlspecialchars($tenantData['nombre']) ?></p>
        </div>

        <?php if (count($encuestas) > 0): ?>
            <?php foreach ($encuestas as $enc): ?>
            <a href="<?= $baseUrl ?>/<?= htmlspecialchars($tenant) ?>/<?= htmlspecialchars($enc['codigo']) ?>" class="encuesta-card">
                <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:8px;">
                    <h2><?= htmlspecialchars($enc['titulo']) ?></h2>
                    <span class="badge-activa">Activa</span>
                </div>
                <?php if ($enc['descripcion']): ?>
                    <p class="descripcion"><?= htmlspecialchars($enc['descripcion']) ?></p>
                <?php endif; ?>
                <div class="encuesta-meta">
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        <?= $enc['total_respuestas'] ?> respuesta<?= $enc['total_respuestas'] != 1 ? 's' : '' ?>
                    </span>
                    <?php if ($enc['fecha_inicio']): ?>
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Desde <?= date('d/m/Y', strtotime($enc['fecha_inicio'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p>No hay encuestas disponibles en este momento.</p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="quast-footer">
        <span>Quast &middot; Encuestas verificadas por <a href="https://verumax.com" target="_blank" rel="noopener">VERUMax</a></span>
    </footer>
</body>
</html>
