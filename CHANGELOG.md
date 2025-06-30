# Changelog

All notable changes to this project will be documented in this file.

## [0.9.2-beta] - 2025-06-30

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