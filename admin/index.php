<?php
// Admin page: sign in, see stored submissions, make AI voice versions with ElevenLabs.
// Admin users live in config.php. AI settings: defaults from config.php, changes made on the
// settings page are saved in data/settings.json (never in git, survives updates).
$config = require dirname(__DIR__) . '/config.php';
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
$sttModels = ['scribe_v1' => 'Scribe v1'];
$defaults = [
    'api_key' => '',
    'voices' => ['nl' => '', 'en' => 'EXAVITQu4vr4xnSDxMaL'],
    'tts_model' => 'eleven_multilingual_v2',
    'sts_model' => 'eleven_multilingual_sts_v2',
    'stt_model' => 'scribe_v1',
    'output_format' => 'mp3_44100_128',
    'stability' => 0.5,
    'similarity_boost' => 0.75,
    'style' => 0.0,
    'speed' => 1.0,
];
$saved = is_file($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];
$el = array_replace_recursive($defaults, $config['elevenlabs'] ?? [], $saved);

// Security headers from config, as on the portal itself
foreach ($config['security_headers']['permissions_policy'] ?? [] as $feature => $value) {
    $pp[] = "$feature=$value";
}
if (!empty($pp)) header('Permissions-Policy: ' . implode(', ', $pp));
foreach ($config['security_headers']['content_security_policy'] ?? [] as $directive => $sources) {
    $csp[] = $directive . ' ' . implode(' ', $sources);
}
if (!empty($csp)) header('Content-Security-Policy: ' . implode('; ', $csp));
foreach ($config['security_headers']['additional_headers'] ?? [] as $name => $value) {
    header("$name: $value");
}
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (!$users) {
    http_response_code(503);
    exit('Admin is not configured. Add the admin section from config.example.php to config.php.');
}

session_name('msp_admin');
session_start(['cookie_httponly' => true, 'cookie_secure' => true, 'cookie_samesite' => 'Strict']);
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
// meta.json holds the values as process.php stored them (already HTML-escaped), so decode before use
function plain($s) { return html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8'); }
function back($to = './') { header('Location: ' . $to); exit; }
function flash($msg, $ok = true) { $_SESSION['flash'] = [$msg, $ok]; }
function valid_id($id) { return is_string($id) && preg_match('/^\d{8}-\d{6}-[0-9a-f]{8}$/', $id); }
function valid_voice($v) { return is_string($v) && preg_match('/^[A-Za-z0-9]{10,40}$/', $v); }

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

// ---- POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        } elseif (isset($users[$user]) && password_verify((string) ($_POST['password'] ?? ''), $users[$user])) {
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
        foreach (['tts_model', 'sts_model', 'stt_model'] as $f) {
            if (preg_match('/^[a-z0-9_]{3,60}$/', (string) ($_POST[$f] ?? ''))) $new[$f] = $_POST[$f];
        }
        if (isset($formats[$_POST['output_format'] ?? ''])) $new['output_format'] = $_POST['output_format'];
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
        $code = isset($languages[$_POST['lang'] ?? '']) ? $_POST['lang'] : 'en';
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
    $original = !empty($meta['audio']) ? $dataDir . $id . '/' . $meta['audio'] : null;
    $out = $dataDir . $id . '/ai-' . date('Ymd-His');

    try {
        if ($action === 'delete') {
            array_map('unlink', glob($dataDir . $id . '/*'));
            rmdir($dataDir . $id);
            flash('Submission deleted.');
            back();
        }
        if ($el['api_key'] === '') throw new RuntimeException('No ElevenLabs API key yet. Add one under AI settings.');
        if ($action === 'tts') {
            $text = trim((string) ($_POST['text'] ?? ''));
            if ($text === '') throw new RuntimeException('Type or transcribe some text first.');
            if (mb_strlen($text) > 5000) throw new RuntimeException('Text is longer than 5000 characters.');
            save_audio($el, "$out-tts", tts($el, $voice, $text));
            flash('AI voice ready.');
        } elseif ($action === 'swap' && $original) {
            $audio = elevenlabs($el, "/v1/speech-to-speech/$voice?output_format=" . urlencode($el['output_format']), [
                'audio' => new CURLFile($original),
                'model_id' => $el['sts_model'],
                'voice_settings' => json_encode(voice_settings($el)),
            ], true);
            save_audio($el, "$out-swap", $audio);
            flash('Voice swap ready.');
        } elseif ($action === 'transcribe' && $original) {
            $fields = ['file' => new CURLFile($original), 'model_id' => $el['stt_model']];
            if (isset($languages[$meta['lang'] ?? ''])) $fields['language_code'] = $meta['lang'];
            $res = json_decode(elevenlabs($el, '/v1/speech-to-text', $fields, true), true);
            $meta['transcript'] = trim($res['text'] ?? '');
            file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            flash('Transcribed. Check the text, then click Generate AI voice.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), false);
    }
    back("./#s-$id");
}

// ---- Stored audio (players and download links) ----
if (isset($_GET['file'])) {
    $file = (string) $_GET['file'];
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
    header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    if (isset($_GET['download'])) header('Content-Disposition: attachment; filename="' . str_replace('/', '-', $file) . '"');
    readfile($path);
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$title = ($config['application_title'] ?? 'MSP Voice Portal') . ' admin';
$page = ($_GET['page'] ?? '') === 'settings' ? 'settings' : 'submissions';

// Voices and models from the account, fetched once per session
if (!empty($_SESSION['admin']) && $el['api_key'] !== '' && !isset($_SESSION['voices'])) {
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
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body style="--primary-color: <?= h($config['primary_color'] ?? '#7289da') ?>; --primary-hover: <?= h($config['primary_hover'] ?? '#5b6eae') ?>;">
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
        <h1 class="h4 m-0"><?= h($title) ?></h1>
        <form method="post" class="d-flex align-items-center gap-2">
            <a class="btn btn-sm <?= $page === 'submissions' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="./">Submissions</a>
            <a class="btn btn-sm <?= $page === 'settings' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?page=settings">AI settings</a>
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <button class="btn btn-sm btn-outline-secondary" name="action" value="logout">Sign out (<?= h($_SESSION['admin']) ?>)</button>
        </form>
    </div>

    <?php if (!empty($voiceError)): ?>
        <div class="alert alert-warning">Could not load voices: <?= h($voiceError) ?></div>
    <?php elseif ($el['api_key'] === ''): ?>
        <div class="alert alert-warning">No ElevenLabs API key yet. Add one under <a href="?page=settings">AI settings</a>.</div>
    <?php endif; ?>

<?php if ($page === 'settings'):
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
            <input class="form-control" id="api_key" name="api_key" type="password" autocomplete="off"
                placeholder="<?= $keySource ? h('Key ending in ' . substr($el['api_key'], -4) . " ($keySource). Paste a new one to replace it.") : 'Paste your key from elevenlabs.io > Developers > API keys' ?>">
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
                </p>
            <?php elseif ($usage): ?>
                <p class="small text-danger mt-2 mb-0"><?= h($usage['error']) ?></p>
            <?php endif; ?>

            <h2 class="h5 mt-4 mb-1">Voices</h2>
            <p class="small text-secondary">Default voice for submissions in each portal language. You can still pick another voice per submission.
                More voices: add them to <em>My Voices</em> in ElevenLabs, then sign out and in again.</p>
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
                <div class="col-md-4">
                    <label class="form-label" for="tts_model">Text to speech</label>
                    <select class="form-select" id="tts_model" name="tts_model"><?= model_options($models, 'can_do_text_to_speech', $el['tts_model']) ?></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sts_model">Voice swap</label>
                    <select class="form-select" id="sts_model" name="sts_model"><?= model_options($models, 'can_do_voice_conversion', $el['sts_model']) ?></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="stt_model">Transcribe</label>
                    <select class="form-select" id="stt_model" name="stt_model">
                        <?php foreach ($sttModels + [$el['stt_model'] => $el['stt_model']] as $id => $name): ?>
                            <option value="<?= h($id) ?>" <?= $id === $el['stt_model'] ? 'selected' : '' ?>><?= h($name) ?></option>
                        <?php endforeach; ?>
                    </select>
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
        $aiFiles = array_filter(glob(dirname($mf) . '/ai-*') ?: [], fn($f) => preg_match('/\.(mp3|wav)$/', $f));
        rsort($aiFiles);
        $text = plain($m['transcript'] ?? $m['textContent'] ?? '');
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
                    <audio controls preload="none" class="w-100" src="?file=<?= h("$id/{$m['audio']}") ?>"></audio>
                </div>
            <?php endif; ?>

            <?php foreach ($aiFiles as $af): $name = basename($af); ?>
                <div class="mb-2">
                    <div class="small text-secondary">
                        AI voice (<?= str_contains($name, '-swap.') ? 'voice swap' : 'text' ?>, <?= h(date('d-m H:i', filemtime($af))) ?>)
                        · <a href="?file=<?= h("$id/$name") ?>&amp;download=1">Download <?= h(strtoupper(pathinfo($name, PATHINFO_EXTENSION))) ?></a>
                    </div>
                    <audio controls preload="none" class="w-100" src="?file=<?= h("$id/$name") ?>"></audio>
                </div>
            <?php endforeach; ?>

            <form method="post" class="mt-3">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="id" value="<?= h($id) ?>">
                <textarea class="form-control mb-2" name="text" rows="3"
                    placeholder="<?= !empty($m['audio']) ? 'Click Transcribe to fill this from the recording, or type the text' : 'Text to speak' ?>"><?= h($text) ?></textarea>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <select class="form-select form-select-sm w-auto" name="voice" aria-label="Voice">
                        <?= voice_options($voices, $voice, $lang) ?>
                    </select>
                    <button class="btn btn-sm btn-primary" name="action" value="tts">Generate AI voice</button>
                    <?php if (!empty($m['audio'])): ?>
                        <button class="btn btn-sm btn-outline-light" name="action" value="transcribe">Transcribe</button>
                        <button class="btn btn-sm btn-outline-light" name="action" value="swap">Voice swap</button>
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
