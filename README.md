# WEIDENBORNpraxis – Kontakt-Backend Hinweise

Dieses Repository enthält statische Seiten sowie einen serverseitigen Handler `api/contact` für den Versand über den IONOS-SMTP-Server. Die folgenden Hinweise beantworten die häufigsten Fragen aus dem Betrieb.

## .env ausfüllen
Lege eine `.env` Datei (nicht einchecken) mit den IONOS-Zugangsdaten an:

```
IONOS_USER=info@weidenbornpraxis.de   # Dein IONOS-Mailbenutzer
IONOS_PASS=<dein-Ionos-Passwort>      # Starkes Passwort bzw. App-Passwort
IONOS_FROM="WEIDENBORNpraxis <info@weidenbornpraxis.de>"  # Absender, wie im Postfach erlaubt
IONOS_TO=info@weidenbornpraxis.de     # Zieladresse für Kontaktmails
```

Die Werte müssen mit deinem Postfach bei IONOS übereinstimmen. `IONOS_FROM` sollte exakt dem Absender entsprechen, den IONOS zulässt (meist die Postfachadresse). Bewahre die `.env` Datei nur lokal oder in Secret-Stores deiner Hosting-Plattform auf.

## Hosting & Sicherheit
* **Serverloses Backend erforderlich:** GitHub Pages liefert nur statische Dateien. Der Fetch-Aufruf des Kontaktformulars (`/api/contact`) braucht deshalb ein Hosting-Anbieter, der Node.js-Funktionen mit geheimen Umgebungsvariablen bereitstellt (z. B. Vercel, Netlify Functions oder ein eigener Server).
* **HTTPS erzwingen:** Browser deaktivieren Auto-Fill und markieren Formulare als „nicht sicher“, wenn die Seite oder das Formularziel nicht über HTTPS ausgeliefert wird. Stelle sicher, dass deine Domain ein gültiges TLS-Zertifikat nutzt und dass das Backend unter `https://` erreichbar ist. Bei GitHub Pages mit Custom Domain muss HTTPS im Domain-Setup aktiviert werden.
* **Secrets geschützt halten:** Lege keine `.env` Inhalte ins Git-Repo. Nutze die Secret-Verwaltung deiner Plattform, um `IONOS_USER`, `IONOS_PASS`, `IONOS_FROM` und `IONOS_TO` zu hinterlegen.

## Lokaler Test
Für lokale Tests kannst du die `.env` setzen und die Funktion mit einem Node-Server (z. B. `vercel dev` oder `netlify dev`) laufen lassen. Prüfe im Terminal, dass die Umgebungsvariablen geladen werden, bevor du das Formular im Browser aufrufst.
