<img src="assets/logo-512x512.svg" alt="MSP Voice Portal Logo" width="120" height="120">

# MSP Voice Portal

[![PHP](https://custom-icon-badges.demolab.com/badge/PHP-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Bootstrap](https://custom-icon-badges.demolab.com/badge/Bootstrap-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![JavaScript](https://custom-icon-badges.demolab.com/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![HTML5](https://custom-icon-badges.demolab.com/badge/HTML5-E34F26?logo=html5&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/HTML)
[![CSS3](https://custom-icon-badges.demolab.com/badge/CSS3-1572B6?logo=css3&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/CSS)
[![Font Awesome](https://custom-icon-badges.demolab.com/badge/Font_Awesome-339AF0?logo=fontawesome&logoColor=white)](https://fontawesome.com/)

A secure web application for MSP (Managed Service Provider) companies to collect audio recordings and text submissions from customers for their managed VoIP solutions. This portal allows customers to easily submit voice recordings, audio files, or text content related to their VoIP service requests.



## Features

- Multiple input methods (audio recording, file upload, text)
- Secure file handling and validation
- Email notifications
- Multi-language support ([View Supported Languages](#multi-language-support))
- User-friendly interface
- Mobile-responsive design
- Configurable security settings
- Rate limiting protection
- CSRF protection
- Comprehensive error handling
- Easy CSS theming

## Supported File Types

The application supports the following audio file formats:

### File Upload and Audio Recording
- **WAV** - Waveform Audio File Format
  - MIME types: `audio/wav`, `audio/wave`, `audio/x-wav`, `audio/x-pn-wav`, `audio/vnd.wave`
  - File header validation (RIFF header)
- **MP3** - MPEG Audio Layer III
  - MIME types: `audio/mpeg`, `application/octet-stream`
- **MP4** - MPEG-4 Audio
  - MIME types: `audio/mp4`
- **WebM** - Web Media Audio
  - MIME types: `audio/webm`, `video/webm`
- **OGG** - Ogg Vorbis Audio
  - MIME types: `audio/ogg`
  - File header validation (OggS header)
- **AAC** - Advanced Audio Coding
  - MIME types: `audio/aac`, `audio/aacp`
  - File header validation (ADTS header)
- **M4A** - MPEG-4 Audio (standalone)
  - MIME types: `audio/x-m4a`, `audio/mp4a-latm`
  - File header validation (ftyp header)

### File Validation
- MIME type verification using PHP's `finfo`
- File header validation for WAV, OGG, M4A, and AAC files
- File extension fallback validation
- Size limit enforcement
- Secure file naming with random prefixes

## Requirements

- PHP 7.4 or higher
- Composer for dependencies (or installed manually)
- Web server (Apache/Nginx)
- SMTP server for email functionality
- The SMTP server and e-mail receiver must be able to handle e-mail attachments of the configured sizes

## PHP Configuration

### Upload Limits
The application is configured for a maximum file size of **10MB**. To change this limit, update the following PHP settings:

#### Option 1: PHP Configuration File (php.ini)
```ini
; Set maximum file upload size
upload_max_filesize = 10M

; Set maximum POST data size (should be larger than upload_max_filesize)
post_max_size = 12M

; Set maximum number of file uploads
max_file_uploads = 5
```

#### Option 2: .htaccess File (Apache)
```apache
php_value upload_max_filesize 10M
php_value post_max_size 12M
php_value max_file_uploads 5
```

#### Option 3: Runtime Configuration
Add to your PHP script or use `ini_set()`:
```php
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '12M');
ini_set('max_file_uploads', '5');
```

### Important Notes
- `post_max_size` should always be larger than `upload_max_filesize`
- Changes to `php.ini` require a web server restart
- `.htaccess` changes take effect immediately
- Runtime configuration must be set before any file upload processing

### Recommended Settings
For most use cases, we recommend:
- **upload_max_filesize**: 10M (10MB)
- **post_max_size**: 12M (12MB)
- **max_file_uploads**: 5

After changing these settings, update the `max_file_size` value in `config.php` to match your `upload_max_filesize` setting.

## Installation

1. Clone the repository:
```bash
git clone [repository-url]
cd [repository-name]
```

2. Install dependencies:
```bash
composer install
```

3. Configure the application:
   - Copy `config.example.php` to `config.php`
   - Update the configuration settings in `config.php` with your own values

4. Set up the upload directory:
```bash
mkdir uploads
# Set appropriate permissions for your web server user
# Example for Apache (www-data):
# chmod 755 uploads
# chown www-data:www-data uploads
```

5. Configure your web server to point to the project directory

## Configuration

The `config.php` file contains all configurable options. **Do not commit your real `config.php` to version control.**

- Email settings (SMTP)
- Security headers
- File upload settings (including `max_file_size`)
- CSRF protection
- Rate limiting
- Error handling

**Important**: The `max_file_size` setting in `config.php` should match your PHP `upload_max_filesize` setting. See [PHP Configuration](#php-configuration) for details on setting upload limits.

## Customer Usage

1. Access the web interface
2. Choose input method:
   - Record audio directly
   - Upload audio file
   - Enter text
3. Fill in required information:
   - Company name
   - Contact email
   - Contact phone
   - Notes
4. Select severity level
5. Submit the form

## Contributing

We welcome contributions from the community! Feel free to contribute to this project in any of the following ways:

### Report Issues
Found a bug or have a suggestion? Please [open an issue](https://github.com/Monstertov/msp-voice-portal/issues) and let us know! We appreciate detailed bug reports and feature requests.

### Suggest Improvements
Have an idea for a new feature or improvement? We'd love to hear about it! Open an issue with the "enhancement" label and describe your suggestion.

### Submit Pull Requests
Want to contribute code? Great! Here's how:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Improve Documentation
Documentation improvements are always welcome! Whether it's fixing typos, clarifying instructions, or adding examples, every bit helps.

### Help with Translations
See [here](#multi-language-support)

### Code of Conduct
Please be respectful and constructive in all interactions. We're committed to providing a welcoming and inclusive environment for everyone.

Thank you for contributing to MSP Voice Portal! 🎉

## Multi-language Support

Currently supported languages:
- English (🇬🇧)
- Dutch (🇳🇱)

To add a new language:
1. Add translations to `assets/js/languages.js`
2. Add language flag to the language switcher in `index.php`
3. Add translations for error messages in `process.php`

Example language addition:
```javascript
// In assets/js/languages.js
const translations = {
    'fr': {
        'submit': 'Soumettre',
        'recordAudio': 'Enregistrer l\'audio',
        // ... more translations
    }
};
```

```html
<!-- In index.php language switcher -->
<button type="button" data-lang="fr">
    <img src="https://flagcdn.com/24x18/fr.png" alt="Français" title="Français">
</button>
```

## TODO List

- [ ] Get a demo up and running

### Features
- [ ] Multiple file/recording upload support
- [x] Additional file format support (OGG, M4A, etc.)
- [x] Contact support integration in error messages
- [x] Clickable mailto link for support contact in disclaimer
- [ ] Configurable primary color accent in CSS

### Technical Improvements
- [x] Add file format validation for new formats
- [ ] Implement multi-file upload handling
- [x] Add MSP support contact configuration in config.php

### Browser Testing
- [ ] iOS Chrome/Safari testing
- [ ] macOS Chrome/Safari testing
- [ ] Edge browser testing
- [ ] Cross-browser compatibility verification

## Author

Monstertov

## License

MIT License

Copyright (c) Monstertov

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.