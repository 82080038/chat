<?php

/**
 * PHP built-in server router.
 * - Serves static files from public/ as-is
 * - Routes /dashboard/* (except real files) to the SPA index.html
 * - Everything else goes to index.php (API)
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve SPA for /dashboard/* routes that don't match a real file
if (str_starts_with($uri, '/dashboard')) {
    $file = __DIR__ . $uri;
    if ($uri !== '/dashboard' && file_exists($file) && !is_dir($file)) {
        return false; // Let PHP serve the static file
    }
    // Fallback to SPA index.html for client-side routing
    readfile(__DIR__ . '/dashboard/index.html');
    return true;
}

// Serve static files at root (favicon, robots.txt, etc.)
$rootFile = __DIR__ . $uri;
if ($uri !== '/' && file_exists($rootFile) && !is_dir($rootFile)) {
    return false; // Let PHP serve the static file
}

// Everything else → API entry point
require __DIR__ . '/index.php';
