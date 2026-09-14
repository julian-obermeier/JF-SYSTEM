# JF-SYSTEM v2

JF-SYSTEM v2 ist der vollständige, vom produktiven v1-System getrennte Neuaufbau.

## Leitplanken

- PHP 8.1+ ohne Composer oder Docker
- MySQL/MariaDB in Produktion
- `/public` ist der einzige Document-Root
- browserbasierte Installation unter `/install/`
- gemeinsame Datenbank mit verpflichtender Mandantentrennung
- globale Benutzerkonten mit Zuordnung zu mehreren Mandanten
- serverseitige Rollen- und Rechteprüfung
- zentrale URL- und Asset-Erzeugung
- keine Übernahme oder Veränderung produktiver v1-Daten während der Entwicklung

## Lokaler Start

Für die Entwicklung kann SQLite verwendet werden. Die produktive Installation verwendet MySQL/MariaDB.

```bash
cp config/config.example.php config/config.php
php tools/seed-demo.php
php -S 127.0.0.1:8088 -t public public/router.php
```

Demo-Zugang:

- E-Mail: `julian@example.test`
- Passwort: `Demo123!`

## Struktur

```text
app/          Anwendungs-, HTTP-, Sicherheits- und Mandantenlogik
config/       lokale Konfiguration (nicht versioniert)
database/     Schema und spätere Migrationen
public/       einziger öffentlich erreichbarer Ordner
resources/    PHP-Views
storage/      Logs, Uploads, Cache und lokale Entwicklungsdaten
tests/        automatisierte Sicherheits- und Funktionstests
tools/        Entwicklungswerkzeuge
```

## Status

Phase 1 enthält den neuen Kern, Login, Mandantenauswahl, Dashboard, Mitgliederverwaltung und Dienstanlage. Weitere Module werden in eigenständigen Ausbaustufen ergänzt.
