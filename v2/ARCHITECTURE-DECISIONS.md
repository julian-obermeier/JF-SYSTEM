# Architekturentscheidungen v2

## Keine Datenmigration

JF-SYSTEM v2 ist ein vollständiger Neustart. Es gibt bewusst:

- keinen v1-Import
- keine automatische Übernahme bestehender Benutzer, Mandanten, Mitglieder oder Dienste
- keine Synchronisation zwischen v1 und v2
- keine Änderung oder Löschung der v1-Datenbank

Die v2 erhält beim Installieren eine eigene Datenbank und wird ausschließlich mit ihren eigenen, neu angelegten Daten betrieben.
