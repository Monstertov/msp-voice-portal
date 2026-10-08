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
| PHP | 7.3 or newer (8.x recommended) |
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

Create the root and uploads `.htaccess` files below for your server. The repository includes `data/.htaccess`, `admin/.htaccess` and `api/.htaccess`; keep these when uploading or updating the portal.

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
RewriteRule ^config(\.example)?\.php - [F,L]
RewriteRule ^(theme\.php|LICENSE)$ - [F,L]
RewriteRule \.php/ - [F,L]
RewriteRule ^\.htaccess$ - [F,L]
RewriteRule ^test\.php$ - [F,L]
RewriteRule ^tests/ - [F,L]
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

- **Text submissions**: **Generate AI voice** reads the text aloud (edit it first if you like) in the voice you pick.
- **Recordings and uploads**: already audio, so they get a **Download audio** button and no AI voice.

Submissions are kept in `data/` for `admin.retention_days` (default 30) and are deleted automatically after that.

Both parts are optional and **off by default**. With the admin page off, `/admin/` returns 404 and submissions are only emailed, never stored. With ElevenLabs off, the admin page just lists submissions.

**1. Turn on the admin page and add a user** in `config.php` (see `config.example.php`). Passwords are stored as hashes:

```bash
php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT), PHP_EOL;'
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

Type the password and press Enter; the command prints the hash. It reads the password instead of taking it on the command line, so special characters like `$` or `)` just work. **To reset a forgotten password** or replace one, run the same command and paste the new hash over the old one.

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

## 11. REST API (optional)

A read-only REST API layer for integrations is available under `<portal url>/api/v1/`. It is **off by default**. Keeping an old `config.php` without an `api` block leaves the API disabled (404) and the portal working as before.

**1. Enable the admin page** as described above, then add this block to `config.php`:

```php
'api' => [
    'enabled' => true,
    'key_requests' => 60,
    'ip_requests' => 120,
    'time_window' => 60, // seconds
],
```

The API reads the same stored submissions as the admin page; it does not change how submissions are emailed or retained. With the admin page off, new submissions are not stored. It does not generate voices, transcribe, edit or delete submissions.

**2. Open admin → API keys**, enter a name, and choose whether to allow the `contact` scope. This scope is **off by default** and allows contact email and phone in list and detail responses. Copy the key immediately: it is shown only in the creation response and cannot be recovered later. The page lists created and last-used dates and has a Revoke button. To change a scope or replace a lost key, create a new key and revoke the old one.

Only HMAC-SHA256 hashes and a server-side pepper are stored in `data/api-keys.json`. Key use is recorded in `data/api-use.log` with timestamp and key id only. Keep `data/` blocked from the web, including a custom `storage_dir`; protect backups of this directory as well. The existing submission retention setting does not remove API keys or this log; rotate the log as needed.

**3. Use HTTPS**, recommended for every API request so Bearer keys and submission contents are encrypted in transit. Send the key in the Authorization header, never in the URL. No CORS headers are enabled by default. Rate limits apply independently per key and per connecting IP (`REMOTE_ADDR`, without trusting forwarded headers), including failed authentication in the IP limit. Behind a proxy, clients may share an IP limit unless the web server is configured to restore the client address. Limits use locked temporary files, as with the portal's file-backed throttling; use one server/shared temporary storage when running multiple PHP workers or hosts.

Example calls (replace the URL and ids; `read` keeps the key out of shell history):

```bash
read -r -s -p 'API key: ' MSP_API_KEY
curl -H "Authorization: Bearer $MSP_API_KEY" 'https://example.com/portal/api/v1/submissions?limit=20&offset=0'
curl -H "Authorization: Bearer $MSP_API_KEY" 'https://example.com/portal/api/v1/submissions?since=2026-10-08T00%3A00%3A00Z'
curl -H "Authorization: Bearer $MSP_API_KEY" 'https://example.com/portal/api/v1/submissions/20261008-120000-abcdef01'
curl -H "Authorization: Bearer $MSP_API_KEY" -o original.wav 'https://example.com/portal/api/v1/submissions/20261008-120000-abcdef01/recording'
curl -H "Authorization: Bearer $MSP_API_KEY" 'https://example.com/portal/api/v1/submissions/20261008-120000-abcdef01/audio'
curl -H "Authorization: Bearer $MSP_API_KEY" -o voice.mp3 'https://example.com/portal/api/v1/submissions/20261008-120000-abcdef01/audio/ai-20261008-120100-tts.mp3'
unset MSP_API_KEY
```

All endpoints use **GET**:

| Endpoint (under `/api/v1/`) | Response |
|---|---|
| `submissions` | `{ "submissions": [...], "next_offset": 20 }` (or null on the last page) |
| `submissions/<id>` | Submission details |
| `submissions/<id>/recording` | Original recording download (404 for text-only submissions) |
| `submissions/<id>/audio` | `{ "files": [{ "name": "ai-...mp3", "size": 1234 }] }` |
| `submissions/<id>/audio/<filename>` | Generated MP3 or WAV download |

Lists are newest first. `limit` defaults to 50 (1–100); `offset` defaults to 0. `since` accepts Unix seconds or an ISO 8601 timestamp with seconds and a timezone (URL-encode a `+` offset), and includes submissions created strictly after it. Keep `since` and `limit` the same while following `next_offset`; offsets can shift when new submissions arrive or retention removes old ones.

List entries and details contain `id`, `created`, `severity`, `language`, `input_type`, `company_name`, `notes`, `text` and `transcript` (null when absent). `contact_email` and `contact_phone` are omitted without the contact scope. Files are served with fixed audio types, `nosniff` and attachment disposition. Errors are JSON: 400 for invalid filters, 401 for authentication failure, 404 for missing resources or disabled API, 405 for other methods, 429 for a rate limit (`Retry-After` seconds), and 503 for unavailable storage.

**4. Web server rules.** Apache: keep `api/.htaccess`, enable `mod_rewrite` and `mod_headers`, and allow overrides as above. It routes `/api/v1/...` to `api/index.php`, forwards Authorization and blocks direct access to the helper. The root `data/` deny rule must remain in place.

For nginx, add these locations to the server block (adjust the PHP socket). They take precedence over the generic PHP handler. For a subfolder install, replace `/api/`, `/data/` and `/tests/` with `/portal/api/`, `/portal/data/` and `/portal/tests/`, and use `/portal/api/index.php` for the script parameters:

```nginx
location ^~ /data/ { deny all; }
location ^~ /tests/ { deny all; }
location ^~ /api/ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/api/index.php;
    fastcgi_param SCRIPT_NAME /api/index.php;
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    add_header Content-Security-Policy "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; sandbox" always;
    add_header X-Content-Type-Options nosniff always;
    add_header Cache-Control no-store always;
}
```

Verify that `/data/api-keys.json` is denied, an unauthenticated `/api/v1/submissions` returns JSON 401 when enabled, and a valid key returns JSON 200. Avoid web server logging of Authorization headers. Run the standalone checks on the server with `php tests/api_test.php`; they use temporary fixtures and no network.

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
