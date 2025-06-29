<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = require_once 'config.php';

// Set secure headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to generate CSRF token
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to validate CSRF token
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to check rate limiting
function check_rate_limit($ip) {
    $rate_limit_file = sys_get_temp_dir() . '/rate_limit_' . md5($ip);
    $time_window = 3600; // 1 hour
    $max_requests = 10; // Maximum requests per hour

    if (file_exists($rate_limit_file)) {
        $data = json_decode(file_get_contents($rate_limit_file), true);
        if (time() - $data['time'] < $time_window) {
            if ($data['count'] >= $max_requests) {
                throw new Exception('Rate limit exceeded. Please try again later.');
            }
            $data['count']++;
        } else {
            $data = ['time' => time(), 'count' => 1];
        }
    } else {
        $data = ['time' => time(), 'count' => 1];
    }
    file_put_contents($rate_limit_file, json_encode($data));
}

// Function to sanitize input
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Function to validate email
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && 
           preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
}

// Function to log errors with detailed information
function log_error($message, $context = []) {
    global $config;
    
    // Check if debugging is enabled
    if (!$config['debug']['enabled']) {
        return;
    }
    
    $log_file = $config['debug']['log_file'];
    $log_dir = dirname($log_file);
    
    // Create log directory if it doesn't exist
    if (!file_exists($log_dir)) {
        if (!mkdir($log_dir, 0755, true)) {
            error_log("Failed to create log directory: " . $log_dir);
            return;
        }
    }
    
    // Check if directory is writable
    if (!is_writable($log_dir)) {
        error_log("Log directory is not writable: " . $log_dir);
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $request_id = uniqid('req_', true);
    
    $log_entry = [
        'timestamp' => $timestamp,
        'request_id' => $request_id,
        'ip' => $ip,
        'message' => $message,
        'context' => $context,
        'request_data' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]
    ];
    
    $log_message = json_encode($log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // Create file if it doesn't exist and set permissions
    if (!file_exists($log_file)) {
        touch($log_file);
        chmod($log_file, 0644);
    }
    
    // Check if file is writable
    if (!is_writable($log_file)) {
        error_log("Log file is not writable: " . $log_file);
        return;
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Function to validate file
function validate_file($file) {
    $allowed_types = [
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/mp4' => 'mp4',
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'application/octet-stream' => 'mp3', // Add this for some browsers
        'audio/x-pn-wav' => 'wav', // Additional WAV MIME type
        'audio/vnd.wave' => 'wav', // Another WAV MIME type
        'audio/wave; codecs="1"' => 'wav', // WAV with codec specification
        'audio/wave; codecs=1' => 'wav', // WAV with codec specification (no quotes)
        'audio/ogg' => 'ogg', // OGG Vorbis
        'audio/aac' => 'aac', // AAC
        'audio/x-m4a' => 'm4a', // M4A
        'audio/mp4a-latm' => 'm4a', // Alternative M4A MIME type
        'audio/aacp' => 'aac' // Alternative AAC MIME type
    ];
    
    // Log file validation attempt
    log_error("Validating file", [
        'file_name' => $file['name'],
        'file_size' => $file['size'],
        'file_type' => $file['type']
    ]);
    
    // Get MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    log_error("Detected MIME type", ['mime_type' => $mime_type]);
    
    // Check if the MIME type is in our allowed types
    if (array_key_exists($mime_type, $allowed_types)) {
        log_error("MIME type is allowed", ['mime_type' => $mime_type]);
        return true;
    }
    
    // Special handling for WAV files - check file header
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension === 'wav') {
        // Read first 4 bytes to check for WAV header
        $file_header = file_get_contents($file['tmp_name'], false, null, 0, 4);
        if ($file_header === 'RIFF') {
            log_error("WAV file detected by header", ['header' => bin2hex($file_header)]);
            return true;
        }
        log_error("WAV file with invalid header", ['header' => bin2hex($file_header)]);
    }
    
    // Special handling for OGG files - check file header
    if ($extension === 'ogg') {
        // Read first 4 bytes to check for OGG header
        $file_header = file_get_contents($file['tmp_name'], false, null, 0, 4);
        if ($file_header === 'OggS') {
            log_error("OGG file detected by header", ['header' => bin2hex($file_header)]);
            return true;
        }
        log_error("OGG file with invalid header", ['header' => bin2hex($file_header)]);
    }
    
    // Special handling for M4A files - check file header
    if ($extension === 'm4a') {
        // Read first 8 bytes to check for M4A header (should contain 'ftyp')
        $file_header = file_get_contents($file['tmp_name'], false, null, 0, 8);
        if (strpos($file_header, 'ftyp') !== false) {
            log_error("M4A file detected by header", ['header' => bin2hex($file_header)]);
            return true;
        }
        log_error("M4A file with invalid header", ['header' => bin2hex($file_header)]);
    }
    
    // Special handling for AAC files - check file header
    if ($extension === 'aac') {
        // AAC files can have different headers, check for ADTS header
        $file_header = file_get_contents($file['tmp_name'], false, null, 0, 2);
        $header_hex = bin2hex($file_header);
        // Check for ADTS sync word (0xFFF)
        if (substr($header_hex, 0, 3) === 'fff') {
            log_error("AAC file detected by ADTS header", ['header' => $header_hex]);
            return true;
        }
        log_error("AAC file with invalid header", ['header' => $header_hex]);
    }
    
    // If MIME type not found, check file extension as fallback
    $valid_extensions = ['mp3', 'wav', 'mp4', 'webm', 'ogg', 'aac', 'm4a'];
    
    log_error("Checking file extension", ['extension' => $extension]);
    
    if (in_array($extension, $valid_extensions)) {
        log_error("File extension is valid", ['extension' => $extension]);
        return true;
    }
    
    log_error("Invalid file type", [
        'mime_type' => $mime_type,
        'extension' => $extension
    ]);
    return false;
}

// Function to generate email subject
function generate_email_subject($severity, $company_name) {
    $severity_prefix = [
        'normal' => '[NORMAL]',
        'high' => '[HIGH]',
        'emergency' => '[EMERGENCY]'
    ][$severity] ?? '[NORMAL]';
    
    return $severity_prefix . ' MSP Voice Portal Request - ' . $company_name;
}

// Function to send email
function send_email($config, $data, $attachment_path = null) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['email']['smtp_host'];
        $mail->Port = $config['email']['smtp_port'];
        
        // Only set authentication if username is provided
        if (!empty($config['email']['smtp_username'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $config['email']['smtp_username'];
            $mail->Password = $config['email']['smtp_password'];
            $mail->SMTPSecure = $config['email']['smtp_secure'];
        }

        // Recipients
        $mail->setFrom($config['email']['from'], $config['email']['from_name']);
        $mail->addAddress($config['email']['to']);

        // Attach file if exists
        if ($attachment_path && file_exists($attachment_path)) {
            log_error("Attempting to attach file", [
                'path' => $attachment_path,
                'exists' => file_exists($attachment_path),
                'size' => filesize($attachment_path),
                'readable' => is_readable($attachment_path)
            ]);
            
            try {
                $mail->addAttachment(
                    $attachment_path,
                    basename($attachment_path),
                    PHPMailer::ENCODING_BASE64,
                    'audio/wav'
                );
                log_error("File attachment added successfully", [
                    'path' => $attachment_path
                ]);
            } catch (Exception $e) {
                log_error("Failed to add attachment", [
                    'path' => $attachment_path,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            log_error("No attachment path provided or file does not exist", [
                'path' => $attachment_path,
                'exists' => $attachment_path ? file_exists($attachment_path) : 'no path'
            ]);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = generate_email_subject($_POST['severity'], $data['companyName']);
        
        // Build email body
        $body = "<h2>New MSP Voice Portal Recording Submission</h2>";
        $body .= "<p><strong>Input Method:</strong> " . ucfirst($data['inputMethod']) . "</p>";
        $body .= "<p><strong>Company Name:</strong> " . $data['companyName'] . "</p>";
        $body .= "<p><strong>Contact Email:</strong> " . $data['contactEmail'] . "</p>";
        if (!empty($data['contactPhone'])) {
            $body .= "<p><strong>Contact Phone:</strong> " . $data['contactPhone'] . "</p>";
        }
        $body .= "<p><strong>Notes:</strong> " . nl2br($data['notes']) . "</p>";
        
        if (!empty($data['textContent'])) {
            $body .= "<h3>Text Content:</h3>";
            $body .= "<p>" . nl2br($data['textContent']) . "</p>";
        }

        if ($attachment_path && file_exists($attachment_path)) {
            $body .= "<p><strong>Audio Recording:</strong> Attached to this email</p>";
        }

        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        log_error("Email sent successfully", [
            'to' => $config['email']['to'],
            'subject' => $mail->Subject
        ]);
        return true;
    } catch (Exception $e) {
        log_error("Email sending failed", [
            'error' => $mail->ErrorInfo,
            'to' => $config['email']['to'],
            'subject' => $mail->Subject
        ]);
        throw new Exception("Email sending failed: " . $mail->ErrorInfo);
    }
}

// Process the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            log_error("Invalid CSRF token", [
                'token' => $_POST['csrf_token'] ?? 'not set'
            ]);
            throw new Exception('Invalid security token');
        }

        // Check rate limiting
        check_rate_limit($_SERVER['REMOTE_ADDR']);

        // Get current language from POST, cookie, or config default
        $currentLang = $_POST['lang'] ?? $_COOKIE['user_language'] ?? $config['default_language'];

        // Define translations
        $translations = [
            'en' => [
                'company_email_required' => 'Company name and contact email are required.',
                'invalid_email' => 'Invalid email address.',
                'invalid_file_type' => 'Invalid file type. Allowed types are: MP3, WAV, MP4, WebM, OGG, AAC, M4A. Detected type: ',
                'file_size_exceeded' => 'File size exceeds the maximum limit.',
                'file_save_failed' => 'Failed to save the uploaded file.',
                'audio_file_required' => 'An audio file is required for this recording method.',
                'upload_error' => 'File upload failed. Please try again or use a smaller file.',
                'upload_size_error' => 'File is too large. Maximum size is 10MB. Please use a smaller file.',
                'submission_success' => 'Your submission has been received successfully.',
                'submission_failed' => 'Failed to send the submission. Please try again later.'
            ],
            'nl' => [
                'company_email_required' => 'Bedrijfsnaam en contact e-mail zijn verplicht.',
                'invalid_email' => 'Ongeldig e-mailadres.',
                'invalid_file_type' => 'Ongeldig bestandstype. Toegestane types zijn: MP3, WAV, MP4, WebM, OGG, AAC, M4A. Gedetecteerd type: ',
                'file_size_exceeded' => 'Bestandsgrootte overschrijdt de maximale limiet.',
                'file_save_failed' => 'Het opslaan van het geüploade bestand is mislukt.',
                'audio_file_required' => 'Een audiobestand is vereist voor deze opnamemethode.',
                'upload_error' => 'Bestandsupload mislukt. Probeer het opnieuw of gebruik een kleiner bestand.',
                'upload_size_error' => 'Bestand is te groot. Maximale grootte is 10MB. Gebruik een kleiner bestand.',
                'submission_success' => 'Uw inzending is succesvol ontvangen.',
                'submission_failed' => 'Het verzenden van de inzending is mislukt. Probeer het later opnieuw.'
            ]
        ];

        // Validate required fields
        if (empty($_POST['companyName']) || empty($_POST['contactEmail'])) {
            log_error("Missing required fields", [
                'company_name' => $_POST['companyName'] ?? 'not set',
                'contact_email' => $_POST['contactEmail'] ?? 'not set'
            ]);
            throw new Exception($translations[$currentLang]['company_email_required']);
        }

        if (!is_valid_email($_POST['contactEmail'])) {
            log_error("Invalid email address", [
                'email' => $_POST['contactEmail']
            ]);
            throw new Exception($translations[$currentLang]['invalid_email']);
        }

        // Prepare data with enhanced sanitization
        $data = [
            'inputMethod' => sanitize_input($_POST['inputMethod'] ?? ''),
            'companyName' => sanitize_input($_POST['companyName']),
            'contactEmail' => sanitize_input($_POST['contactEmail']),
            'contactPhone' => sanitize_input($_POST['contactPhone'] ?? ''),
            'notes' => sanitize_input($_POST['notes'] ?? ''),
            'textContent' => sanitize_input($_POST['textContent'] ?? ''),
            'email_subject' => sanitize_input($_POST['email_subject'] ?? 'MSP Voice Portal Request')
        ];

        $attachment_path = null;
        $inputMethod = $_POST['inputMethod'] ?? '';

        // Handle file upload with enhanced security
        if (isset($_FILES['audioFile']) && $_FILES['audioFile']['error'] === UPLOAD_ERR_OK) {
            log_error("File upload detected", [
                'file_info' => $_FILES['audioFile'],
                'input_method' => $inputMethod
            ]);
            
            $upload_dir = $config['upload']['upload_dir'];
            
            // Create upload directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    log_error("Failed to create upload directory", [
                        'path' => $upload_dir
                    ]);
                    throw new Exception('Failed to create upload directory');
                }
            }

            $file_info = $_FILES['audioFile'];
            
            // Validate file
            if (!validate_file($file_info)) {
                log_error("Invalid file type", [
                    'file' => $file_info
                ]);
                throw new Exception($translations[$currentLang]['invalid_file_type']);
            }

            // Validate file size
            if ($file_info['size'] > $config['upload']['max_file_size']) {
                log_error("File size exceeded", [
                    'size' => $file_info['size'],
                    'max_size' => $config['upload']['max_file_size']
                ]);
                throw new Exception($translations[$currentLang]['file_size_exceeded']);
            }

            // Generate secure filename
            $filename = bin2hex(random_bytes(16)) . '_' . time() . '.' . pathinfo($file_info['name'], PATHINFO_EXTENSION);
            $attachment_path = $upload_dir . $filename;

            log_error("Attempting to save file", [
                'source' => $file_info['tmp_name'],
                'destination' => $attachment_path,
                'filename' => $filename
            ]);

            // Move uploaded file with additional security checks
            if (!is_uploaded_file($file_info['tmp_name'])) {
                log_error("Invalid upload attempt", [
                    'file' => $file_info
                ]);
                throw new Exception('Invalid upload attempt');
            }

            if (!move_uploaded_file($file_info['tmp_name'], $attachment_path)) {
                log_error("Failed to save uploaded file", [
                    'source' => $file_info['tmp_name'],
                    'destination' => $attachment_path
                ]);
                throw new Exception($translations[$currentLang]['file_save_failed']);
            }

            // Set proper file permissions
            chmod($attachment_path, 0644);
            
            log_error("File saved successfully", [
                'path' => $attachment_path,
                'size' => filesize($attachment_path),
                'exists' => file_exists($attachment_path)
            ]);
        } else {
            log_error("No file upload or upload error", [
                'files_set' => isset($_FILES['audioFile']),
                'upload_error' => $_FILES['audioFile']['error'] ?? 'not set',
                'input_method' => $inputMethod
            ]);
            
            // Check if audio file is required for this input method
            if (in_array($inputMethod, ['upload', 'record'])) {
                if (!isset($_FILES['audioFile'])) {
                    // No file was uploaded
                    throw new Exception($translations[$currentLang]['audio_file_required']);
                } else {
                    // File upload had an error
                    $upload_error = $_FILES['audioFile']['error'];
                    $error_messages = [
                        UPLOAD_ERR_INI_SIZE => 'File size exceeds PHP upload limit.',
                        UPLOAD_ERR_FORM_SIZE => 'File size exceeds form upload limit.',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
                    ];
                    
                    $error_message = $error_messages[$upload_error] ?? 'Unknown upload error.';
                    log_error("Upload error details", [
                        'error_code' => $upload_error,
                        'error_message' => $error_message
                    ]);
                    
                    // Provide specific error for size issues
                    if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
                        throw new Exception($translations[$currentLang]['upload_size_error']);
                    } else {
                        throw new Exception($translations[$currentLang]['upload_error']);
                    }
                }
            }
        }

        // Send email
        if (send_email($config, $data, $attachment_path)) {
            $response['success'] = true;
            $response['message'] = $translations[$currentLang]['submission_success'];
            
            // Clean up attachment after successful email send
            if ($attachment_path && file_exists($attachment_path)) {
                log_error("Cleaning up attachment file", [
                    'path' => $attachment_path
                ]);
                unlink($attachment_path);
            }
        } else {
            throw new Exception($translations[$currentLang]['submission_failed']);
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        log_error("Form submission error", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Clean up any uploaded file in case of error
        if (isset($attachment_path) && file_exists($attachment_path)) {
            unlink($attachment_path);
        }
    }

    echo json_encode($response);
    exit;
}

// If not POST request, redirect to index
header('Location: index.php');
exit;

// Add file content validation
function validateFileContent($file_path) {
    global $config;
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);
    
    // Add more thorough content validation
    if (!in_array($mime_type, $config['upload']['allowed_types'])) {
        return false;
    }
    
    // Check file headers
    $file_header = file_get_contents($file_path, false, null, 0, 8);
    $valid_headers = [
        'audio/mpeg' => "\xFF\xFB",
        'audio/wav' => "RIFF",
        'audio/mp4' => "ftyp",
        'audio/webm' => "\x1A\x45\xDF\xA3",
        'audio/ogg' => "OggS",
        'audio/aac' => "\xFF\xF1", // ADTS AAC
        'audio/x-m4a' => "ftyp"
    ];
    
    foreach ($valid_headers as $mime => $header) {
        if ($mime_type === $mime && strpos($file_header, $header) === 0) {
            return true;
        }
    }
    
    return false;
} 