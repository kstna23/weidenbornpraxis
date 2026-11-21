# Changelog

All notable changes to this project are documented here.
Format: Keep a Changelog 1.1.0. Versioning: SemVer.

## [Unreleased]

## 2026-11-21 (deployed)
### Added
- Christmas closure notice with substitute practices on the start page (#41)
- Custom 404 page (`404.html`, `noindex`) (#44)
- `STATUS.md` and this changelog (#44)
### Changed
- Every page has exactly one `<h1>` with its topic; the header logo is no longer a heading (#44)
- `<meta charset>` is the first element in `<head>`; Apache sends `charset=UTF-8` (#44)
- Oversized images resized (all rasters 9.1 MB to 3.8 MB, start page about 7.7 MB to 2.6 MB); hero loads with high priority (#44)
- `.htaccess`: 301 from `http://` and `www.` to `https://weidenbornpraxis.de/`, gzip compression, cache headers (#44)
- `ueber-uns.html`: canonical URL and favicon added (#44)
- Contact form mails carry Date, Message-ID and MIME headers, CRLF line endings, quoted-printable body, `EHLO` with the sender domain (#43)
- Contact form quiz is issued and checked by the server via a session cookie (`wbp_contact`), privacy notice extended (#43)
### Fixed
- Practice phone number in the start page's structured data (JSON-LD) now matches the site and the Google profile: 069 451359
### Removed
- MFA job banner on the start page (#41)
- Expired summer closure notice (#41)
### Security
- `.env` with SMTP credentials was publicly downloadable; `.htaccess` now returns 404 for dotfiles and repo files, credentials moved outside the webroot (#42)
- Contact form validates the e-mail address (blocks `Reply-To` header injection) and limits field lengths (#43)
