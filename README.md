# JF-SYSTEM.de

Professionelle, mandantenfähige Webanwendung zur Verwaltung von Jugendfeuerwehren.

## Funktionsumfang

- Mandantenfähige Organisationen
- Sicherer Login und rollenbasierter Zugriff
- Dashboard mit Kennzahlen und nächstem Dienst
- Erweiterte Mitgliederakten mit Anschrift, Schule, Größen und Notfallangaben
- Sorgeberechtigte, Abholberechtigungen und Einwilligungsverwaltung
- Geschützte Dokumentenablage in der Mitgliederakte (PDF, Bilder und Office-Dateien)
- Dienstvorlagen und CSV-Exporte für Mitglieder, Dienste und Anwesenheit
- Dashboard-Hinweise für Geburtstage und auslaufende Einwilligungen
- Dienst- und Übungsplanung mit Leitung, Lernzielen, Material und Terminserien
- Zu-/Absagen, Rückmeldefristen und Anwesenheitserfassung
- Qualifikationen
- Benutzer- und Grundeinstellungen
- Audit-Protokoll
- Responsives Feuerwehr-Design
- Browserbasierter Online-Installer ohne SSH, Docker oder Composer

## Voraussetzungen

- PHP 8.1 oder neuer
- MySQL 8.0 oder MariaDB 10.5 oder neuer
- PHP-Erweiterungen: PDO, pdo_mysql, mbstring, json
- Schreibrechte für `config/` und `storage/`

## Installation

1. Alle Dateien in das Webverzeichnis hochladen.
2. Im Browser `/install/` öffnen.
3. Datenbankzugang, Organisation und Administratorkonto eintragen.
4. Installation abschließen und anmelden.
5. Den Ordner `install/` nach erfolgreicher Einrichtung löschen oder sperren.

## Update einer bestehenden Installation

1. Vorher Datenbank und Dateien sichern.
2. Aktualisierte Dateien hochladen.
3. Als Administrator `/update/` aufrufen.
4. Ausstehende Migrationen **002/003** unter `/update/` ausführen.
5. Den Ordner `update/` anschließend sperren oder löschen.

Die Anwendung benötigt weder Composer noch Node.js und ist für übliches Shared Hosting geeignet.

## Sicherheit

- Passwort-Hashes mit PHP `password_hash()`
- PDO Prepared Statements
- CSRF-Schutz
- Mandantenfilter für sämtliche Fachdaten
- Sichere Session-Cookies
- Login-Drosselung
- Audit-Protokoll
- Installer-Sperrdatei

## Support

Support wird über OBERMEIER IT bereitgestellt: https://ticket.obermeier-it.de

## Status

Aktueller Entwicklungsstand: **v0.3.0 – Dokumente, Vorlagen & Exporte**

Diese Fassung ist eine produktionsnahe Grundlage. Vor einem Echtbetrieb mit personenbezogenen Daten sind Datenschutzkonzept, Berechtigungsmatrix, Backups, Mailversand und ein Hosting-Sicherheitsaudit zu vervollständigen.
