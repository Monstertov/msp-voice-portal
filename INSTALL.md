# Installation Guide for MSP Voice Portal

This guide provides simple steps to get your MSP Voice Portal up and running. You don't need to be a web development expert to follow these instructions.

## Quick Start (TL;DR)

1. **Download** the project files from GitHub
2. **Configure** `config.php` with your SMTP and support email settings
3. **Create** an `uploads` folder and set proper permissions
4. **Access** the portal via your web browser

For detailed instructions, continue reading below.

## 1. Requirements

Before you start, make sure you have these installed on your server:

*   **PHP:** Version 7.4 or newer.
*   **Web Server:** Apache or Nginx (Plesk, cPanel, or DirectAdmin hosting environments work perfectly)
*   **SMTP Server:** For sending email notifications (e.g., Postfix, Sendmail, or an external service like SendGrid)

## 2. Get the Code

You have two options to obtain the project files:

### Option 1: Download ZIP
1. Go to the GitHub repository: [https://github.com/Monstertov/msp-voice-portal](https://github.com/Monstertov/msp-voice-portal)
2. Click the green "Code" button
3. Select "Download ZIP"
4. Extract the ZIP file to your web server's directory

### Option 2: Git Clone
```bash
git clone https://github.com/Monstertov/msp-voice-portal
cd msp-voice-portal
```

## 3. Install Dependencies

### Composer (Optional)
While Composer can be used, it's not required. You can manually download PHPMailer:

1. **Composer Method (Optional):**
   ```bash
   composer install
   ```

2. **Manual PHPMailer Download:**
   - Visit [PHPMailer GitHub Releases](https://github.com/PHPMailer/PHPMailer/releases)
   - Download the latest version
   - Extract the PHPMailer files into a `vendor/phpmailer` directory in your project

## 4. Configure the Portal

All settings are in a file called `config.php`. We provide an example file to get you started.

1.  **Copy the example file:**
    ```bash
    cp config.example.php config.php
    ```
2.  **Edit `config.php`:** Open the `config.php` file in a text editor. You'll need to update settings like:
    *   **SMTP details:** For sending emails (server, username, password, etc.).
    *   **File upload limits:** Match this with your server's PHP settings (see next step).
    *   **Security settings:** Adjust as needed.
    *   **Primary Color:** Set the hexadecimal color code for the primary accent color (e.g., `#7289da`).

    *Example snippet from `config.php` (your values will differ):*
    ```php
    <?php
    // SMTP Configuration
    define('SMTP_HOST', 'your.smtp.server.com');
    define('SMTP_PORT', 587);
    define('SMTP_USERNAME', 'your_smtp_username');
    define('SMTP_PASSWORD', 'your_smtp_password');
    define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
    define('SMTP_FROM_NAME', 'MSP Voice Portal');

    // File Upload Settings
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
    ?>
    ```

## 5. Set Up Uploads Folder

The portal needs a place to store uploaded audio files.

1.  **Create the folder:**
    ```bash
    mkdir uploads
    ```
2.  **Set permissions:** Your web server needs permission to write to this folder.
    ```bash
    chmod 755 uploads
    chown www-data:www-data uploads # Replace 'www-data:www-data' with your web server user/group (e.g., 'nginx:nginx' for Nginx)
    ```

    **For Plesk hosting:**
    ```bash
    chmod 755 uploads
    chown psaserv:psaserv uploads
    # Or if using Apache with Plesk:
    chown apache:apache uploads
    ```
    > **Note:** For detailed Plesk permission setup, see the [Plesk documentation](https://docs.plesk.com/en-US/obsidian/administrator-guide/web-hosting/web-sites-and-domains/website-permissions.73385/).

    **For DirectAdmin hosting:**
    ```bash
    chmod 755 uploads
    chown apache:apache uploads
    # Or if using a specific user account:
    chown yourusername:apache uploads
    ```
    > **Note:** For DirectAdmin-specific setup, refer to the [DirectAdmin documentation](https://www.directadmin.com/features.php?id=1838) and [DirectAdmin forums](https://forum.directadmin.com/) for permission configuration.

    **For cPanel hosting:**
    ```bash
    chmod 755 uploads
    chown yourusername:yourusername uploads
    # The web server will run as your username in cPanel
    ```
    > **Note:** For cPanel file permission setup, see the [cPanel File Manager documentation](https://kb.hosting.com/docs/file-permissions) for detailed instructions on setting permissions through the File Manager interface.

    **Note:** If you're unsure about your web server user, check your hosting provider's documentation or contact their support. The web server user is typically `www-data`, `apache`, `nginx`, or your hosting account username.

## 6. Configure Your Web Server (Apache/Nginx)

You need to tell your web server where the portal files are. This usually involves creating a Virtual Host entry (Apache) or a server block (Nginx).

*Example for Apache Virtual Host:*
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/your/msp-voice-portal

    <Directory /path/to/your/msp-voice-portal>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

## 7. Security Configuration: .htaccess

Create a `.htaccess` file in your project root with the following security-focused configuration:

```apache
# Disable directory browsing
Options -Indexes

# Protect sensitive files
<FilesMatch "^(config\.php|\.htaccess|\.env)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Block access to potentially sensitive directories
<DirectoryMatch "^.+(vendor|uploads|logs)$">
    Order Allow,Deny
    Deny from all
</DirectoryMatch>

# Prevent file upload of potentially dangerous file types
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|phps|pht|asp|aspx|exe|pl|cgi|sh|bash)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Prevent viewing of log files
<FilesMatch "\.log$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Protect against SQL injection and other attacks
RewriteEngine On
RewriteCond %{QUERY_STRING} (\<|%3C).*script.*(\>|%3E) [NC,OR]
RewriteCond %{QUERY_STRING} GLOBALS(=|\[|\%[0-9A-Z]{0,2}) [OR]
RewriteCond %{QUERY_STRING} _REQUEST(=|\[|\%[0-9A-Z]{0,2})
RewriteRule ^(.*)$ index.php [F,L]

# Prevent clickjacking
Header always append X-Frame-Options SAMEORIGIN

# Enable XSS protection in browsers
Header set X-XSS-Protection "1; mode=block"

# Prevent MIME type sniffing
Header set X-Content-Type-Options nosniff
```

## 8. Adjust PHP Upload Limits (Important!)

If you plan to allow larger file uploads, you might need to adjust your PHP settings.

*Example for `php.ini`:*
```ini
upload_max_filesize = 20M
post_max_size = 24M
```

## 9. Customizing Branding (Logo, Favicon)

To customize the portal with your MSP's branding, you can replace the default logo and favicon files located in the `assets/` directory.

*   **Logo:** Replace `assets/logo-512x512.svg` with your own SVG logo. Ensure your logo is also 512x512 pixels for optimal display.
*   **Favicon:** Replace the various `favicon.ico`, `favicon.svg`, `apple-touch-icon.png`, `favicon-96x96.png`, `web-app-manifest-192x192.png`, and `web-app-manifest-512x512.png` files with your own. It's recommended to use a favicon generator to create all necessary sizes from a single source image.

## 10. Access the Portal

Once all steps are complete, open your web browser and navigate to the domain or IP address where you configured the portal.

If you encounter any issues, check your web server's error logs and the `logs/` directory within the portal for more details.

**Note:** Always keep your software updated and follow security best practices!

## Configuring Max Recording Duration

You can set the maximum allowed duration for audio recordings (in seconds) in your `config.php`:

```php
// config.php
'recording_max_duration' => 60, // 60 seconds (default)
```

- The frontend will warn users of the limit before recording starts.
- If the limit is reached, recording will automatically stop and a warning will be shown.

## Setting Up Mail Receiver and Support Email

In your `config.php`, you can configure where form submissions are sent and what email address is shown to users for support. These can be the same or different addresses, depending on your MSP's workflow.

### Example:
```php
// config.php
'email' => [
    'to' => 'receiver@example.com', // The email address that receives form submissions
    // ... other SMTP settings ...
],

'support' => [
    'email' => 'support@example.com', // The support contact email shown to users
    'name' => 'MSP Support Team'
],
```

- **Mail Receiver (`email.to`)**: This is the address that will receive all form submissions (audio, text, etc.).
- **Support Email (`support.email`)**: This is the address shown to users in the portal for support/contact. It can be the same as the receiver, or a different address (e.g., a helpdesk or ticketing system).

**Tip:** If you want all notifications and support requests to go to the same address, set both to the same value. If you want to separate customer support from form submissions, use different addresses.

## Troubleshooting

If you encounter issues during installation, here are common problems and their solutions:

### Problem: "Upload failed" or "File upload error"

**Symptoms:**
- Users can't upload audio files
- Error messages about file uploads
- Files appear to upload but don't save

**Solutions:**
1. **Check uploads folder permissions:**
   ```bash
   ls -la uploads/
   # Should show: drwxr-xr-x (755 permissions)
   ```

2. **Verify web server user ownership:**
   ```bash
   ls -la uploads/
   # Should show your web server user (www-data, apache, etc.)
   ```

3. **Check PHP upload limits in php.ini:**
   ```ini
   upload_max_filesize = 20M
   post_max_size = 24M
   max_execution_time = 300
   ```

4. **Test with a small file first** (under 1MB) to isolate size-related issues.

### Problem: "Email not sending" or "SMTP error"

**Symptoms:**
- Form submissions don't generate email notifications
- SMTP connection errors
- Emails go to spam folder

**Solutions:**
1. **Verify SMTP settings in config.php:**
   - Check host, port, username, and password
   - Ensure SSL/TLS settings match your provider

2. **Test SMTP connection:**
   ```php
   // Add this to a test file to verify SMTP
   require 'vendor/autoload.php';
   $mail = new PHPMailer\PHPMailer\PHPMailer(true);
   $mail->isSMTP();
   $mail->Host = 'your.smtp.server.com';
   $mail->Port = 587;
   $mail->SMTPAuth = true;
   $mail->Username = 'your_username';
   $mail->Password = 'your_password';
   ```

3. **Check hosting provider's SMTP restrictions** - some hosts block external SMTP

4. **Use alternative email services** like SendGrid, Mailgun, or Gmail SMTP

### Problem: "Recording not working" or "Microphone access denied"

**Symptoms:**
- Audio recording button doesn't work
- Browser shows microphone permission errors
- Recording starts but stops immediately

**Solutions:**
1. **Ensure HTTPS is enabled** - modern browsers require secure connections for microphone access

2. **Check browser permissions:**
   - Click the camera/microphone icon in the address bar
   - Ensure microphone access is allowed

3. **Test in different browsers** - Chrome, Firefox, Safari, Edge

4. **Check for browser extensions** that might block microphone access

### Problem: "Page not loading" or "500 Internal Server Error"

**Symptoms:**
- Portal doesn't load in browser
- Server error messages
- Blank white page

**Solutions:**
1. **Check PHP version compatibility:**
   ```bash
   php -v
   # Should be 7.4 or higher
   ```

2. **Verify file permissions:**
   ```bash
   ls -la *.php
   # Should be readable by web server (644)
   ```

3. **Check web server error logs:**
   - Apache: `/var/log/apache2/error.log`
   - Nginx: `/var/log/nginx/error.log`
   - cPanel: Check error logs in hosting panel

4. **Enable error display temporarily** in config.php:
   ```php
   'error_handling' => [
       'display_errors' => true, // Temporarily enable for debugging
       'log_errors' => true,
   ]
   ```

### Problem: "Language not switching" or "Translation errors"

**Symptoms:**
- Language switcher doesn't work
- Mixed languages displayed
- Missing translations

**Solutions:**
1. **Clear browser cache** and cookies
2. **Check JavaScript console** for errors (F12 → Console)
3. **Verify language files** are properly loaded
4. **Test with different browsers** to isolate browser-specific issues

### Problem: "Security headers causing issues"

**Symptoms:**
- Mixed content warnings
- External resources not loading
- CSP (Content Security Policy) violations

**Solutions:**
1. **Review security headers** in config.php
2. **Add missing domains** to CSP directives
3. **Check browser console** for specific CSP violations
4. **Temporarily disable security headers** for testing:
   ```php
   'security_headers' => [
       'content_security_policy' => [
           'default-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
           // Add other domains as needed
       ]
   ]
   ```

### Still Having Issues?

If you're still experiencing problems:

1. **Check the logs directory** for detailed error messages
2. **Review your hosting provider's documentation** for specific requirements
3. **Contact your hosting provider's support** for server-level issues
4. **Open an issue on GitHub** with detailed error messages and your configuration (remove sensitive data)

**Remember:** Always keep your software updated and follow security best practices!