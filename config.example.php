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
    'require_notes' => true,
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'default_language' => 'en',

    // Security Headers
    'security_headers' => [
        'content_security_policy' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", 'cdnjs.cloudflare.com'],
            'style-src' => ["'self'", "'unsafe-inline'", 'cdnjs.cloudflare.com'],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'", 'cdnjs.cloudflare.com'],
            'connect-src' => ["'self'"],
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
    ]
]; 