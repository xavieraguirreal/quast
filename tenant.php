<?php
/**
 * Detección de tenant por dominio personalizado
 * Incluir desde config.php o directamente donde se necesite
 */

// Mapeo de dominios personalizados a tenant
function detectTenantFromDomain(): ?string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $map = [
        'encuestas.sajur.org' => 'sajur',
        // Agregar más dominios acá:
        // 'encuestas.liberte.edu.ar' => 'liberte',
    ];
    return $map[$host] ?? null;
}

// Obtener tenant: primero por dominio, luego por parámetro GET
function getTenant(?string $default = null): ?string {
    return detectTenantFromDomain() ?? ($_GET['t'] ?? $default);
}

// Verifica si el acceso es desde un dominio personalizado
function isCustomDomain(): bool {
    return detectTenantFromDomain() !== null;
}
