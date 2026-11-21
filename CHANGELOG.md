# Changelog

All notable changes to this project are documented here.
Format: Keep a Changelog 1.1.0. Versioning: SemVer.

## [Unreleased]
### Added
- Custom 404 page (`404.html`, `noindex`)
- `STATUS.md` and this changelog
### Changed
- Every page has exactly one `<h1>` with its topic; the header logo is no longer a heading
- `<meta charset>` is the first element in `<head>`; Apache sends `charset=UTF-8`
- Oversized images resized (hero 2.2 MB to 0.5 MB, start page about 7.7 MB to 2.4 MB); hero loads with high priority
- `.htaccess`: 301 from `http://` and `www.` to `https://weidenbornpraxis.de/`, gzip compression, cache headers
- `ueber-uns.html`: canonical URL and favicon added

## 2026-11-21
### Added
- Christmas closure notice with substitute practices on the start page (#41)
### Changed
- Contact form mails carry Date, Message-ID and MIME headers, CRLF line endings, quoted-printable body, `EHLO` with the sender domain (#43)
- Contact form quiz is issued and checked by the server via a session cookie (`wbp_contact`), privacy notice extended (#43)
### Removed
- MFA job banner on the start page (#41)
- Expired summer closure notice (#41)
### Security
- `.env` with SMTP credentials was publicly downloadable; `.htaccess` now returns 404 for dotfiles and repo files, credentials moved outside the webroot (#42)
- Contact form validates the e-mail address (blocks `Reply-To` header injection) and limits field lengths (#43)
