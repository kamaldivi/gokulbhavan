<?php
/**
 * CORS + JSON headers — included at the top of every endpoint.
 * Only allows requests from the production domain and localhost dev.
 */

$allowed_origins = [
    'https://gokulbhavan.org',
    'https://www.gokulbhavan.org',
    'http://localhost:4321',   // Astro dev server
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight — browsers send OPTIONS before POST
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
