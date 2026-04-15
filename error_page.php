<?php
/**
 * Pantalla de error amigable reutilizable.
 * Tipos: 'no_tenant', 'org_inactiva'
 */
function showErrorPage(string $baseUrl, string $tipo, ?string $nombre = null): void {
    $config = match($tipo) {
        'org_inactiva' => [
            'titulo' => 'Organización no disponible',
            'mensaje' => $nombre
                ? htmlspecialchars($nombre) . ' no tiene encuestas activas en este momento.'
                : 'Esta organización no tiene encuestas activas en este momento.',
            'detalle' => 'Si tenés consultas, contactá a la organización responsable.',
            'color' => 'linear-gradient(135deg, #f59e0b, #d97706)',
            'icono' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>',
        ],
        default => [
            'titulo' => 'Página no encontrada',
            'mensaje' => 'La dirección que ingresaste no corresponde a ninguna organización o encuesta.',
            'detalle' => 'Verificá el enlace e intentá nuevamente.',
            'color' => 'linear-gradient(135deg, #94a3b8, #64748b)',
            'icono' => '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>',
        ],
    };
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#6366f1">
    <title><?= $config['titulo'] ?> — Quast</title>
    <link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
</head>
<body>
    <header class="quast-header">
        <div class="header-inner">
            <span class="header-brand">Quast</span>
        </div>
    </header>

    <div class="container">
        <div class="screen active" style="justify-content:center; align-items:center; padding:24px; text-align:center;">
            <div style="max-width:440px;">
                <div style="width:80px; height:80px; border-radius:50%; margin:0 auto 24px; display:flex; align-items:center; justify-content:center; background:<?= $config['color'] ?>;">
                    <?= $config['icono'] ?>
                </div>
                <h1 style="font-size:1.5rem; font-weight:700; margin-bottom:12px; color:var(--text);"><?= $config['titulo'] ?></h1>
                <p style="color:var(--text-light); font-size:0.9rem; margin-bottom:24px;"><?= $config['mensaje'] ?></p>
                <p style="color:var(--text-light); font-size:0.85rem;"><?= $config['detalle'] ?></p>
            </div>
        </div>
    </div>

    <footer class="quast-footer">
        <span>Quast &middot; Encuestas verificadas por <a href="https://verumax.com" target="_blank" rel="noopener">VERUMax</a></span>
    </footer>
</body>
</html>
<?php } ?>
