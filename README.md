# Self-Hosted EPUB-Reader (PHP 8.x & SQLite3)

Ein moderner, leichtgewichtiger und responsiver Web-Reader für EPUB-Dateien, optimiert für den Betrieb auf einem eigenen Server (z. B. auf `ebook.runready.cloud`).

## Features

- **Backend:** Natives PHP 8.x ohne externe Framework-Abhängigkeiten.
- **Datenbank:** Eine einzige SQLite3-Datei (`storage/database.sqlite`), die beim ersten Aufruf automatisch inklusive aller Tabellen und Indizes initialisiert wird.
- **Reader-Engine:** Voll integriertes **epub.js** mit lokaler Einbindung (sowie automatischer CDN-Fallback) für reaktionsschnelles, flüssiges Lesen.
- **Automatischer Lesefortschritt:**
  - Speichert bei jedem Blättern (debounced) die exakte EPUB-CFI-Position sowie den prozentualen Fortschritt (0–100 %) in SQLite.
  - Beim Wiederöffnen eines Buches springt der Reader automatisch an die zuletzt gelesene Stelle.
- **Themes & Lese-Komfort:**
  - 3 Farbschemata: **Hell (Weiß)**, **Sepia (Buchton)** und **Dunkelmodus (Dark Mode)**.
  - Dynamisch anpassbare Schriftgröße (80 % bis 180 %) und Schriftarten (Serif / Sans-Serif), persistent im `localStorage` gesichert.
  - Vollbild-Modus und ablenkungsfreie Leseansicht (Klick in die Bildschirmmitte blendet Header und Footer ein/aus).
- **Navigation:**
  - Klick/Tap an den Bildschirmrändern (links = zurück, rechts = vor).
  - Tastatur-Shortcuts (Pfeiltasten Links/Rechts, Bild-Auf/Ab, Leertaste, `f` für Vollbild, `Esc`).
  - Touch-Swipe-Gesten auf Tablets und Smartphones.
  - Ausklappbares Inhaltsverzeichnis (TOC) mit direkter Kapitelauswahl.
- **Bibliothek & Upload:**
  - Drag & Drop Upload für `.epub`-Dateien mit Live-Fortschrittsanzeige.
  - Automatisches Auslesen von Buchtitel, Autor und Cover-Bild aus dem EPUB-Archiv (OPF-Metadaten) via PHP `ZipArchive`.
  - Dynamische Filterung/Echtzeitsuche nach Titel und Autor.
  - Sortierung nach „Zuletzt gelesen“, „Neueste zuerst“, „Titel (A-Z)“ und „Autor (A-Z)“.
  - Löschfunktion mit Sicherheitsabfrage.
- **Sicherheit & Schutz:**
  - Sämtliche Buchdateien und Cover liegen geschützt in `storage/` und werden ausschließlich über authentifizierte PHP-Handler (`serve_epub.php`, `serve_cover.php`) ausgeliefert.
  - Schutz vor direktem Webzugriff auf die SQLite-Datenbank und das `storage/`-Verzeichnis via `.htaccess`.
  - CSRF-Schutz für alle Formulare und AJAX/Fetch-API-Endpunkte.
  - Passwort-Hashing nach aktuellem Standard (`password_hash(..., PASSWORD_DEFAULT)`).

---

## Verzeichnisstruktur

```
EbookReader/
├── .htaccess                 # Schutz für SQLite, Systemdateien & Options
├── index.php                 # Bibliothek & Dashboard
├── login.php                 # Login- und Initial-Setup-Seite
├── logout.php                # Abmelden
├── reader.php                # Vollbild-EPUB-Reader
├── serve_epub.php            # Authentifizierter Range-fähiger EPUB-Download/Stream
├── serve_cover.php           # Authentifizierte Cover-Auslieferung mit ETag-Caching
├── includes/
│   ├── auth.php              # Authentifizierung, Sessions & CSRF-Schutz
│   ├── db.php                # SQLite-Verbindung & automatische Migrationen
│   ├── epub_parser.php       # EPUB-Parser (ZipArchive, OPF, Metadaten & Cover)
│   └── functions.php         # Hilfsfunktionen & Formatierungen
├── api/
│   ├── upload.php            # EPUB-Upload & Metadatenextraktion
│   ├── delete.php            # Buch löschen (DB & Dateisystem)
│   └── save_progress.php     # Lesefortschritt (CFI + Prozent) speichern
├── assets/
│   ├── css/
│   │   ├── app.css           # Styling für Bibliothek, Login & Modals
│   │   └── reader.css        # Styling für den EPUB-Reader, Themes & Drawer
│   └── js/
│       ├── jszip.min.js      # JSZip (für epub.js offline)
│       ├── epub.min.js       # epub.js Bibliothek (offline)
│       ├── app.js            # Dashboard-Logik (Upload, Suche, Löschen)
│       └── reader.js         # Reader-Logik (epub.js, Gesten, Auto-Save)
├── storage/
│   ├── .htaccess             # Zugriffssperre für Apache
│   ├── database.sqlite       # Automatisch erstellte SQLite-Datenbank
│   ├── books/                # Gespeicherte EPUB-Dateien (UUID.epub)
│   └── covers/               # Extrahierte Buchcover (UUID.jpg/.png)
└── README.md
```

---

## Voraussetzungen

- **PHP 8.0 oder höher** (getestet mit PHP 8.4)
- PHP-Erweiterungen:
  - `pdo_sqlite`
  - `sqlite3`
  - `zip`
  - `session`
  - `fileinfo`
  - `libxml` / `dom`
- Webserver: Apache (mit aktiviertem `mod_rewrite` bzw. `.htaccess`-Unterstützung) oder Nginx.

---

## Installation & Ersteinrichtung

1. Laden Sie die Dateien in das Webverzeichnis (DocumentRoot) Ihrer Domain (z. B. `ebook.runready.cloud`).
2. Stellen Sie sicher, dass der Webserver-Benutzer (z. B. `www-data`) Schreibrechte im Verzeichnis `storage/` hat:
   ```bash
   chmod -R 775 storage
   chown -R www-data:www-data storage
   ```
3. Rufen Sie die Website im Browser auf. Da noch kein Benutzer in der Datenbank existiert, erscheint automatisch die **Ersteinrichtungs-Maske**:
   - Benutzername wählen (Standard: `admin`)
   - Sicheres Master-Passwort eingeben und bestätigen
   - Auf *„Bibliothek initialisieren“* klicken.
4. Sie werden sofort angemeldet und zur Bibliothek weitergeleitet. Ziehen Sie Ihre `.epub`-Dateien einfach per Drag & Drop in das Upload-Feld!

---

## Nginx-Konfiguration (Optional)

Falls Sie Nginx anstelle von Apache nutzen, blockieren Sie den direkten Zugriff auf den `storage/`-Ordner und `.sqlite`-Dateien:

```nginx
server {
    server_name ebook.runready.cloud;
    root /var/www/ebookreader;
    index index.php;

    client_max_body_size 128M;

    # Direkten Zugriff auf storage und sqlite sperren
    location ^~ /storage/ {
        deny all;
        return 404;
    }

    location ~ \.(sqlite|sqlite3|db|env|log)$ {
        deny all;
        return 404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
```

---

## Lizenz & Hinweise
Freie Nutzung für private und kommerzielle Self-Hosted-Projekte.
EPUB® ist eine eingetragene Marke des World Wide Web Consortium (W3C).
