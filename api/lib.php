<?php
// Read-only API helpers. No sessions, mail, or changes to submission processing.
function api_enabled($config) { return !empty($config['api']['enabled']); }
function api_data_dir($config) { return rtrim($config['storage_dir'] ?? dirname(__DIR__) . '/data/', '/\\') . '/'; }
function api_valid_id($id) { return is_string($id) && preg_match('/^\d{8}-\d{6}-[0-9a-f]{8}$/D', $id); }

// The same file-backed approach as portal/admin throttling, with a lock for concurrent requests.
function api_json_file($file, $callback) {
    $fp = @fopen($file, 'c+');
    if (!$fp) throw new RuntimeException('Cannot open API storage. Check data folder permissions.');
    try {
        if (!flock($fp, LOCK_EX)) throw new RuntimeException('Cannot lock API storage.');
        $raw = stream_get_contents($fp);
        $data = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($data)) throw new RuntimeException('Cannot read API storage.');
        $before = $data;
        $result = $callback($data);
        if ($data !== $before) {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            rewind($fp);
            if ($json === false || !ftruncate($fp, 0) || fwrite($fp, $json) !== strlen($json) || !fflush($fp)) {
                throw new RuntimeException('Cannot write API storage.');
            }
        }
        return $result;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function api_keys($config, $callback) {
    $dir = api_data_dir($config);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Cannot create data folder.');
    return api_json_file($dir . 'api-keys.json', $callback);
}

function api_create_key($config, $name, $contact = false) {
    $name = is_string($name) ? trim($name) : '';
    if ($name === '' || strlen($name) > 100) throw new RuntimeException('Enter a key name (up to 100 bytes).');
    return api_keys($config, function (&$data) use ($name, $contact) {
        $data['pepper'] = $data['pepper'] ?? bin2hex(random_bytes(32));
        $id = bin2hex(random_bytes(8));
        $key = 'msp_' . $id . '_' . bin2hex(random_bytes(32));
        $data['keys'][$id] = [
            'name' => $name, 'created' => date('c'), 'last_used' => null, 'revoked' => null,
            'scopes' => $contact ? ['contact'] : [],
            'hash' => hash_hmac('sha256', $key, $data['pepper']),
        ];
        return $key;
    });
}

function api_revoke_key($config, $id) {
    api_keys($config, function (&$data) use ($id) {
        if (is_string($id) && isset($data['keys'][$id])) $data['keys'][$id]['revoked'] = date('c');
    });
}

function api_auth($config, $authorization) {
    return api_keys($config, function (&$data) use ($authorization) {
        $key = ''; $id = '';
        if (is_string($authorization) && preg_match('/^Bearer (msp_([0-9a-f]{16})_[0-9a-f]{64})$/D', $authorization, $m)) {
            $key = $m[1]; $id = $m[2];
        }
        $record = $data['keys'][$id] ?? null;
        // Always compute and compare one fixed-length HMAC, including unknown and revoked keys.
        $actual = hash_hmac('sha256', $key, $data['pepper'] ?? str_repeat('0', 64));
        $matches = hash_equals($record['hash'] ?? str_repeat('0', 64), $actual);
        return $matches && $record && empty($record['revoked']) ? ['id' => $id] + $record : null;
    });
}

function api_rate_limit($config, $kind, $identity, $max, $window) {
    $file = sys_get_temp_dir() . '/msp_api_' . hash('sha256', api_data_dir($config) . $kind . $identity);
    return api_json_file($file, function (&$data) use ($max, $window) {
        $now = time();
        if (!$data || $now - $data['time'] >= $window) $data = ['time' => $now, 'count' => 0];
        if ($data['count'] >= $max) return false;
        $data['count']++;
        return true;
    });
}

function api_used($config, $id) {
    api_keys($config, function (&$data) use ($id) {
        if (isset($data['keys'][$id])) $data['keys'][$id]['last_used'] = date('c');
    });
    // Never log Authorization, the key, query strings or submission contents.
    if (file_put_contents(api_data_dir($config) . 'api-use.log', date('c') . ' key=' . $id . "\n", FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('Cannot write API log.');
    }
}

function api_file($config, $id, $name) {
    if (!api_valid_id($id) || !is_string($name) || !preg_match('/^(meta\.json|original\.(mp3|wav|mp4|webm|ogg|aac|m4a|bin)|ai-[0-9a-z-]+\.(mp3|wav))$/D', $name)) return null;
    $root = realpath(api_data_dir($config));
    $dir = realpath(api_data_dir($config) . $id);
    $path = realpath(api_data_dir($config) . $id . '/' . $name);
    if (!$root || !$dir || !$path || $dir !== $root . DIRECTORY_SEPARATOR . $id
        || dirname($path) !== $dir || !is_file($path)) return null;
    return $path;
}

function api_meta($config, $id) {
    $file = api_file($config, $id, 'meta.json');
    return $file ? json_decode(file_get_contents($file), true) : null;
}

function api_submission($id, $meta, $key) {
    $result = ['id' => $id, 'created' => $meta['created_at'] ?? null];
    foreach (['severity' => 'severity', 'language' => 'lang', 'input_type' => 'inputMethod',
              'company_name' => 'companyName', 'notes' => 'notes', 'text' => 'textContent', 'transcript' => 'transcript'] as $out => $in) {
        $result[$out] = isset($meta[$in]) ? html_entity_decode((string) $meta[$in], ENT_QUOTES, 'UTF-8') : null;
    }
    if (in_array('contact', $key['scopes'] ?? [], true)) {
        foreach (['contact_email' => 'contactEmail', 'contact_phone' => 'contactPhone'] as $out => $in) {
            $result[$out] = html_entity_decode((string) ($meta[$in] ?? ''), ENT_QUOTES, 'UTF-8');
        }
    }
    return $result;
}

function api_audio_files($config, $id) {
    $files = [];
    if (!api_valid_id($id)) return $files;
    foreach (glob(api_data_dir($config) . $id . '/ai-*') ?: [] as $file) {
        $name = basename($file);
        if (api_file($config, $id, $name)) $files[] = ['name' => $name, 'size' => filesize($file)];
    }
    usort($files, function ($a, $b) { return strcmp($b['name'], $a['name']); });
    return $files;
}

// Returns a response for the front controller and network-free tests.
function api_request($config, $method, $route, $query, $authorization, $ip) {
    $error = function ($status, $message) { return ['status' => $status, 'body' => ['error' => $message]]; };
    if (!api_enabled($config)) return $error(404, 'Not found');
    $settings = $config['api'];
    $window = max(1, (int) ($settings['time_window'] ?? 60));
    if (!api_rate_limit($config, 'ip', $ip, max(1, (int) ($settings['ip_requests'] ?? 120)), $window)) return $error(429, 'Rate limit exceeded');
    $key = api_auth($config, $authorization);
    if (!$key) return $error(401, 'Unauthorized');
    api_used($config, $key['id']);
    if (!api_rate_limit($config, 'key', $key['id'], max(1, (int) ($settings['key_requests'] ?? 60)), $window)) return $error(429, 'Rate limit exceeded');
    if ($method !== 'GET') return $error(405, 'Method not allowed');
    if ($route === 'v1/submissions') {
        $limit = $query['limit'] ?? '50'; $offset = $query['offset'] ?? '0'; $since = $query['since'] ?? null;
        if (!is_scalar($limit) || !is_scalar($offset) || !ctype_digit((string) $limit) || !ctype_digit((string) $offset)
            || (int) $limit < 1 || (int) $limit > 100 || strlen((string) $offset) > 9) return $error(400, 'Invalid pagination');
        $stamp = null;
        if ($since !== null) {
            if (!is_string($since) || !preg_match('/^(\d{1,10}|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2}))$/D', $since)) return $error(400, 'Invalid since timestamp');
            $stamp = ctype_digit($since) ? (int) $since : strtotime($since);
            if ($stamp === false) return $error(400, 'Invalid since timestamp');
        }
        $items = [];
        foreach (glob(api_data_dir($config) . '*/meta.json') ?: [] as $file) {
            $id = basename(dirname($file));
            $meta = api_meta($config, $id);
            if (!is_array($meta)) continue;
            if ($stamp !== null && strtotime($meta['created_at'] ?? '') <= $stamp) continue;
            $items[] = api_submission($id, $meta, $key);
        }
        usort($items, function ($a, $b) {
            $order = (strtotime($b['created'] ?? '') ?: 0) <=> (strtotime($a['created'] ?? '') ?: 0);
            return $order ?: strcmp($b['id'], $a['id']);
        });
        $next = (int) $offset + (int) $limit;
        return ['status' => 200, 'body' => ['submissions' => array_slice($items, (int) $offset, (int) $limit),
            'next_offset' => $next < count($items) ? $next : null]];
    }
    $parts = explode('/', $route);
    if (count($parts) < 3 || $parts[0] !== 'v1' || $parts[1] !== 'submissions') return $error(404, 'Not found');
    $id = $parts[2]; $meta = api_meta($config, $id);
    if (!is_array($meta)) return $error(404, 'Not found');
    if (count($parts) === 3) return ['status' => 200, 'body' => api_submission($id, $meta, $key)];
    if (count($parts) === 4 && $parts[3] === 'audio') return ['status' => 200, 'body' => ['files' => api_audio_files($config, $id)]];
    $name = null;
    if (count($parts) === 4 && $parts[3] === 'recording' && preg_match('/^original\.[a-z0-9]+$/D', $meta['audio'] ?? '')) $name = $meta['audio'];
    if (count($parts) === 5 && $parts[3] === 'audio' && preg_match('/^ai-[0-9a-z-]+\.(mp3|wav)$/D', $parts[4])) $name = $parts[4];
    $file = $name ? api_file($config, $id, $name) : null;
    return $file ? ['status' => 200, 'file' => $file, 'name' => $id . '-' . $name] : $error(404, 'Not found');
}
