# Installation Guide — MSP Voice Portal

## Quick Start

```bash
git clone https://github.com/Monstertov/msp-voice-portal
cd msp-voice-portal
composer install
cp config.example.php config.php
# Edit config.php with your SMTP and support email settings
mkdir -p uploads logs data
chmod 755 uploads logs data
```

Then configure your web server to point to the project root and ensure HTTPS is active.

---

## Requirements

| Requirement | Minimum |
|---|---|
| PHP | 8.0 or newer |
| PHP extensions | `fileinfo`, `json`, `session`, `curl` (admin AI voice) |
| Web server | Apache 2.4+ (with `mod_rewrite`, `mod_headers`) or Nginx |
| Composer | Required (manages PHPMailer) |
| HTTPS | Required — microphone access and secure session cookies need a valid TLS certificate |

---

## 1. Get the Code

**Option A — Git clone (recommended):**
```bash
git clone https://github.com/Monstertov/msp-voice-portal
cd msp-voice-portal
```

**Option B — Download ZIP:**
1. Go to the [GitHub repository](https://github.com/Monstertov/msp-voice-portal)
2. Click **Code → Download ZIP**
3. Extract to your web server directory

---

## 2. Install Dependencies

Composer is required. It installs PHPMailer and the autoloader.

```bash
composer install --no-dev
```

If Composer is not installed on your server, install it first:
```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev
```

---

## 3. Configure the Portal

Copy the example config and edit it:

```bash
cp config.example.php config.php
```

Open `config.php` and update the following sections. The file uses a PHP return-array format:

```php
<?php
return [
    'email' => [
        'smtp_host'     => 'smtp.yourprovider.com',
        'smtp_port'     => 587,          // 587 for TLS, 465 for SSL, 25 for unauthenticated relay
        'smtp_username' => 'you@example.com', // Leave empty for unauthenticated relay
        'smtp_password' => 'yourpassword',    // Leave empty for unauthenticated relay
        'smtp_secure'   => 'tls',        // 'tls', 'ssl', or '' for no encryption
        'from'          => 'noreply@yourdomain.com',
        'from_name'     => 'MSP Voice Portal',
        'to'            => 'support@yourdomain.com', // Where submissions are delivered
    ],

    'application_title' => 'MSP Voice Portal',
    'primary_color'     => '#7289da', // Hex accent colour
    'primary_hover'     => '#5b6eae', // Hover shade of the accent colour
    'default_language'  => 'nl',      // 'en' or 'nl'
    'require_notes'     => true,
    'recording_max_duration' => 60,   // Maximum recording length in seconds

    'support' => [
        'email' => 'support@yourdomain.com', // Address shown to users in the portal
        'name'  => 'MSP Support Team',
    ],

    // ... security, rate limiting, CSRF, upload settings (see config.example.php for all options)
];
```

> **Tip:** The full list of options with descriptions is in `config.example.php`.

---

## 4. Create Required Directories

```bash
mkdir -p uploads logs data
chmod 755 uploads logs data
```

Set ownership to your web server user:

```bash
# Apache on Debian/Ubuntu
chown www-data:www-data uploads logs data

# Apache on RHEL/CentOS
chown apache:apache uploads logs data

# Plesk
chown psaserv:psaserv uploads logs data

# cPanel / DirectAdmin — your account user owns the files
chown yourusername:yourusername uploads logs data
```

---

## 5. Set Up .htaccess Files

The `.htaccess` files are **not included in the repository** (they are gitignored because they contain server-specific configuration). You must create them manually.

### Project root — `/.htaccess`

Create `/path/to/msp-voice-portal/.htaccess` with the following content:

```apache
# Enable Rewrite Engine
RewriteEngine On

# --- Block access to sensitive files and directories ---
RewriteRule ^\.git - [F,L]
RewriteRule ^\.gitignore$ - [F,L]
RewriteRule ^\.gitattributes$ - [F,L]
RewriteRule ^\.vscode - [F,L]
RewriteRule ^\.env - [F,L]
RewriteRule ^composer\.lock$ - [F,L]
RewriteRule ^package\.json$ - [F,L]
RewriteRule ^package-lock\.json$ - [F,L]
RewriteRule ^logs/.*\.log$ - [F,L]
RewriteRule ^data/ - [F,L]
RewriteRule ^vendor/ - [F,L]
RewriteRule ^composer\.json$ - [F,L]
RewriteRule \.md$ - [F,L]
RewriteRule ^config\.php$ - [F,L]
RewriteRule ^\.htaccess$ - [F,L]
RewriteRule ^test\.php$ - [F,L]
RewriteRule ^reset\.php$ - [F,L]

# --- Redirect HTTP to HTTPS ---
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

# --- Security Headers ---
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; style-src-elem 'self' cdnjs.cloudflare.com; font-src 'self' cdnjs.cloudflare.com; img-src 'self' data: blob: https://flagcdn.com; media-src 'self' blob:; connect-src 'self' blob: cdnjs.cloudflare.com; object-src 'none'; form-action 'self'; base-uri 'self';"
Header always set X-Frame-Options "DENY"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
Header always set Permissions-Policy "geolocation=(), camera=(), microphone=(self)"
Header unset X-Powered-By
Header unset Server
```

### Uploads directory — `/uploads/.htaccess`

Create `/path/to/msp-voice-portal/uploads/.htaccess` to prevent execution of any uploaded scripts:

```apache
# Deny execution of server-side scripts in the uploads directory
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|pht|asp|aspx|cgi|pl|py|sh)$">
    Require all denied
</FilesMatch>
Options -ExecCGI -Indexes
```

> **Why two .htaccess files?** The root one secures the application. The uploads one provides a second line of defence: even if a malformed file somehow passes validation, Apache will refuse to execute it.

---

## 6. Configure Your Web Server

### Apache Virtual Host

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /path/to/msp-voice-portal

    <Directory /path/to/msp-voice-portal>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile    /path/to/fullchain.pem
    SSLCertificateKeyFile /path/to/privkey.pem

    ErrorLog  ${APACHE_LOG_DIR}/msp-voice-portal-error.log
    CustomLog ${APACHE_LOG_DIR}/msp-voice-portal-access.log combined
</VirtualHost>
```

Make sure `mod_rewrite` and `mod_headers` are enabled:
```bash
a2enmod rewrite headers
systemctl restart apache2
```

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name your-domain.com;
    root /path/to/msp-voice-portal;
    index index.php;

    ssl_certificate     /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Block sensitive files
    location ~* ^/(config\.php|\.htaccess|\.git|logs/|data/|vendor/) {
        deny all;
    }

    # Block script execution in uploads
    location ~* ^/uploads/.*\.(php|phtml|cgi|pl|py|sh)$ {
        deny all;
    }
}
```

---

## 7. Adjust PHP Upload Limits

The portal allows files up to 10 MB by default. Make sure your PHP settings match. Create or edit a `.user.ini` in the project root:

```ini
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 60
```

Or set these in your global `php.ini` / hosting panel.

---

## 8. Branding

Replace the default logo and favicon files in `assets/`:

| File | Purpose |
|---|---|
| `assets/logo-512x512.svg` | Main logo shown in the portal header |
| `assets/favicon.svg` | Browser tab icon (SVG) |
| `assets/favicon.ico` | Browser tab icon (legacy) |
| `assets/favicon-96x96.png` | 96×96 PNG favicon |
| `assets/apple-touch-icon.png` | iOS home screen icon (180×180) |
| `assets/web-app-manifest-192x192.png` | Android home screen icon |
| `assets/web-app-manifest-512x512.png` | Android splash icon |

Use a favicon generator to produce all sizes from a single source image.

Set your brand colour in `config.php`:
```php
'primary_color' => '#7289da', // Main accent (buttons, highlights)
'primary_hover' => '#5b6eae', // Hover state
```

---

## 9. Admin Page & AI Voice (optional)

The admin page lives at `<your portal url>/admin/` (for example `https://example.com/portal/admin/`). Admins can see recent submissions, play and download them, and turn them into a professional AI voice with [ElevenLabs](https://elevenlabs.io):

- **Generate AI voice**: reads text aloud (a typed submission, a transcript, or anything you type).
- **Transcribe**: turns a recording into editable text, which you can then generate as AI voice.
- **Voice swap**: keeps the caller's words and timing but replaces their voice.

Submissions are kept in `data/` for `admin.retention_days` (default 30) and are deleted automatically after that.

Both parts are optional and **off by default**. With the admin page off, `/admin/` returns 404 and submissions are only emailed, never stored. With ElevenLabs off, the admin page just lists submissions.

**1. Turn on the admin page and add a user** in `config.php` (see `config.example.php`). Passwords are stored as hashes:

```bash
php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), "\n";'
```

```php
'admin' => [
    'enabled' => true,
    'users' => [
        'admin' => '$2y$10$...paste the hash here...',
    ],
    'retention_days' => 30,
],
```

**2. Turn on ElevenLabs** (optional) with `'elevenlabs' => ['enabled' => true, ...]` in `config.php`, and add your API key there or on the admin **AI settings** page. The active key is shown as `sk_ab12…wxyz`, so you can see which one is in use. The AI settings page also has:

- a default voice per portal language (Dutch, English), with a Test button
- a voice library: search ElevenLabs community voices by language, gender and keyword, listen, and add them to your account
- models, voice tuning (stability, similarity, style, speed) and the output format. Pick *WAV 8 kHz* for classic phone systems.
- your credit usage and voice slots

Settings saved there go to `data/settings.json`, so updating the portal never overwrites them. Only the on/off switch has to stay in `config.php`.

**3. Make sure `data/` is not reachable from the web.** The repository ships `data/.htaccess` for Apache; on nginx add `data/` to the deny rule above. Check it: `https://your-portal/data/.htaccess` must return 403. The repository also ships `admin/.htaccess`, which gives the admin page a strict Content-Security-Policy (it runs no JavaScript).

---

## 10. Verify the Installation

1. Open your domain in a browser — the portal should load in your configured default language
2. Try recording a short audio clip and submitting the form
3. Confirm the submission email arrives
4. Open browser devtools (F12 → Console) — there should be no CSP errors or JS errors

---

## Troubleshooting

### Upload fails or file upload error

- Check `uploads/` exists and is writable by the web server user
- Verify PHP upload limits (`upload_max_filesize`, `post_max_size`) match or exceed your `config.php` `max_file_size`
- Test with a small file (under 1 MB) first
- Check `logs/process_errors.log` when `debug.enabled` is `true` in config

### Email not sending / SMTP error

- Verify `smtp_host`, `smtp_port`, `smtp_username`, and `smtp_password` in config
- Port 587 requires `smtp_secure = 'tls'`; port 465 requires `smtp_secure = 'ssl'`; port 25 unauthenticated relay requires `smtp_secure = ''` and empty credentials
- Some hosts block outbound SMTP — check with your provider or use a relay service (SendGrid, Mailgun, etc.)
- Enable `debug.enabled = true` temporarily to log SMTP errors to `logs/process_errors.log`

### Microphone / recording not working

- HTTPS is required — microphone access is blocked on plain HTTP
- Check browser permissions: click the lock icon in the address bar
- iOS Safari requires iOS 14.5+ for WebRTC recording
- Android Chrome and all modern desktop browsers are supported

### Page not loading / 500 error

- Check PHP version: `php -v` (needs 8.0+)
- Verify `vendor/` exists and PHPMailer is installed (`composer install`)
- Confirm `AllowOverride All` is set in your Apache config so `.htaccess` is read
- Check web server error log (`/var/log/apache2/error.log` or `/var/log/nginx/error.log`)
- Temporarily set `display_errors = true` in `config.php` `error_handling` section

### Language shows English despite config setting `nl`

- This happens when a `user_language` cookie from a previous visit overrides the config default
- Clear cookies for the domain and reload
- New visitors without a cookie will see the language set in `config.php` → `default_language`

### CSP violations in browser console

- Ensure the root `.htaccess` is in place and `mod_headers` is enabled
- If you load resources from additional domains (e.g. a custom font CDN), add them to the relevant CSP directives in your `.htaccess` and in `config.php` → `security_headers.content_security_policy`

### Still having issues?

1. Check `logs/process_errors.log` (enable `debug.enabled = true` in config temporarily)
2. [Open an issue on GitHub](https://github.com/Monstertov/msp-voice-portal/issues) with your error log (remove sensitive data before posting)
