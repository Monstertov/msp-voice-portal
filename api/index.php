<?php
ini_set('display_errors', '0');
require __DIR__ . '/lib.php';
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; sandbox");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
// No CORS headers: keys are intended for server-side integrations.
try {
    $config = require dirname(__DIR__) . '/config.php';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $route = is_string($path) && strpos($path, $base) === 0 ? rawurldecode(substr($path, strlen($base))) : '';
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $response = api_request($config, $_SERVER['REQUEST_METHOD'] ?? '', $route, $_GET, $authorization, $_SERVER['REMOTE_ADDR'] ?? '');
} catch (Throwable $e) {
    $response = ['status' => 503, 'body' => ['error' => 'Service unavailable']];
}
http_response_code($response['status']);
if ($response['status'] === 401) header('WWW-Authenticate: Bearer');
if ($response['status'] === 405) header('Allow: GET');
if ($response['status'] === 429) header('Retry-After: ' . max(1, (int) ($config['api']['time_window'] ?? 60)));
if (isset($response['file'])) {
    $types = ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'mp4' => 'audio/mp4', 'm4a' => 'audio/mp4',
              'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'aac' => 'audio/aac'];
    header('Content-Type: ' . ($types[pathinfo($response['file'], PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . $response['name'] . '"');
    header('Content-Length: ' . filesize($response['file']));
    readfile($response['file']);
} else {
    echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
