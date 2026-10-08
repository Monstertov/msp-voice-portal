<?php
// Standalone, no network: php tests/api_test.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/api/lib.php';
$dir = sys_get_temp_dir() . '/msp_api_test_' . bin2hex(random_bytes(8)) . '/';
mkdir($dir, 0700);
$config = ['storage_dir' => $dir, 'api' => ['enabled' => true, 'key_requests' => 1000, 'ip_requests' => 1000, 'time_window' => 60]];
$id = '20261008-120000-abcdef01';
$other = '20261008-130000-abcdef02';
$checks = 0;
function check($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function request($config, $key, $route = 'v1/submissions', $query = [], $ip = '192.0.2.1', $method = 'GET') {
    return api_request($config, $method, $route, $query, $key === null ? '' : 'Bearer ' . $key, $ip);
}
try {
    $oldConfig = ['storage_dir' => $dir];
    check(request($oldConfig, null)['status'] === 404, 'Missing API config must be disabled');
    $off = $config; $off['api']['enabled'] = false;
    check(request($off, null)['status'] === 404, 'Disabled API must return 404');
    check(!file_exists($dir . 'api-keys.json'), 'Disabled requests must not create storage');
    $key = api_create_key($config, 'Read only');
    $contact = api_create_key($config, 'Contact', true);
    $keyId = explode('_', $key)[1];
    check(strpos(file_get_contents($dir . 'api-keys.json'), $key) === false, 'Plaintext key must not be stored');
    check(api_auth($config, 'Bearer ' . $key)['id'] === $keyId, 'Valid authentication');
    foreach (['', 'Basic ' . $key, 'Bearer bad', 'Bearer msp_' . str_repeat('a', 16) . '_' . str_repeat('b', 64), 'Bearer ' . substr($key, 0, -1) . ($key[strlen($key) - 1] === 'a' ? 'b' : 'a')] as $bad) {
        $response = api_request($config, 'GET', 'v1/submissions', [], $bad, '192.0.2.2');
        check($response === ['status' => 401, 'body' => ['error' => 'Unauthorized']], 'Bad keys use the same 401 response');
    }
    mkdir($dir . $id); mkdir($dir . $other);
    $meta = ['created_at' => '2026-10-08T12:00:00+00:00', 'severity' => 'normal', 'lang' => 'en',
        'inputMethod' => 'record', 'companyName' => 'A &amp; B', 'notes' => 'Notes', 'textContent' => '',
        'transcript' => 'Hello', 'contactEmail' => 'person@example.com', 'contactPhone' => '1234', 'audio' => 'original.wav'];
    file_put_contents($dir . $id . '/meta.json', json_encode($meta));
    file_put_contents($dir . $other . '/meta.json', json_encode(array_replace($meta, ['created_at' => '2026-10-08T13:00:00+00:00'])));
    file_put_contents($dir . $id . '/original.wav', 'RIFF test');
    file_put_contents($dir . $id . '/ai-20261008-120100-tts.mp3', 'test mp3');
    file_put_contents($dir . $id . '/ai-20261008-120200-swap.wav', 'test wav');
    $detail = request($config, $key, 'v1/submissions/' . $id)['body'];
    check(!isset($detail['contact_email']) && !isset($detail['contact_phone']), 'Contact scope defaults off');
    check($detail['company_name'] === 'A & B' && $detail['transcript'] === 'Hello', 'Details use stored metadata');
    check(request($config, $contact, 'v1/submissions/' . $id)['body']['contact_email'] === 'person@example.com', 'Contact scope grants access');
    $list = request($config, $key, 'v1/submissions', ['limit' => '1'])['body'];
    check($list['submissions'][0]['id'] === $other && $list['next_offset'] === 1, 'Newest first and pagination');
    check(!isset($list['submissions'][0]['contact_email']), 'List respects contact scope');
    check(request($config, $key, 'v1/submissions', ['offset' => '1'])['body']['submissions'][0]['id'] === $id, 'Offset pagination');
    check(count(request($config, $key, 'v1/submissions', ['since' => '2026-10-08T12:30:00Z'])['body']['submissions']) === 1, 'Since timestamp filter');
    check(count(request($config, $key, 'v1/submissions', ['since' => (string) strtotime('2026-10-08T12:30:00Z')])['body']['submissions']) === 1, 'Unix timestamp filter');
    foreach ([['limit' => '101'], ['offset' => '-1'], ['since' => 'yesterday'], ['limit' => []]] as $query) {
        check(request($config, $key, 'v1/submissions', $query)['status'] === 400, 'Invalid query rejected');
    }
    check(request($config, $key, 'v1/submissions/' . $id . '/recording')['file'] === realpath($dir . $id . '/original.wav'), 'Original recording download');
    check(count(request($config, $key, 'v1/submissions/' . $id . '/audio')['body']['files']) === 2, 'MP3 and WAV listed');
    check(isset(request($config, $key, 'v1/submissions/' . $id . '/audio/ai-20261008-120100-tts.mp3')['file']), 'Generated audio download');
    foreach (['v1/submissions/../settings', 'v1/submissions/' . $id . '/audio/../../api-keys.json',
        'v1/submissions/' . $id . '/audio/original.wav', 'v1/submissions/' . $id . '/audio/ai-test.php'] as $route) {
        check(request($config, $key, $route)['status'] === 404, 'Traversal and unsupported files rejected');
    }
    check(api_file($config, '../', 'meta.json') === null && api_file($config, $id, '../api-keys.json') === null, 'Path helper rejects traversal');
    $outside = $dir . 'outside.wav'; file_put_contents($outside, 'outside');
    if (function_exists('symlink') && @symlink($outside, $dir . $id . '/ai-outside.wav')) {
        check(api_file($config, $id, 'ai-outside.wav') === null, 'Resolved audio cannot escape submission');
        check(count(api_audio_files($config, $id)) === 2, 'Escaping symlink omitted from list');
    }
    $tampered = $meta; $tampered['audio'] = '../api-keys.json';
    file_put_contents($dir . $id . '/meta.json', json_encode($tampered));
    check(request($config, $key, 'v1/submissions/' . $id . '/recording')['status'] === 404, 'Metadata cannot select arbitrary files');
    check(request($config, $key, 'v1/submissions', [], '192.0.2.1', 'POST')['status'] === 405, 'Read only methods');
    $limited = $config; $limited['api']['key_requests'] = 1;
    $rateKey = api_create_key($config, 'Rate test');
    check(request($limited, $rateKey, 'v1/submissions', [], '192.0.2.3')['status'] === 200, 'First key request allowed');
    check(request($limited, $rateKey, 'v1/submissions', [], '192.0.2.4')['status'] === 429, 'Key limit works across IPs');
    $limited = $config; $limited['api']['ip_requests'] = 1;
    check(request($limited, null, 'v1/submissions', [], '192.0.2.5')['status'] === 401, 'First invalid IP request');
    check(request($limited, $contact, 'v1/submissions', [], '192.0.2.5')['status'] === 429, 'IP limit includes bad authentication and different keys');
    api_revoke_key($config, $keyId);
    check(api_auth($config, 'Bearer ' . $key) === null, 'Revoked key rejected');
    check(request($config, $key)['status'] === 401, 'Revoked key uses generic 401');
    $keys = json_decode(file_get_contents($dir . 'api-keys.json'), true);
    check($keys['keys'][$keyId]['last_used'] !== null, 'Last use recorded');
    $log = file_get_contents($dir . 'api-use.log');
    check(strpos($log, 'key=' . $keyId) !== false && strpos($log, $key) === false, 'Log contains key id only');
    echo "API tests passed ($checks checks).\n";
} finally {
    // Remove only this test's storage and throttle files.
    foreach (glob($dir . '*') ?: [] as $file) {
        if (is_dir($file) && !is_link($file)) {
            foreach (glob($file . '/*') ?: [] as $child) unlink($child);
            rmdir($file);
        } else unlink($file);
    }
    rmdir($dir);
    foreach (['192.0.2.1', '192.0.2.2', '192.0.2.3', '192.0.2.4', '192.0.2.5'] as $ip) {
        @unlink(sys_get_temp_dir() . '/msp_api_' . hash('sha256', $dir . 'ip' . $ip));
    }
    foreach ([$key ?? '', $contact ?? '', $rateKey ?? ''] as $token) {
        $parts = explode('_', $token);
        if (isset($parts[1])) @unlink(sys_get_temp_dir() . '/msp_api_' . hash('sha256', $dir . 'key' . $parts[1]));
    }
}
