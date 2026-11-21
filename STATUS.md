# STATUS: weidenbornpraxis

Stand: 2026-11-21 · Branch: feat/seo-quick-wins · Zustand: in Arbeit

## Next
- [ ] SEO-PR (`feat/seo-quick-wins`) reviewen, mergen, auf IONOS deployen und direkt danach prüfen: `curl -sI http://weidenbornpraxis.de/` und `curl -sI https://www.weidenbornpraxis.de/` müssen 301 auf `https://weidenbornpraxis.de/` liefern, `curl -sI https://weidenbornpraxis.de/` muss 200 liefern (keine Umleitungsschleife hinter dem IONOS-CDN). Bei Schleife: vorherige `.htaccess` aus `main` wieder hochladen.
- [ ] Postfach-Passwort `info@weidenbornpraxis.de` bei IONOS ändern und in `/weidenbornpraxis.env` (Webspace-Root, nicht `htdocs`) eintragen, danach Testnachricht über `kontakt.html` senden.
- [ ] Fehlende Fotos liefern lassen: `images/daniela-meissner.jpg`, `images/stefanie-weck.jpg` (aerzte.html), `images/praxis-innen.jpg` (praxis.html), `images/team-gruppe.jpg` (team.html). Live 404.
- [ ] Weihnachtsbox in `index.html` nach dem 04.01.2027 entfernen.

## Blockiert / Offene Fragen
- **Q:** Landet die Testmail vom 21.11.2026 (Betreff "Neue Kontaktaufnahme Homepage") im Posteingang oder im Spam? Entscheidet, ob ein Versanddienst mit eigener Signatur nötig ist.
- **Q:** Echte URL des Google-Unternehmensprofils für `sameAs` im JSON-LD (`index.html`)?

## Entscheidungen
- Deploy manuell über den IONOS Webspace-Explorer: Webroot `/htdocs`, Konfiguration mit SMTP-Zugangsdaten in `/weidenbornpraxis.env` außerhalb des Webroots. Nur Website-Dateien hochladen, keine Repo-Dateien (`README.md`, `package.json`, `.env.example`).
- `main` ist der Live-Stand. Änderungen am Server nur aus `main`, nicht direkt im Webspace-Explorer bearbeiten.
- Rechenaufgabe des Kontaktformulars kommt vom Server (PHP-Session), nicht aus dem Browser.

## Zuletzt erledigt
- 2026-11-21: #41 Weihnachtsbox, MFA-Banner entfernt; #42 `.env` war öffentlich abrufbar, jetzt per `.htaccess` gesperrt und außerhalb des Webroots; #43 standardkonforme Formular-Mails und serverseitige Rechenaufgabe. Alles deployt, Live-Test des Formulars erfolgreich.
