# STATUS: weidenbornpraxis

Stand: 2026-11-21 · Branch: main · Zustand: pausiert

## Next
- [ ] Postfach-Passwort `info@weidenbornpraxis.de` bei IONOS ändern und das neue in `/weidenbornpraxis.env` eintragen (Webspace-Explorer, Hauptverzeichnis `/`, nicht `htdocs`), danach Testnachricht über `https://weidenbornpraxis.de/kontakt.html`.
- [ ] Fehlende Fotos beschaffen und nach `/htdocs/images` hochladen: `daniela-meissner.jpg`, `stefanie-weck.jpg` (aerzte.html), `praxis-innen.jpg` (praxis.html), `team-gruppe.jpg` (team.html). Live 404. Vor dem Upload auf max. 1600 px verkleinern.
- [ ] `sameAs` im JSON-LD von `index.html` auf den Teilen-Link des Google-Unternehmensprofils setzen (statt der Maps-Suche).
- [ ] Weihnachtsbox in `index.html` nach dem 04.01.2027 entfernen.

## Blockiert / Offene Fragen
- **Q:** Landet die Testmail vom 21.11.2026 (Betreff "Neue Kontaktaufnahme Homepage") im Posteingang oder im Spam? Entscheidet, ob ein Versanddienst mit eigener Signatur nötig ist.

## Entscheidungen
- Deploy manuell über den IONOS Webspace-Explorer. Webroot `/htdocs` (absolut `/home/www/htdocs`), SMTP-Zugangsdaten in `/weidenbornpraxis.env` außerhalb des Webroots. Nur geänderte Website-Dateien aus `main` hochladen, keine Repo-Dateien (`*.md`, `package.json`, `.env.example`). `.htaccess` bei Änderungen zuletzt hochladen und sofort `curl -sI https://weidenbornpraxis.de/` (200) sowie `http://` und `www.` (301) prüfen.
- `main` ist der Live-Stand. Nicht direkt im Webspace-Explorer editieren.
- IONOS-CDN ist aktiv; bisher wurde jede Änderung sofort ausgeliefert, kein Cache-Leeren nötig.
- Rechenaufgabe des Kontaktformulars kommt vom Server (PHP-Session), nicht aus dem Browser.

## Zuletzt erledigt
- 2026-11-21: #44 SEO deployt (eine h1 pro Seite, 301 auf https ohne www ohne Schleife hinter dem CDN, gzip, Cache-Header, Bilder 9,1 auf 3,8 MB, Startseite 2,6 MB, eigene 404-Seite). Telefonnummer im JSON-LD korrigiert.
- 2026-11-21: #41 Weihnachtsbox, MFA-Banner entfernt; #42 öffentlich abrufbare `.env` gesperrt und außerhalb des Webroots; #43 standardkonforme Formular-Mails, serverseitige Rechenaufgabe. Deployt, Live-Test des Formulars erfolgreich.
