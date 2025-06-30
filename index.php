<?php
$config = require_once 'config.php';

// Build Permissions-Policy header from config
$permissions_policy = [];
foreach ($config['security_headers']['permissions_policy'] as $feature => $value) {
    $permissions_policy[] = $feature . '=' . $value;
}
header("Permissions-Policy: " . implode(', ', $permissions_policy));

// Build Content-Security-Policy header from config
$csp_parts = [];
foreach ($config['security_headers']['content_security_policy'] as $directive => $sources) {
    $csp_parts[] = $directive . ' ' . implode(' ', $sources);
}
// Add media-src and connect-src for recording
$csp_parts[] = "media-src 'self' blob:";
$csp_parts[] = "connect-src 'self' blob:";
header("Content-Security-Policy: " . implode('; ', $csp_parts));

// Add additional security headers from config
foreach ($config['security_headers']['additional_headers'] as $header => $value) {
    header("$header: $value");
}

// Initialize CSRF protection if enabled
if ($config['csrf']['enabled']) {
    session_start();
    if (!isset($_SESSION[$config['csrf']['token_name']])) {
        $_SESSION[$config['csrf']['token_name']] = bin2hex(random_bytes($config['csrf']['token_length']));
    }
}

// Configure error handling
ini_set('display_errors', $config['error_handling']['display_errors']);
ini_set('log_errors', $config['error_handling']['log_errors']);
ini_set('error_log', $config['error_handling']['error_log']);
error_reporting($config['error_handling']['error_reporting']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="no">
    <meta name="apple-mobile-web-app-capable" content="no">
    <meta name="application-name" content="<?php echo htmlspecialchars($config['application_title']); ?>">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($config['application_title']); ?>">
    <meta name="theme-color" content="#1a1a1a">
    <title><?php echo htmlspecialchars($config['application_title']); ?></title>
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="96x96" href="assets/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/web-app-manifest-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="assets/web-app-manifest-512x512.png">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <!-- Styles -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($config['primary_color']); ?>;
        }
    </style>
</head>
<body data-support-email="<?php echo htmlspecialchars($config['support']['email']); ?>">
    <div class="container py-2">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div id="notificationContainer"></div>
                <div class="language-switcher">
                    <button type="button" class="<?php echo $config['default_language'] === 'en' ? 'active' : ''; ?>" data-lang="en">
                        <img src="https://flagcdn.com/24x18/gb.png" alt="English" title="English">
                    </button>
                    <button type="button" class="<?php echo $config['default_language'] === 'nl' ? 'active' : ''; ?>" data-lang="nl">
                        <img src="https://flagcdn.com/24x18/nl.png" alt="Nederlands" title="Nederlands">
                    </button>
                </div>
                <div class="card shadow">
                    <div class="card-body p-4">
                        <div class="text-center logo-container">
                            <div class="logo-title-flex">
                                <img src="assets/logo-512x512.svg" alt="Company Logo" class="img-fluid logo-img">
                                <h1 class="h3 mt-3 logo-title"><?php echo htmlspecialchars($config['application_title']); ?></h1>
                            </div>
                        </div>

                        <form id="recordingForm" action="process.php" method="POST" enctype="multipart/form-data">
                            <?php if ($config['csrf']['enabled']): ?>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[$config['csrf']['token_name']]; ?>">
                            <?php endif; ?>
                            <div class="mb-4">
                                <label class="form-label" data-i18n="recordingMethod">Recording Method</label>
                                <div class="recording-method-container">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="inputMethod" id="recordAudio" value="record" checked>
                                        <label class="form-check-label" for="recordAudio">
                                            <i class="fas fa-microphone"></i> <span data-i18n="recordAudio">Record Audio</span>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="inputMethod" id="uploadAudio" value="upload">
                                        <label class="form-check-label" for="uploadAudio">
                                            <i class="fas fa-upload"></i> <span data-i18n="uploadAudio">Upload Audio</span>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="inputMethod" id="textInput" value="text">
                                        <label class="form-check-label" for="textInput">
                                            <i class="fas fa-font"></i> <span data-i18n="textInput">Text Input</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Audio Recording Section -->
                            <div id="recordingSection" class="input-section active">
                                <button type="button" class="btn btn-primary record-button" id="recordButton">
                                    <i class="fas fa-microphone"></i> <span data-i18n="startRecording">Start Recording</span>
                                </button>
                                <div class="recording-controls">
                                    <div class="waveform" id="waveform"></div>
                                    <button type="button" class="btn btn-danger" id="stopButton">
                                        <i class="fas fa-stop"></i> <span data-i18n="stopRecording">Stop Recording</span>
                                    </button>
                                </div>
                            </div>

                            <!-- File Upload Section -->
                            <div id="uploadSection" class="input-section">
                                <label for="audioFile" class="form-label" data-i18n="uploadAudioFile">Upload Audio File</label>
                                <input type="file" class="form-control" id="audioFile" name="audioFile" accept=".mp3,.wav,.mp4,.webm,.ogg,.aac,.m4a,audio/mpeg,audio/wav,audio/mp4,audio/webm,audio/ogg,audio/aac,audio/x-m4a">
                                <div class="form-text" data-i18n="supportedFormats">Supported formats: MP3, WAV, MP4, WebM, OGG, AAC, M4A</div>
                            </div>

                            <!-- Text Input Section -->
                            <div id="textSection" class="input-section">
                                <label for="textContent" class="form-label" data-i18n="enterText">Enter Text</label>
                                <textarea class="form-control" id="textContent" name="textContent" rows="5"></textarea>
                            </div>

                            <div class="mb-4">
                                <label for="companyName" class="form-label" data-i18n="companyName">Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="companyName" name="companyName" required data-i18n-placeholder="companyNamePlaceholder">
                            </div>

                            <div class="mb-4">
                                <label for="contactEmail" class="form-label" data-i18n="contactEmail">Contact Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="contactEmail" name="contactEmail" required data-i18n-placeholder="emailPlaceholder">
                            </div>

                            <div class="mb-4">
                                <label for="contactPhone" class="form-label" data-i18n="contactPhone">Contact Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contactPhone" name="contactPhone" required data-i18n-placeholder="phonePlaceholder">
                            </div>

                            <div class="mb-4">
                                <label for="notes" class="form-label" data-i18n="notes">Notes <?php if ($config['require_notes']): ?><span class="text-danger">*</span><?php endif; ?></label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" <?php if ($config['require_notes']): ?>required<?php endif; ?> data-i18n-placeholder="notesPlaceholder"></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" data-i18n="severityLevel">Severity Level</label>
                                <div class="severity-container">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="severity" id="severityNormal" value="normal" checked>
                                        <label class="form-check-label" for="severityNormal">
                                            <i class="fas fa-clock"></i> <span data-i18n="normal">Normal</span>
                                            <span class="severity-description" data-i18n="normalDescription">Handle in a few business days</span>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="severity" id="severityHigh" value="high">
                                        <label class="form-check-label" for="severityHigh">
                                            <i class="fas fa-exclamation-circle"></i> <span data-i18n="high">High</span>
                                            <span class="severity-description" data-i18n="highDescription">Must be done before tomorrow</span>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="severity" id="severityEmergency" value="emergency">
                                        <label class="form-check-label" for="severityEmergency">
                                            <i class="fas fa-bolt"></i> <span data-i18n="emergency">Emergency</span>
                                            <span class="severity-description" data-i18n="emergencyDescription">Must be done ASAP</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center">
                                <div class="disclaimer text-muted mb-3">
                                    <small>
                                        <i class="fas fa-info-circle"></i>
                                        <span data-i18n="businessHoursNote">Please note that these requests will only be handled between business hours.</span>
                                    </small>
                                    <br>
                                    <small>
                                        <i class="fas fa-envelope"></i>
                                        <span>
                                            <span data-i18n="forAssistanceContact">For assistance, contact</span> <a href="mailto:<?php echo htmlspecialchars($config['support']['email']); ?>"><?php echo htmlspecialchars($config['support']['email']); ?></a>
                                        </span>
                                    </small>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> <span data-i18n="submit">Submit</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <footer class="text-center text-muted">
                    <small><span data-i18n="developedBy">Developed by</span> <a href="https://github.com/monstertov" target="_blank" class="text-decoration-none">Monstertov</a> - <a href="https://github.com/Monstertov/msp-voice-portal" target="_blank" class="text-decoration-none">Support the project</a></small>
                </footer>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/RecordRTC/5.6.2/RecordRTC.min.js"></script>
    <script src="assets/js/languages.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html> 