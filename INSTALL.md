# Installation Guide for MSP Voice Portal

This guide provides simple steps to get your MSP Voice Portal up and running. You don't need to be a web development expert to follow these instructions.

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