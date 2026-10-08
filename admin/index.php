<?php
require_once dirname(__DIR__) . '/tracking.php';
// Admin page: sign in, see stored submissions, make AI voice versions with ElevenLabs.
// Admin users live in config.php. AI settings: defaults from config.php, changes made on the
// settings page are saved in data/settings.json (never in git, survives updates).
$config = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/theme.php';
$dataDir = $config['storage_dir'] ?? dirname(__DIR__) . '/data/';
$users = $config['admin']['users'] ?? [];
$settingsFile = $dataDir . 'settings.json';

// Portal languages (see assets/js/languages.js); each gets its own default voice
$languages = ['nl' => 'Dutch', 'en' => 'English'];
$formats = [
    'mp3_44100_128' => 'MP3 44.1 kHz 128 kbps',
    'mp3_22050_32'  => 'MP3 22 kHz 32 kbps (small file)',
    'pcm_8000'      => 'WAV 8 kHz mono (classic phone systems)',
    'pcm_16000'     => 'WAV 16 kHz mono (HD voice)',
];
$defaults = [
    'api_key' => '',
    'voices' => ['nl' => '', 'en' => 'EXAVITQu4vr4xnSDxMaL'],
    'tts_model' => 'eleven_multilingual_v2',
    'output_format' => 'mp3_44100_128',
    'stability' => 0.5,
    'similarity_boost' => 0.75,
    'style' => 0.0,
    'speed' => 1.0,
];
$saved = is_file($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];
$el = array_replace_recursive($defaults, $config['elevenlabs'] ?? [], $saved);

// Security headers from config, plus a strict CSP: the admin page runs no JavaScript at all
foreach ($config['security_headers']['additional_headers'] ?? [] as $name => $value) {
    header("$name: $value");
}
header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; "
    . "img-src 'self' data:; media-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

// Switched off (or not set up) in config.php: behave as if the page does not exist
if (empty($config['admin']['enabled']) || !$users) {
    http_response_code(404);
    exit('Not found');
}
$aiOn = !empty($config['elevenlabs']['enabled']);
$apiOn = !empty($config['api']['enabled']);
if ($apiOn) require dirname(__DIR__) . '/api/lib.php';
$createdApiKey = null;

session_name('msp_admin');
session_start(['cookie_httponly' => true, 'cookie_secure' => true, 'cookie_samesite' => 'Strict']);
$csrf = $_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));
if (!empty($_SESSION['admin']) && time() - ($_SESSION['seen'] ?? 0) > 7200) {
    unset($_SESSION['admin']); // signed out after 2 hours without activity
}
$_SESSION['seen'] = time();

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
// meta.json holds the values as process.php stored them (already HTML-escaped), so decode before use
function plain($s) { return html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8'); }
function back($to = './') { header('Location: ' . $to); exit; }
function flash($msg, $ok = true) { $_SESSION['flash'] = [$msg, $ok]; }
// Request value as a string (arrays like ?x[]=1 become '', so they can't be used as array keys)
function str($v) { return is_string($v) ? $v : ''; }
function valid_id($id) { return is_string($id) && preg_match('/^\d{8}-\d{6}-[0-9a-f]{8}$/', $id); }
function valid_voice($v) { return is_string($v) && preg_match('/^[A-Za-z0-9]{10,40}$/', $v); }
// "sk_8b64…5913": enough to recognise which key is active without showing it
function key_hint($key) { return strlen($key) > 12 ? substr($key, 0, 7) . '…' . substr($key, -4) : '…'; }

// One call to the ElevenLabs API. $fields null = GET; $multipart = send files. Returns the body.
function elevenlabs($el, $path, $fields = null, $multipart = false) {
    $headers = ['xi-api-key: ' . $el['api_key']];
    $ch = curl_init('https://api.elevenlabs.io' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 180, CURLOPT_CONNECTTIMEOUT => 10]);
    if ($fields !== null) {
        if (!$multipart) {
            $fields = json_encode($fields);
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields]);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException("ElevenLabs unreachable: $err");
    if ($code !== 200) {
        $detail = json_decode($body, true)['detail'] ?? null;
        $msg = is_array($detail) ? ($detail['message'] ?? json_encode($detail)) : ($detail ?: "HTTP $code");
        throw new RuntimeException('ElevenLabs: ' . $msg);
    }
    return $body;
}

function voice_settings($el) {
    return [
        'stability' => (float) $el['stability'],
        'similarity_boost' => (float) $el['similarity_boost'],
        'style' => (float) $el['style'],
        'speed' => (float) $el['speed'],
    ];
}

// Save ElevenLabs audio; raw PCM gets a WAV header so phone systems and players can open it
function save_audio($el, $base, $audio) {
    if (preg_match('/^pcm_(\d+)$/', $el['output_format'], $m)) {
        $rate = (int) $m[1];
        $audio = 'RIFF' . pack('V', 36 + strlen($audio)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
            . 'data' . pack('V', strlen($audio)) . $audio;
        $file = "$base.wav";
    } else {
        $file = "$base.mp3";
    }
    if (file_put_contents($file, $audio) === false) throw new RuntimeException('Cannot write ' . basename($file));
    return $file;
}

function tts($el, $voice, $text) {
    return elevenlabs($el, "/v1/text-to-speech/$voice?output_format=" . urlencode($el['output_format']),
        ['text' => $text, 'model_id' => $el['tts_model'], 'voice_settings' => voice_settings($el)]);
}

// API key actions use the same admin session and CSRF token. Creation renders the key in
// this response only: the plaintext key is never saved in the session.
$apiAction = $apiOn && $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? '', ['api_create', 'api_revoke'], true);
if ($apiAction) {
    if (!hash_equals($csrf, str($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Session expired, go back and reload the page.');
    }
    if (empty($_SESSION['admin'])) back();
    try {
        if ($_POST['action'] === 'api_create') {
            $createdApiKey = api_create_key($config, str($_POST['name'] ?? ''), !empty($_POST['contact']));
        } else {
            api_revoke_key($config, str($_POST['key_id'] ?? ''));
            flash('API key revoked.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), false);
    }
    if ($_POST['action'] === 'api_revoke') back('?page=api');
}

// ---- POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$apiAction) {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Session expired, go back and reload the page.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        // Throttle: 5 failed attempts per 15 minutes per IP
        $tf = sys_get_temp_dir() . '/msp_admin_login_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        $t = json_decode((string) @file_get_contents($tf), true) ?: ['n' => 0, 't' => time()];
        if (time() - $t['t'] > 900) $t = ['n' => 0, 't' => time()];
        $user = (string) ($_POST['user'] ?? '');
        if ($t['n'] >= 5) {
            flash('Too many attempts. Try again in 15 minutes.', false);
        // Unknown users are checked against a dummy hash, so response time does not reveal usernames
        } elseif (password_verify((string) ($_POST['password'] ?? ''), $users[$user] ?? '$2y$10$2A7YEqLGqa031Xru3MfYCeycqkBua3TrRn2MmC8Lm1LH6lpUOdde2')
            && isset($users[$user])) {
            @unlink($tf);
            session_regenerate_id(true);
            $_SESSION['admin'] = $user;
        } else {
            $t['n']++;
            file_put_contents($tf, json_encode($t));
            flash('Wrong username or password.', false);
        }
        back();
    }

    if ($action === 'logout') {
        session_destroy();
        back();
    }

    if (empty($_SESSION['admin'])) back();
    if (!$aiOn && $action !== 'delete') back();

    if ($action === 'add_voice') {
        $lib = $_SESSION['lib'][str($_POST['voice_id'] ?? '')] ?? null;
        try {
            if (!$lib) throw new RuntimeException('Search the library again, that result expired.');
            elevenlabs($el, '/v1/voices/add/' . rawurlencode($lib['owner']) . '/' . rawurlencode($_POST['voice_id']), ['new_name' => $lib['name']]);
            unset($_SESSION['voices']);
            flash($lib['name'] . ' added to your voices. Pick it in the voice dropdowns.');
        } catch (Throwable $e) {
            flash($e->getMessage(), false);
        }
        back('?page=settings&' . http_build_query(array_intersect_key($_POST, ['lib_lang' => 1, 'lib_q' => 1, 'lib_gender' => 1])) . '#library');
    }

    // Settings page actions
    if ($action === 'save_settings') {
        $new = $saved;
        $key = trim((string) ($_POST['api_key'] ?? ''));
        if (!empty($_POST['clear_key'])) {
            unset($new['api_key']);
        } elseif ($key !== '') {
            if (!preg_match('/^[A-Za-z0-9_]{20,100}$/', $key)) {
                flash('That does not look like an ElevenLabs API key.', false);
                back('?page=settings');
            }
            $new['api_key'] = $key;
        }
        foreach ($languages as $code => $_) {
            $v = $_POST['voices'][$code] ?? '';
            $new['voices'][$code] = valid_voice($v) ? $v : '';
        }
        if (preg_match('/^[a-z0-9_]{3,60}$/', (string) ($_POST['tts_model'] ?? ''))) $new['tts_model'] = $_POST['tts_model'];
        if (isset($formats[str($_POST['output_format'] ?? '')])) $new['output_format'] = $_POST['output_format'];
        foreach (['stability' => [0, 1], 'similarity_boost' => [0, 1], 'style' => [0, 1], 'speed' => [0.7, 1.2]] as $f => [$lo, $hi]) {
            if (is_numeric($_POST[$f] ?? null)) $new[$f] = max($lo, min($hi, round((float) $_POST[$f], 2)));
        }
        if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
        if (file_put_contents($settingsFile, json_encode($new, JSON_PRETTY_PRINT)) === false) {
            flash("Cannot write $settingsFile. Check that PHP can write to the data folder.", false);
        } else {
            unset($_SESSION['voices'], $_SESSION['models']);
            flash('Settings saved.');
        }
        back('?page=settings');
    }

    if ($action === 'test_voice') {
        $code = isset($languages[str($_POST['lang'] ?? '')]) ? $_POST['lang'] : 'en';
        $sample = ['nl' => 'Welkom bij ons bedrijf. Al onze medewerkers zijn in gesprek, een moment geduld alstublieft.',
                   'en' => 'Welcome to our company. All of our colleagues are busy, please hold the line.'][$code] ?? 'Hello.';
        try {
            if ($el['api_key'] === '') throw new RuntimeException('Add an API key first.');
            $voice = $el['voices'][$code] ?: $el['voices']['en'];
            array_map('unlink', glob($dataDir . 'preview.*') ?: []);
            save_audio($el, $dataDir . 'preview', tts($el, $voice, $sample));
            $_SESSION['preview'] = $code;
            flash($languages[$code] . ' test ready, play it below.');
        } catch (Throwable $e) {
            flash($e->getMessage(), false);
        }
        back('?page=settings');
    }

    // Submission actions
    $id = $_POST['id'] ?? '';
    $metaFile = $dataDir . $id . '/meta.json';
    if (!valid_id($id) || !is_file($metaFile)) back();
    $meta = json_decode(file_get_contents($metaFile), true);
    $voice = valid_voice($_POST['voice'] ?? null) ? $_POST['voice'] : (($el['voices'][$meta['lang'] ?? ''] ?? '') ?: $el['voices']['en']);
    $out = $dataDir . $id . '/ai-' . date('Ymd-His');

    try {
        if ($action === 'delete') {
            array_map('unlink', glob($dataDir . $id . '/*'));
            rmdir($dataDir . $id);
            flash('Submission deleted.');
            back();
        }
        // AI voice is for text submissions only; a recording or upload is already audio
        if (!empty($meta['audio'])) throw new RuntimeException('This submission is already audio. Download it instead.');
        if ($el['api_key'] === '') throw new RuntimeException('No ElevenLabs API key yet. Add one under AI settings.');
        if ($action === 'tts') {
            $text = trim((string) ($_POST['text'] ?? ''));
            if ($text === '') throw new RuntimeException('Type some text first.');
            if (mb_strlen($text) > 5000) throw new RuntimeException('Text is longer than 5000 characters.');
            save_audio($el, "$out-tts", tts($el, $voice, $text));
            flash('AI voice ready.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), false);
    }
    back("./#s-$id");
}

// ---- Voice library preview: proxied, because the page only loads media from itself ----
if (isset($_GET['libpreview'])) {
    $url = $_SESSION['lib'][str($_GET['libpreview'])]['url'] ?? '';
    if (empty($_SESSION['admin']) || !$aiOn || strpos($url, 'https://storage.googleapis.com/') !== 0) {
        http_response_code(404);
        exit('Not found');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    $audio = curl_exec($ch);
    $ok = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
    curl_close($ch);
    if (!$ok) {
        http_response_code(502);
        exit('Preview unavailable');
    }
    header('Content-Type: audio/mpeg');
    header('Cache-Control: private, max-age=86400');
    echo $audio;
    exit;
}

// ---- Stored audio (players and download links) ----
if (isset($_GET['file'])) {
    $file = str($_GET['file']);
    if ($file === 'preview') {
        $path = (glob($dataDir . 'preview.*') ?: [''])[0];
    } else {
        [$fid, $fname] = array_pad(explode('/', $file, 2), 2, '');
        $path = valid_id($fid) && preg_match('/^(original|ai-[0-9a-z-]+)\.[a-z0-9]{2,4}$/', $fname) ? $dataDir . $file : '';
    }
    if (empty($_SESSION['admin']) || $path === '' || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    // Type from the extension, never sniffed from the content: an upload can never be served as a web page
    $types = ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'mp4' => 'audio/mp4', 'm4a' => 'audio/mp4',
              'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'aac' => 'audio/aac'];
    header('Content-Type: ' . ($types[pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    header('Content-Security-Policy: sandbox');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($path));
    if (isset($_GET['download'])) header('Content-Disposition: attachment; filename="' . str_replace('/', '-', $file) . '"');
    readfile($path);
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$title = ($config['application_title'] ?? 'MSP Voice Portal') . ' admin';
$page = $aiOn && ($_GET['page'] ?? '') === 'settings' ? 'settings' : 'submissions';
if ($apiOn && (($_GET['page'] ?? '') === 'api' || ($_POST['action'] ?? '') === 'api_create')) $page = 'api';
$apiKeys = [];
if ($page === 'api' && !empty($_SESSION['admin'])) {
    try {
        $apiKeys = api_keys($config, function (&$data) { return $data['keys'] ?? []; });
    } catch (Throwable $e) {
        $flash = [$e->getMessage(), false];
    }
}

// Voices and models from the account, fetched once per session
if ($aiOn && !empty($_SESSION['admin']) && $el['api_key'] !== '' && !isset($_SESSION['voices'])) {
    try {
        $_SESSION['voices'] = [];
        foreach (json_decode(elevenlabs($el, '/v2/voices?page_size=100'), true)['voices'] ?? [] as $v) {
            $_SESSION['voices'][$v['voice_id']] = ['name' => $v['name'], 'lang' => $v['labels']['language'] ?? '', 'cat' => $v['category'] ?? ''];
        }
        $_SESSION['models'] = json_decode(elevenlabs($el, '/v1/models'), true) ?: [];
    } catch (Throwable $e) {
        unset($_SESSION['voices']);
        $voiceError = $e->getMessage();
    }
}
$voices = $_SESSION['voices'] ?? [];
$models = $_SESSION['models'] ?? [];

// <option>s for a voice dropdown, voices for $lang first
function voice_options($voices, $selected, $lang = '') {
    $groups = [];
    foreach ($voices as $id => $v) {
        $groups[$v['lang'] === $lang ? 0 : ($v['lang'] === '' ? 2 : 1)][$v['lang'] ?: 'other'][$id] = $v['name'];
    }
    ksort($groups);
    if ($selected && !isset($voices[$selected])) $groups[-1]['current'][$selected] = $selected;
    $html = '';
    foreach ($groups as $byLang) {
        ksort($byLang);
        foreach ($byLang as $l => $list) {
            asort($list);
            $html .= '<optgroup label="' . h(strtoupper($l)) . '">';
            foreach ($list as $id => $name) {
                $html .= '<option value="' . h($id) . '"' . ($id === $selected ? ' selected' : '') . '>' . h($name) . '</option>';
            }
            $html .= '</optgroup>';
        }
    }
    return $html;
}

function model_options($models, $cap, $selected) {
    $html = '';
    foreach ($models as $m) {
        if (empty($m[$cap])) continue;
        $n = count($m['languages'] ?? []);
        $html .= '<option value="' . h($m['model_id']) . '"' . ($m['model_id'] === $selected ? ' selected' : '') . '>'
            . h($m['name']) . ($n > 1 ? '' : ' (English only)') . '</option>';
    }
    return $html ?: '<option value="' . h($selected) . '">' . h($selected) . '</option>';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= h($title) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/style.css', '../') ?>" rel="stylesheet">
    <style>
        /* Bootstrap parts of the admin page follow the colours from config.php too */
        body { background-color: var(--bs-darker); color: var(--bs-light); }
        .text-secondary, .form-text { color: var(--text-secondary) !important; }
        h1, h2, .h4, .h5 { color: var(--text-primary); }
        a { color: var(--primary-color); }
        a:hover { color: var(--primary-hover); }
        code { color: var(--primary-color); }
        .form-control, .form-select { background-color: var(--bg-dark); border-color: var(--border-color); color: var(--bs-light); }
        .form-control::placeholder { color: var(--text-secondary); }
        .form-select:focus { border-color: var(--bs-primary); box-shadow: 0 0 0 0.25rem color-mix(in srgb, var(--bs-primary) 25%, transparent); }
        .border-top { border-color: var(--border-color) !important; }
        .btn-outline-secondary { color: var(--text-secondary); border-color: var(--border-color); }
        .btn-outline-secondary:hover { background-color: var(--border-color); color: var(--text-primary); }
        .btn-outline-danger { color: var(--danger-color); border-color: var(--danger-color); }
        .btn-outline-danger:hover { background-color: var(--danger-color); border-color: var(--danger-color); color: #fff; }
        .alert-danger { background-color: color-mix(in srgb, var(--danger-color) 15%, var(--bg-card)); border-color: var(--danger-color); color: var(--text-primary); }
        .alert-success { background-color: color-mix(in srgb, var(--primary-color) 15%, var(--bg-card)); border-color: var(--primary-color); color: var(--text-primary); }
        .text-bg-danger { background-color: var(--danger-color) !important; }
        .text-danger { color: var(--danger-color) !important; }
        .text-bg-dark { background-color: var(--bg-dark) !important; border: 1px solid var(--border-color); }
    </style>
</head>
<body style="<?= theme_css($config) ?>">
<div class="container py-4" style="max-width: 900px;">
<?php if ($flash): ?>
    <div class="alert alert-<?= $flash[1] ? 'success' : 'danger' ?>"><?= h($flash[0]) ?></div>
<?php endif; ?>

<?php if (empty($_SESSION['admin'])): ?>
    <div class="card shadow mx-auto" style="max-width: 380px;">
        <div class="card-body p-4">
            <h1 class="h4 mb-3"><?= h($title) ?></h1>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="login">
                <label class="form-label" for="user">Username</label>
                <input class="form-control mb-3" id="user" name="user" autocomplete="username" required autofocus>
                <label class="form-label" for="password">Password</label>
                <input class="form-control mb-3" id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="btn btn-primary w-100">Sign in</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 m-0"><?= h($title) ?></h1>
            <?php if ($aiOn && $el['api_key'] !== ''): ?>
                <div class="small text-secondary">ElevenLabs key active: <code><?= h(key_hint($el['api_key'])) ?></code></div>
            <?php endif; ?>
        </div>
        <form method="post" class="d-flex align-items-center gap-2">
            <a class="btn btn-sm <?= $page === 'submissions' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="./">Submissions</a>
            <?php if ($aiOn): ?>
                <a class="btn btn-sm <?= $page === 'settings' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?page=settings">AI settings</a>
            <?php endif; ?>
            <?php if ($apiOn): ?>
                <a class="btn btn-sm <?= $page === 'api' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?page=api">API keys</a>
            <?php endif; ?>
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <button class="btn btn-sm btn-outline-secondary" name="action" value="logout">Sign out (<?= h($_SESSION['admin']) ?>)</button>
        </form>
    </div>

    <?php if (!$aiOn): ?>
    <?php elseif (!empty($voiceError)): ?>
        <div class="alert alert-warning">Could not load voices: <?= h($voiceError) ?></div>
    <?php elseif ($el['api_key'] === ''): ?>
        <div class="alert alert-warning">No ElevenLabs API key yet. Add one under <a href="?page=settings">AI settings</a>.</div>
    <?php endif; ?>

<?php if ($page === 'api'): ?>
    <div class="card shadow mb-4"><div class="card-body p-4">
        <h2 class="h5">API keys</h2>
        <p class="text-secondary">Read-only access to stored submissions and audio. Contact details are off by default.</p>
        <?php if ($createdApiKey !== null): ?>
            <div class="alert alert-success">Copy this key now. It is shown only in this response.<br>
                <code class="text-break"><?= h($createdApiKey) ?></code>
            </div>
        <?php endif; ?>
        <form method="post" action="?page=api">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <label class="form-label" for="key-name">Name</label>
            <input class="form-control mb-3" id="key-name" name="name" maxlength="100" required>
            <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="contact" value="1">
                <span class="form-check-label">Allow contact email and phone (contact scope)</span></label>
            <button class="btn btn-primary" name="action" value="api_create">Create key</button>
        </form>
    </div></div>
    <?php foreach ($apiKeys as $keyId => $key): ?>
        <div class="card mb-3"><div class="card-body">
            <h3 class="h6"><?= h($key['name']) ?></h3>
            <p class="small text-secondary">Created: <?= h($key['created']) ?><br>
                Last used: <?= h($key['last_used'] ?? 'Never') ?><br>
                Contact: <?= in_array('contact', $key['scopes'] ?? [], true) ? 'Allowed' : 'Off' ?></p>
            <?php if (!empty($key['revoked'])): ?>
                <span class="text-secondary">Revoked: <?= h($key['revoked']) ?></span>
            <?php else: ?>
                <form method="post" action="?page=api">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="key_id" value="<?= h($keyId) ?>">
                    <button class="btn btn-sm btn-outline-danger" name="action" value="api_revoke">Revoke</button>
                </form>
            <?php endif; ?>
        </div></div>
    <?php endforeach; ?>
<?php elseif ($page === 'settings'):
    $keySource = isset($saved['api_key']) ? 'saved on this page' : (!empty($config['elevenlabs']['api_key']) ? 'from config.php' : '');
    $usage = null;
    if ($el['api_key'] !== '') {
        try { $usage = json_decode(elevenlabs($el, '/v1/user/subscription'), true); } catch (Throwable $e) { $usage = ['error' => $e->getMessage()]; }
    }
?>
    <form method="post" class="card shadow mb-4">
        <div class="card-body p-4">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <h2 class="h5 mb-3">ElevenLabs account</h2>
            <label class="form-label" for="api_key">API key</label>
            <p class="mb-2">
                <?php if ($keySource): ?>
                    <span class="badge text-bg-success">Active</span> <code><?= h(key_hint($el['api_key'])) ?></code>
                    <span class="small text-secondary">(<?= h($keySource) ?>)</span>
                <?php else: ?>
                    <span class="badge text-bg-warning">No key</span>
                <?php endif; ?>
            </p>
            <input class="form-control" id="api_key" name="api_key" type="password" autocomplete="off"
                placeholder="<?= $keySource ? 'Paste a new key here to replace it' : 'Paste your key from elevenlabs.io > Developers > API keys' ?>">
            <?php if (isset($saved['api_key'])): ?>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="clear_key" value="1" id="clear_key">
                    <label class="form-check-label small" for="clear_key">Remove the key saved here<?= !empty($config['elevenlabs']['api_key']) ? ' (falls back to config.php)' : '' ?></label>
                </div>
            <?php endif; ?>
            <?php if ($usage && isset($usage['character_limit'])): ?>
                <p class="small text-secondary mt-2 mb-0">
                    Plan: <?= h($usage['tier'] ?? '?') ?> ·
                    <?= number_format((int) $usage['character_count'], 0, ',', '.') ?> of <?= number_format((int) $usage['character_limit'], 0, ',', '.') ?> credits used
                    <?php if (!empty($usage['next_character_count_reset_unix'])): ?>· resets <?= h(date('d-m-Y', $usage['next_character_count_reset_unix'])) ?><?php endif; ?>
                    <?php if (isset($usage['voice_limit'])): ?>· <?= (int) ($usage['voice_slots_used'] ?? 0) ?> of <?= (int) $usage['voice_limit'] ?> custom voice slots used<?php endif; ?>
                </p>
            <?php elseif ($usage): ?>
                <p class="small text-danger mt-2 mb-0"><?= h($usage['error']) ?></p>
            <?php endif; ?>

            <h2 class="h5 mt-4 mb-1">Voices</h2>
            <p class="small text-secondary">Default voice for submissions in each portal language. You can still pick another voice per submission.
                Need more? Browse the <a href="#library">voice library</a> below.</p>
            <?php foreach ($languages as $code => $label): ?>
                <label class="form-label" for="voice-<?= $code ?>"><?= h($label) ?> voice</label>
                <div class="d-flex gap-2 mb-3">
                    <select class="form-select" id="voice-<?= $code ?>" name="voices[<?= $code ?>]">
                        <?php if ($code !== 'en'): ?><option value="">Same as English voice</option><?php endif; ?>
                        <?= voice_options($voices, $el['voices'][$code] ?? '', $code) ?>
                    </select>
                    <button class="btn btn-outline-light text-nowrap" name="lang" value="<?= $code ?>" form="test-<?= $code ?>">Test</button>
                </div>
            <?php endforeach; ?>
            <?php if (!empty($_SESSION['preview']) && glob($dataDir . 'preview.*')): ?>
                <div class="small text-secondary">Test (<?= h($languages[$_SESSION['preview']] ?? '') ?>, saved settings)</div>
                <audio controls class="w-100 mb-3" src="?file=preview&amp;t=<?= time() ?>"></audio>
            <?php endif; ?>

            <h2 class="h5 mt-3 mb-3">Models</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="tts_model">Text to speech</label>
                    <select class="form-select" id="tts_model" name="tts_model"><?= model_options($models, 'can_do_text_to_speech', $el['tts_model']) ?></select>
                </div>
            </div>
            <p class="small text-secondary mt-2">Dutch needs a multilingual model. Multilingual v2 sounds best; Flash and Turbo are faster and use half the credits.</p>

            <h2 class="h5 mt-3 mb-3">Sound</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="output_format">Output format</label>
                    <select class="form-select" id="output_format" name="output_format">
                        <?php foreach ($formats as $id => $name): ?>
                            <option value="<?= h($id) ?>" <?= $id === $el['output_format'] ? 'selected' : '' ?>><?= h($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach ([
                    'stability' => ['Stability', 0, 1, 'Lower = more expressive, higher = more even'],
                    'similarity_boost' => ['Similarity', 0, 1, 'How closely to stick to the original voice'],
                    'style' => ['Style exaggeration', 0, 1, 'Usually best at 0'],
                    'speed' => ['Speed', 0.7, 1.2, '1 = normal'],
                ] as $f => [$label, $min, $max, $help]): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="<?= $f ?>"><?= h($label) ?> (<?= $min ?> to <?= $max ?>)</label>
                        <input class="form-control" type="number" step="0.05" min="<?= $min ?>" max="<?= $max ?>" id="<?= $f ?>" name="<?= $f ?>" value="<?= h($el[$f]) ?>">
                        <div class="form-text"><?= h($help) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button class="btn btn-primary mt-4" name="action" value="save_settings">Save settings</button>
        </div>
    </form>
    <?php foreach ($languages as $code => $_): ?>
        <form method="post" id="test-<?= $code ?>" class="d-none">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="test_voice">
        </form>
    <?php endforeach; ?>

    <?php
    // Voice library: shared community voices, searchable by language, gender and text
    $libLang = isset($languages[str($_GET['lib_lang'] ?? '')]) || ($_GET['lib_lang'] ?? null) === '' ? $_GET['lib_lang']
        : (isset($languages[$config['default_language'] ?? '']) ? $config['default_language'] : 'en');
    $libGender = in_array($_GET['lib_gender'] ?? '', ['female', 'male', 'neutral'], true) ? $_GET['lib_gender'] : '';
    $libQ = mb_substr(trim(str($_GET['lib_q'] ?? '')), 0, 60);
    $libResults = [];
    $libError = '';
    if ($el['api_key'] !== '') {
        try {
            $q = http_build_query(array_filter(['page_size' => 24, 'language' => $libLang, 'gender' => $libGender, 'search' => $libQ]));
            $libResults = json_decode(elevenlabs($el, "/v1/shared-voices?$q"), true)['voices'] ?? [];
        } catch (Throwable $e) {
            $libError = $e->getMessage();
        }
    }
    $_SESSION['lib'] = [];
    foreach ($libResults as $v) {
        $_SESSION['lib'][$v['voice_id']] = ['owner' => $v['public_owner_id'], 'url' => $v['preview_url'] ?? '', 'name' => $v['name']];
    }
    $libKeep = ['lib_lang' => $libLang, 'lib_gender' => $libGender, 'lib_q' => $libQ];
    ?>
    <div class="card shadow mb-4" id="library">
        <div class="card-body p-4">
            <h2 class="h5 mb-1">Voice library</h2>
            <p class="small text-secondary">Voices shared by the ElevenLabs community. Listen, then add the ones you like; they show up in the voice dropdowns above.</p>
            <form method="get" action="#library" class="row g-2 mb-2">
                <input type="hidden" name="page" value="settings">
                <div class="col-sm-3">
                    <select class="form-select" name="lib_lang" aria-label="Language">
                        <?php foreach ($languages + ['' => 'Any language'] as $code => $label): ?>
                            <option value="<?= h($code) ?>" <?= $code === $libLang ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3">
                    <select class="form-select" name="lib_gender" aria-label="Gender">
                        <?php foreach (['' => 'Any voice', 'female' => 'Female', 'male' => 'Male', 'neutral' => 'Neutral'] as $g => $label): ?>
                            <option value="<?= $g ?>" <?= $g === $libGender ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-4">
                    <input class="form-control" name="lib_q" value="<?= h($libQ) ?>" placeholder="Search, e.g. calm, warm, narrator" aria-label="Search">
                </div>
                <div class="col-sm-2"><button class="btn btn-outline-light w-100">Search</button></div>
            </form>
            <?php if ($libError): ?><p class="text-danger small"><?= h($libError) ?></p><?php endif; ?>
            <?php if (!$libResults && !$libError): ?><p class="text-secondary small">No voices found.</p><?php endif; ?>
            <?php foreach ($libResults as $v): ?>
                <div class="border-top py-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                        <div>
                            <strong><?= h($v['name']) ?></strong>
                            <span class="small text-secondary ms-1"><?= h(implode(' · ', array_filter([
                                $v['gender'] ?? '', str_replace('_', ' ', $v['age'] ?? ''), $v['accent'] ?? '', $v['locale'] ?? '']))) ?></span>
                        </div>
                        <?php if (isset($voices[$v['voice_id']])): ?>
                            <span class="badge text-bg-success">In your voices</span>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                <input type="hidden" name="voice_id" value="<?= h($v['voice_id']) ?>">
                                <?php foreach ($libKeep as $k => $val): ?><input type="hidden" name="<?= $k ?>" value="<?= h($val) ?>"><?php endforeach; ?>
                                <button class="btn btn-sm btn-outline-light" name="action" value="add_voice">Add to my voices</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($v['description'])): ?>
                        <div class="small text-secondary mb-1"><?= h(mb_strimwidth($v['description'], 0, 180, '…')) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($v['preview_url'])): ?>
                        <audio controls preload="none" class="w-100" src="?libpreview=<?= h(rawurlencode($v['voice_id'])) ?>"></audio>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php else:
    $dirs = glob($dataDir . '*/meta.json') ?: [];
    rsort($dirs);
    $badge = ['normal' => 'secondary', 'high' => 'warning', 'emergency' => 'danger'];
?>
    <?php if (!$dirs): ?>
        <p class="text-secondary">No submissions yet. New ones show up here after a customer submits the form
            (kept for <?= (int) ($config['admin']['retention_days'] ?? 30) ?> days).</p>
    <?php endif; ?>

    <?php foreach ($dirs as $mf):
        $m = json_decode(file_get_contents($mf), true);
        $id = basename(dirname($mf));
        $aiFiles = array_filter(glob(dirname($mf) . '/ai-*') ?: [], function ($f) { return preg_match('/\.(mp3|wav)$/', $f); });
        rsort($aiFiles);
        $text = plain($m['textContent'] ?? '');
        $lang = $m['lang'] ?? '';
        $voice = ($el['voices'][$lang] ?? '') ?: $el['voices']['en'];
    ?>
    <div class="card shadow mb-4" id="s-<?= h($id) ?>">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                <h2 class="h5 m-0"><?= h(plain($m['companyName'] ?? '?')) ?></h2>
                <div>
                    <span class="badge text-bg-<?= $badge[$m['severity'] ?? 'normal'] ?? 'secondary' ?>"><?= h($m['severity'] ?? 'normal') ?></span>
                    <span class="badge text-bg-dark"><?= h($m['inputMethod'] ?? '') ?></span>
                    <?php if ($lang): ?><span class="badge text-bg-dark"><?= h(strtoupper($lang)) ?></span><?php endif; ?>
                    <span class="text-secondary small ms-2"><?= h(date('d-m-Y H:i', strtotime($m['created_at'] ?? 'now'))) ?></span>
                </div>
            </div>
            <p class="small text-secondary mb-2">
                <?= h(plain($m['contactEmail'] ?? '')) ?><?= !empty($m['contactPhone']) ? ' · ' . h(plain($m['contactPhone'])) : '' ?>
            </p>
            <?php if (!empty($m['notes'])): ?><p class="mb-2"><strong>Notes:</strong> <?= nl2br(h(plain($m['notes']))) ?></p><?php endif; ?>
            <?php if (!empty($m['textContent'])): ?><p class="mb-2"><strong>Text:</strong> <?= nl2br(h(plain($m['textContent']))) ?></p><?php endif; ?>

            <?php if (!empty($m['audio'])): ?>
                <div class="mb-3">
                    <div class="small text-secondary">Original recording</div>
                    <audio controls preload="none" class="w-100 mb-2" src="?file=<?= h("$id/{$m['audio']}") ?>"></audio>
                    <a class="btn btn-sm btn-primary" href="?file=<?= h("$id/{$m['audio']}") ?>&amp;download=1">Download audio</a>
                </div>
            <?php endif; ?>

            <?php foreach ($aiFiles as $af): $name = basename($af); ?>
                <div class="mb-2">
                    <div class="small text-secondary">
                        AI voice (<?= strpos($name, '-swap.') !== false ? 'voice swap' : 'text' ?>, <?= h(date('d-m H:i', filemtime($af))) ?>)
                        · <a href="?file=<?= h("$id/$name") ?>&amp;download=1">Download <?= h(strtoupper(pathinfo($name, PATHINFO_EXTENSION))) ?></a>
                    </div>
                    <audio controls preload="none" class="w-100" src="?file=<?= h("$id/$name") ?>"></audio>
                </div>
            <?php endforeach; ?>

            <form method="post" class="mt-3">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="id" value="<?= h($id) ?>">
                <?php $aiHere = $aiOn && empty($m['audio']); ?>
                <?php if ($aiHere): ?>
                <textarea class="form-control mb-2" name="text" rows="3" placeholder="Text to speak"><?= h($text) ?></textarea>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php if ($aiHere): ?>
                    <select class="form-select form-select-sm w-auto" name="voice" aria-label="Voice">
                        <?= voice_options($voices, $voice, $lang) ?>
                    </select>
                    <button class="btn btn-sm btn-primary" name="action" value="tts">Generate AI voice</button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-danger ms-auto" name="action" value="delete">Delete</button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
