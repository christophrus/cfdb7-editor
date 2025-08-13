# CFDB7 Editor Plugin

Ein WordPress-Plugin, das das Bearbeiten von Contact Form 7 Database (CFDB7) Einträgen im Backend ermöglicht  
und optional die Einträge im Frontend über einen Shortcode ausgibt.

## Features

- Übersicht aller Formularfelder in separaten Spalten im Backend
- Bearbeiten einzelner Felder inkl. neuer Spalte **"Bezahlt"** (Checkbox)
- "Bezahlt"-Status wird auch im Frontend angezeigt
- Frontend-Shortcode `[cfdb7_data]` mit Optionen:
  - `form_id` – ID des CF7-Formulars *(Pflichtfeld)*
  - `limit` – Anzahl der Einträge *(Standard: 10)*
  - `exclude` – Felder ausschließen (kommagetrennt)
  - `headers` – benutzerdefinierte Tabellen-Header (kommagetrennt)

## Installation

1. Lade den gesamten `cfdb7-editor` Ordner in das Verzeichnis `/wp-content/plugins/` hoch.
2. Aktiviere das Plugin über das **Plugins**-Menü in WordPress.
3. Öffne im WordPress-Admin-Menü den Punkt **CFDB7 Editor**.

## Shortcode Beispiel

```plaintext
[cfdb7_data form_id="123" limit="20" exclude="email,telefon" headers="Name,Nachricht,Datum,Bezahlt"]
