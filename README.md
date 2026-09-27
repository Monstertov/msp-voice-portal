<img src="assets/logo-512x512.svg" alt="MSP Voice Portal Logo" width="120" height="120">

# MSP Voice Portal

[![PHP](https://custom-icon-badges.demolab.com/badge/PHP-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Bootstrap](https://custom-icon-badges.demolab.com/badge/Bootstrap-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![JavaScript](https://custom-icon-badges.demolab.com/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![HTML5](https://custom-icon-badges.demolab.com/badge/HTML5-E34F26?logo=html5&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/HTML)
[![CSS3](https://custom-icon-badges.demolab.com/badge/CSS3-1572B6?logo=css3&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/CSS)
[![Font Awesome](https://custom-icon-badges.demolab.com/badge/Font_Awesome-339AF0?logo=fontawesome&logoColor=white)](https://fontawesome.com/)

A secure, mobile-friendly web portal for IT Managed Service Providers to collect audio recordings and text submissions from customers for VoIP service requests. An optional admin page turns those submissions into professional AI voice recordings with [ElevenLabs](https://elevenlabs.io), ready for your phone system.

<details>
  <summary>Preview</summary>
  <img src="https://tov.monster/host/mspvoiceportal.png" alt="MSP Voice Portal Preview" width="100%" />
</details>

## Features

- Multiple input methods: record audio, upload file (MP3, WAV, MP4, WebM, OGG, AAC, M4A), or enter text
- Works on iOS, Android, and all modern desktop browsers
- Configurable max recording duration
- Email notifications via SMTP
- Multi-language support (English & Dutch)
- Severity levels: Normal, High, Emergency
- CSRF protection, rate limiting, and secure file validation
- Easy branding via config: title, logo, every colour (portal and admin page), and which input method opens first
- Optional admin page (`/admin/`) to review, play, download and delete submissions
- Optional ElevenLabs AI voice: text to speech, transcription and voice swap, with Dutch and English voices, a searchable voice library, and MP3 or phone-ready WAV output

## Installation

See [INSTALL.md](INSTALL.md) for full setup instructions.

## Customer Usage

1. Choose an input method — record, upload, or type
2. Fill in company name, contact email, phone, and notes
3. Select a severity level
4. Submit

## Admin & AI Voice

Both are optional and off by default: the portal works on its own, emailing every submission as before.

- **Admin page** (`'admin' => ['enabled' => true]` in `config.php`): open `<portal url>/admin/` and sign in with a user from `config.php`. Every submission is listed with its details and recording, kept for 30 days by default. Each notification email links straight to the submission there.
- **ElevenLabs AI voice** (`'elevenlabs' => ['enabled' => true]`, needs the admin page): per submission, one click for **Generate AI voice** (text to speech), **Transcribe** (recording to editable text) or **Voice swap** (same words, AI voice). The **AI settings** page holds the API key (shown as `sk_ab12…wxyz` so you can see which key is active), a default voice per language (Dutch, English), models, voice tuning, output format (MP3, or WAV 8/16 kHz for phone systems), credit usage, and a searchable ElevenLabs voice library with previews.

Setup: [INSTALL.md](INSTALL.md#9-admin-page--ai-voice-optional).

## Contributing

Contributions are welcome. [Open an issue](https://github.com/Monstertov/msp-voice-portal/issues) for bugs, feature requests, or improvements. To add a language, add translations to `assets/js/languages.js`, a flag button to `index.php`, and error message translations to `process.php`.

## TODO

### Features
- [ ] Multiple file/recording upload support
- [x] Additional file format support (OGG, M4A, etc.)
- [x] Contact support integration in error messages
- [x] Clickable mailto link for support contact in disclaimer
- [x] Drag & drop upload
- [x] Configurable primary color accent in config
- [x] Remember company name/email from previous submissions
- [x] Play back recordings before submission
- [x] ElevenLabs integration
- [x] Custom styled audio playback

### Technical
- [x] Configurable max recording duration with warnings
- [x] iOS and cross-browser audio recording compatibility
- [x] CSP headers properly configured
- [x] Add MSP support contact configuration in config.php
- [ ] Multiple file upload with split emails
- [ ] File compression

## Author

[Monstertov](https://github.com/monstertov)

## License

[MIT](LICENSE)
