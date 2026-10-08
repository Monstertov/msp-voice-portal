<?php
// Example configuration for MSP Voice Portal
return [
    'email' => [
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'your_smtp_username', // Can be empty for no authentication
        'smtp_password' => 'your_smtp_password', // Can be empty for no authentication
        'smtp_secure' => 'tls', // Options: 'tls', 'ssl', or '' for no authentication
        'from' => 'noreply@example.com',
        'from_name' => 'Your Company Name',
        'to' => 'support@example.com'
    ],

    // Debug Configuration WARNING: Logs will leak backend information
    'debug' => [
        'enabled' => false,
        'log_file' => __DIR__ . '/logs/process_errors.log',
        'log_level' => 'error' // Options: 'error', 'warning', 'info', 'debug'
    ],

    // Application Settings
    'application_title' => 'MSP Voice Portal',
    // Colours of the portal and the admin page. Any CSS colour: '#7289da', 'white', 'rgb(0, 0, 0)'.
    // Leave a line out to keep its default. (Older configs with 'primary_color' and
    // 'primary_hover' at this level still work.)
    'colors' => [
        'primary'       => '#7289da', // buttons, links, accents
        'primary_hover' => '#5b6eae', // hover/active shade of primary
        'background'    => '#141414', // page background
        'card'          => '#2d2d2d', // cards and panels
        'field'         => '#1a1a1a', // input fields and dark areas
        'text'          => '#f8f9fa', // body text
        'heading'       => '#ffffff', // headings and strong text
        'muted'         => '#b9bbbe', // labels and secondary text
        'border'        => '#404040',
        'focus'         => '#0d6efd', // outline of the text field being typed in
        'danger'        => '#ed4245', // stop/delete buttons and errors
        'danger_hover'  => '#c03537',
    ],
    'require_notes' => true,
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'default_language' => 'en',
    'default_input_method' => 'record', // option open when the page loads: 'record', 'upload' or 'text'

    // MSP Support Contact Configuration
    'support' => [
        'email' => 'support@example.com', // MSP support contact email
        'name' => 'MSP Support Team' // MSP support team name
    ],

    // Security Headers
    'security_headers' => [
        'content_security_policy' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", 'cdnjs.cloudflare.com'],
            'style-src' => ["'self'", "'unsafe-inline'", 'cdnjs.cloudflare.com'],
            'style-src-elem' => ["'self'", "'unsafe-inline'", 'cdnjs.cloudflare.com'],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'", 'cdnjs.cloudflare.com'],
            'connect-src' => ["'self'", 'blob:', 'cdnjs.cloudflare.com'],
            'media-src' => ["'self'", 'blob:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"]
        ],
        'permissions_policy' => [
            'camera' => "'self'",
            'microphone' => "'self'",
            'geolocation' => "'none'",
            'payment' => "'none'",
            'usb' => "'none'"
        ],
        'additional_headers' => [
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
        ]
    ],

    // Rate Limiting
    'rate_limit' => [
        'enabled' => true,
        'max_requests' => 10,
        'time_window' => 3600 // 1 hour
    ],

    // CSRF Protection
    'csrf' => [
        'enabled' => true,
        'token_name' => 'csrf_token',
        'token_length' => 32
    ],

    // File Upload Settings
    'upload' => [
        'upload_dir' => __DIR__ . '/uploads/',
        'allowed_types' => [
            'audio/mpeg',
            'audio/wav',
            'audio/wave',
            'audio/x-wav',
            'audio/mp4',
            'audio/webm',
            'video/webm',
            'audio/ogg',
            'audio/aac',
            'audio/x-m4a'
        ],
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_extensions' => ['mp3', 'wav', 'mp4', 'webm', 'ogg', 'aac', 'm4a']
    ],

    // Error Handling
    'error_handling' => [
        'display_errors' => false,
        'log_errors' => true,
        'error_log' => __DIR__ . '/logs/error.log',
        'error_reporting' => E_ALL
    ],

    // Maximum recording duration in seconds (frontend enforced)
    // Example: 60 for 1 minute, 180 for 3 minutes
    'recording_max_duration' => 60,

    // Admin page (<portal url>/admin/): users as 'username' => password hash, never the password itself.
    // Set, reset or replace a password: on the server run
    //     php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT), PHP_EOL;'
    // type the new password, press Enter, and paste the output ($2y$...) between the quotes
    // after the username. Add a line for another user, delete a line to remove one.
    // Off: /admin/ does not exist and submissions are only emailed, never stored.
    'admin' => [
        'enabled' => false,
        'users' => [
            // 'admin' => '$2y$10$...',
        ],
        'retention_days' => 30, // stored submissions are deleted after this many days
        // Emails link straight to the submission on the admin page. The address is worked out
        // automatically; set it here only if that goes wrong (e.g. behind an unusual proxy):
        // 'url' => 'https://example.com/portal/admin/',
    ],

    // A REST API layer for integrations (/api/v1/). Off or missing: returns 404.
    // Enable the admin page too: it stores submissions and has the API keys page.
    // Keys are shown once; hashes are kept in data/api-keys.json. Contact scope is off by default.
    'api' => [
        'enabled' => false,
        'key_requests' => 60, // requests per key per time window
        'ip_requests' => 120, // requests per IP, including failed authentication
        'time_window' => 60,  // seconds; limits are independent of portal submission limits
    ],

    // Where submissions and AI voice files are kept. Must not be reachable from the web
    // (data/.htaccess blocks it on Apache; see INSTALL.md for nginx).
    'storage_dir' => __DIR__ . '/data/',

    // ElevenLabs AI voice on the admin page (needs the admin page on). These are defaults:
    // everything except 'enabled' can be changed on the admin "AI settings" page, which
    // saves to data/settings.json. Off: the admin page only lists submissions.
    'elevenlabs' => [
        'enabled' => false,
        'api_key' => '', // elevenlabs.io > Developers > API keys
        'voices' => [
            'en' => 'EXAVITQu4vr4xnSDxMaL', // Sarah, a standard ElevenLabs voice
            'nl' => '',                     // empty = use the English voice (it speaks Dutch too)
        ],
        'tts_model' => 'eleven_multilingual_v2',
        'sts_model' => 'eleven_multilingual_sts_v2',
        'stt_model' => 'scribe_v1',
        'output_format' => 'mp3_44100_128', // or pcm_8000 / pcm_16000 for WAV (phone systems)
    ],
]; 