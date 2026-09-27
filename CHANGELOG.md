# Changelog

All notable changes to this project will be documented in this file.

## [0.10.0-beta] - 2026-09-27

### Added
- Admin page at `/admin/`: sign in with users from `config.php`, see recent submissions with their recordings, download them, delete them
- ElevenLabs AI voice on the admin page: generate AI voice from text, transcribe recordings to editable text, and voice swap recordings
- AI settings page: API key, default voice per portal language (Dutch, English), models, voice tuning (stability, similarity, style, speed) and output format (MP3 or WAV 8/16 kHz for phone systems), plus a test button and credit usage. Saved to `data/settings.json`
- Submissions are now stored in `data/` (kept for `admin.retention_days`, default 30) in addition to being emailed, when the admin page is on
- On/off switches in `config.php`: `admin.enabled` and `elevenlabs.enabled`, both off by default. The portal works exactly as before without them
- Voice library on the AI settings page: search ElevenLabs community voices by language, gender and keyword, preview them and add them to your account
- The active API key is shown as `sk_ab12…wxyz` in the admin header and on the AI settings page

### Security
- `data/` is blocked from the web (`data/.htaccess`, root `.htaccess` rule, nginx example). Admin logins are throttled (5 failed attempts per 15 minutes per IP), unknown usernames take as long as wrong passwords, sessions end after 2 hours idle, and every admin action is CSRF protected
- Admin page has a strict Content-Security-Policy (no JavaScript) via `admin/.htaccess`
- Uploads: a file is only accepted when its content is detected as audio (or has a valid audio header); the extension-only fallback is gone, and the extension must be one of the allowed audio types
- Stored files are served to the admin with a fixed audio type and `nosniff`, so a disguised upload can never run as a web page
- Mail server errors are logged instead of shown to the customer
- Portal notifications show server messages as plain text, never as HTML
- Invalid language values no longer break the form handler
- Example config: `script-src` no longer allows `'unsafe-inline'` (the portal has no inline scripts); install docs block `vendor/`, `composer.json` and `*.md`
- Removed unused `validateFileContent()` code

### Fixed
- Works on PHP 7.3 and newer again (no PHP 8-only functions)
- Permissions-Policy header now uses valid syntax (`camera=(self)`, `geolocation=()`), whether config values are written as `'self'`, `self`, `'none'` or `()`

## [0.9.3-beta] - 2026-03-21

### Added
- Custom audio player for recorded audio: themed play/pause button, seek bar, and time display — consistent across iOS Safari, Android Chrome, Firefox, and all desktop browsers. Replaces the native `<audio controls>` element which rendered differently on every platform.

### Fixed
- CSP `connect-src` directive now includes `blob:` and `cdnjs.cloudflare.com`, resolving browser console violations when devtools fetch Bootstrap/Font Awesome source maps
- Removed duplicate `media-src` and `connect-src` directives in `index.php` that were silently discarding the intended values (CSP only uses the first occurrence of each directive)
- Default language from `config.php` now correctly applies on first page load instead of always defaulting to English
- Audio recording on iOS Safari and other mobile browsers now works correctly — MIME type is detected at runtime (`audio/mp4` on iOS, `audio/webm` on Android/desktop) instead of being hardcoded to WAV
- Recorded audio is uploaded with the correct file extension matching its actual format, fixing server-side validation failures on mobile recordings
- Removed duplicate `submissionSuccess` translation key in English locale

### Security
- Blocked direct web access to `test.php` and `reset.php` via `.htaccess`
- Added `.htaccess` to `uploads/` directory to prevent execution of server-side scripts on uploaded files

### Changed
- Example support email in `config.example.php` replaced with a generic placeholder

## [0.9.2-beta] - 2025-07-01

### Added
- Primary accent color is now configurable via `config.php`
- Drag & drop file upload functionality
- Support email dynamically loaded from configuration using PHP
- Mailto functionality works in both English and Dutch languages
- Remember company name/email from previous submissions using sessions.
- Play back recordings before submission.

### Changed
- Updated support contact display to show actual email address instead of generic "support" text
- Enhanced user experience with intuitive drag & drop interface
- Improved file upload workflow with automatic method selection
- Minor bug fixes (mobile UI, recording upload, UI/UX bugs)
- UI/UX changes (added timer to recording)

## [0.9.1-beta] - 2025-06-30

### Added
- Support for additional audio formats:
  - **OGG** (.ogg) - Ogg Vorbis audio format
  - **AAC** (.aac) - Advanced Audio Coding format
  - **M4A** (.m4a) - MPEG-4 Audio standalone format
- Enhanced file validation with header checking for new formats
- Updated file input accept attributes to include new formats
- Updated error messages to reflect new supported formats

### Changed
- Updated configuration to include new MIME types and extensions
- Enhanced file validation logic to support OGG, AAC, and M4A headers
- Updated documentation to reflect new supported formats

## [0.9.0-beta] - 2025-06-18

### Added
- Initial public beta release of MSP Voice Portal
- Multiple input methods (audio recording, file upload, text)
- Multi-language support (English and Dutch)
- Secure file handling and validation
- Email notifications
- Rate limiting protection
- CSRF protection
- Comprehensive error handling

### Changed
- N/A