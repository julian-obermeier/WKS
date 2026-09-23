# WKS – webbasiertes Wach- und Sicherheitsdienst-System

WKS ist eine serverseitig gerenderte interne Betriebsanwendung für einen Sicherheitsdienst mit mehreren Standorten. Der aktuelle integrierte Entwicklungsstand ist **0.8.0-alpha**.

Die Kernmodule sind Dienstbuch, Sonderberichte, Wertsachen und Hausverbote. Ergänzt werden sie durch Benutzer/Rollen/Rechte, Standorttrennung, Dashboard, Mitteilungen, Benachrichtigungen, Suche, Statistik, Audit-Log, Papierkorb, PWA, Systemstatus, Cronjobs, Fehlerprotokoll, Dokumentvorlagen und ein GitHub-basiertes Update-System.

## Voraussetzungen

- PHP **8.1 oder neuer**; für den produktiven Betrieb wird PHP 8.4 empfohlen.
- MySQL 8.x oder eine kompatible aktuelle MariaDB-Version.
- PHP-Erweiterungen: `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `session`, `zip` / `ZipArchive`.
- `curl` wird für das Update-System empfohlen; alternativ muss `allow_url_fopen` für ausgehende HTTPS-Lesezugriffe verfügbar sein.
- Apache mit `mod_rewrite` ist die vorgesehene Shared-Hosting-Konfiguration. Vergleichbare Rewrite-Regeln sind auf anderen Webservern möglich.
- HTTPS ist im Produktivbetrieb erforderlich.

Es gibt **keinen Node.js- oder Frontend-Build-Schritt** im produktiven Betrieb.

## Verzeichnisstruktur und Document Root

Der Webserver muss auf das Verzeichnis **`public/`** zeigen. Dadurch befinden sich Uploads, Logs, generierte Dokumente, Konfiguration und PHP-Anwendungscode außerhalb des direkt erreichbaren Webroots.

Beispiel:

```
/www/htdocs/<account>/WKS/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/          <- Document Root der Domain
├── resources/
├── routes/
├── storage/
└── .env
```

## Installation

1. Repository auf den Webspace holen:

   ```bash
   git clone https://github.com/julian-obermeier/WKS.git
   cd WKS
   ```

2. Konfiguration anlegen:

   ```bash
   cp .env.example .env
   ```

3. In `.env` mindestens `APP_URL`, Datenbankzugang und Session-Einstellungen setzen. Für Produktivbetrieb:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://wks.example.de
   APP_TIMEZONE=Europe/Berlin

   DB_HOST=...
   DB_PORT=3306
   DB_DATABASE=...
   DB_USERNAME=...
   DB_PASSWORD=...

   SESSION_SECURE=true
   SESSION_SAMESITE=Lax
   ```

4. Die Domain auf `WKS/public/` zeigen lassen.

5. Folgende Verzeichnisse müssen für den PHP-Prozess beschreibbar sein:

   - `storage/uploads`
   - `storage/logs`
   - `storage/exports`
   - `storage/generated`
   - `storage/sessions`

   Auf Shared Hosting sind üblicherweise `770` für Verzeichnisse und `660`/Serverstandard für Dateien ausreichend. Keine pauschalen `777`-Rechte verwenden, wenn dies nicht zwingend erforderlich ist.

6. Im Browser `/install` aufrufen. Die Erstinstallation führt die ausstehenden Migrationen aus und legt den ersten Administrator an.

## Erstkonfiguration

Die Migrationen bereiten unter anderem vor:

- Standorte **Gießen** und **Marburg**
- Standort-Mailadressen:
  - Gießen: `Security.gi@uk-gm.de`
  - Marburg: `Security.mr@uk-gm.de`
- Mailmodus standardmäßig **deaktiviert**
- Rollen Mitarbeiter, Leitung und Admin
- Standardrechte
- Früh-/Spät-/Nacht-/Tagschichten
- Kassetten 1–100 je Standort
- Regale 1–50 und Boden je Standort
- Personenrollen und Maßnahmen
- Sonderbericht-Einsatzarten
- mindestens eine allgemeine Dienstbuch-Ereignisart je Standort
- sichere Standardwerte für Login-Sperre, Bearbeitungsfristen und Langzeitverwahrung

Nach dem ersten Login sollten zusätzlich Orte/Gebäude, fachliche Dienstbuch-Ereignisarten, dynamische Felder, Rechte und Dokumentvorlagen geprüft und an den Standort angepasst werden.

## Benutzer, Rollen und Rechte

Benutzer werden ausschließlich von Administratoren angelegt. Es gibt drei feste Grundrollen:

- Mitarbeiter
- Leitung
- Admin

Die Rollen besitzen granulare Berechtigungen. Standortzuordnung und serverseitige Rechteprüfung gelten unabhängig von sichtbaren Navigationselementen. Ein Benutzer mit mehreren Standorten wählt einen aktiven Standort; sämtliche Fachmodule prüfen diesen Kontext erneut auf dem Server.

Admins können Konten aktivieren, sperren/deaktivieren, Passwörter zurücksetzen und aktive Sitzungen beenden.

## Datenbank und Migrationen

Migrationen befinden sich unter `database/migrations/` und werden versioniert in der Tabelle `migrations` protokolliert.

Ausstehende Migrationen können im Adminbereich unter **Systemupdates** ausgeführt werden. Migrationen dürfen nicht manuell aus der Versionshistorie entfernt werden.

WKS enthält **keine interne Backup-Funktion**. Vor produktiven Updates oder kritischen Migrationen ist das Backup des Hosters/Servers zu verwenden.

## Dienstbuch

- genau ein Tagesdienstbuch je Standort/Arbeitstag
- Nachtschicht vollständig beim Datum ihres Schichtbeginns
- Dienstübernahme und Anwesenheit
- strukturierte/dynamische Ereignisfelder
- Beteiligte, Personen, Orte, Maßnahmen, externe Stellen, Anhänge
- Bearbeitungsfrist und dokumentierte Nachträge
- verbindliche digitale Übergabe
- unveränderlicher PDF-Archivstand mit SHA-256-Integritätsprüfung
- PDF, CSV und Druckansicht

## Sonderberichte

- standortbezogene Einsatzarten und dynamische Formulare
- Jahresnummern transaktionssicher ab `0001`
- Zeugen, Beteiligte, Verletzungen, externe Stellen und optionale Zwangsmaßnahmen
- Entwurf / Bearbeitung / Abschluss / Leitungsprüfung
- mehrere einzelne Nachforderungen
- geprüfte Versionen als unveränderliche Snapshots
- PDF, DOCX und Druckansicht
- bidirektionale Verknüpfung mit Dienstbuch

## Wertsachen

- globale fortlaufende Verwahrnummer ohne Jahresreset
- keine Dokumentation einzelner Wertgegenstände
- mehrere Behältnisse und Lagerorte
- Kassetten 1–100 je Standort
- Kassettenbelegung transaktionssicher
- zwei unterschiedliche numerische Siegel je Kassette
- Siegelnummer systemweit nur einmal verwendbar; die Siegelhistorie bleibt auch nach einer endgültigen Datensatzlöschung erhalten
- ausschließlich vollständige Auslagerung
- Siegelprüfung bei Auslagerung
- Langzeitverwahrung, Notizen, Änderungshistorie, Korrekturfolge
- PDF, CSV und Druckansicht

## Hausverbote

Hausverbote sind absichtlich eigenständig und werden nicht mit Dienstbuch oder Sonderbericht verknüpft. Gespeichert werden Datum, Name, Grund, Standort, Ersteller und optionale Bild-/PDF-Anhänge.

## Uploads

Uploads werden nicht unter einem öffentlich ausführbaren Webpfad abgelegt. WKS prüft Dateigröße, MIME-Type und Dateiendung, verwendet zufällige interne Dateinamen und liefert Dateien nur über autorisierte PHP-Endpunkte aus.

Zulässige Dateitypen und maximale Dateigröße werden unter **Administration → Upload-Richtlinien** konfiguriert. Die Konfiguration kann nur aus den im Backend unterstützten, geprüften Dateitypen auswählen.

## Cronjob

Der zentrale Cron-Runner lautet:

```bash
php /absoluter/pfad/WKS/bin/cron.php
```

Empfehlung: den Runner **jede Minute** durch den Hoster aufrufen lassen. WKS entscheidet anhand der in der Datenbank hinterlegten Jobintervalle selbst, welche Aufgaben fällig sind.

Der Cron verarbeitet unter anderem:

- geplante/abgelaufene Mitteilungen
- interne Ereignisbenachrichtigungen
- Langzeitverwahrungen
- E-Mail-Queue
- technische Wartung
- Systemstatusaufgaben

Im Adminbereich sind letzter Lauf, letzter erfolgreicher Lauf, Status und Fehler je Job sichtbar.

## E-Mail-Konfiguration

WKS verwendet für betriebliche Benachrichtigungen ausschließlich die konfigurierte Standortadresse. Private Mitarbeiteradressen werden nicht als Ziel verwendet.

Die Freigabe hat mehrere Stufen:

1. Ereignisregel erlaubt Standort-E-Mail.
2. Standort steht auf `Testmodus` oder `Produktiv`.
3. **Globaler Mailversand** ist aktiviert.

In der Testphase bleibt der globale Versand standardmäßig **aus**. Unterdrückte Queue-Einträge werden protokolliert, aber nicht versendet.

## Updates über GitHub

Produktiver Update-Stand ist:

```
https://github.com/julian-obermeier/WKS
Branch: main
```

Das Repository ist öffentlich; für reine Lesezugriffe wird kein GitHub-Token benötigt.

Unter **Administration → Systemupdates** kann ein Admin:

- nach einem neuen `main`-Stand suchen,
- installierte und veröffentlichte Version vergleichen,
- ausstehende Migrationen sehen,
- ein Update sofort installieren,
- die Update-Historie einsehen.

Der Installer:

1. aktiviert Wartungsmodus,
2. lädt den aktuellen öffentlichen `main`-Stand,
3. übernimmt neue Migrationen,
4. führt Migrationen aus,
5. aktualisiert kontrollierte Systemdateien,
6. führt den Post-Update-Check aus,
7. schreibt die Update-Historie,
8. beendet den Wartungsmodus nur bei erfolgreicher Prüfung.

Es gibt absichtlich **kein automatisches Rollback**. Bei einem Fehler bleibt der Vorgang protokolliert und der Wartungsmodus kann aktiv bleiben.

Sensible Dateien wie `.env` sowie Uploads, Logs und Sessions werden vom Update nicht aus dem Repository überschrieben.

## „Was ist neu?“

`VERSION` und `CHANGELOG.json` definieren die veröffentlichte Version. Nach einem Update erscheint eine noch nicht gesehene Version beim ersten Login als Modal. Alle Versionshinweise bleiben im Menü **Was ist neu?** erreichbar.

## Sicherheit

WKS setzt unter anderem um:

- `password_hash()` / `password_verify()`
- Session-ID-Regeneration nach Login
- Strict Session Mode und HttpOnly/Secure/SameSite-Cookies
- konfigurierbares Inaktivitäts-Logout
- konfigurierbare Login-Sperre
- CSRF-Schutz für schreibende Requests
- serverseitige Rechte- und Standortprüfung
- PDO Prepared Statements mit deaktivierten emulierten Prepares
- HTML-Escaping
- gesicherte Uploads und Downloads
- IDOR-Schutz durch Standort-/Rechteprüfung
- kontrollierte Fehlerseiten im Produktivbetrieb
- internes technisches Fehlerprotokoll
- Audit-Log
- Security Header und Content Security Policy

2FA, Push Notifications und Offline-Bearbeitung sensibler Vorgänge gehören ausdrücklich nicht zu dieser Version.

## PWA

WKS kann als PWA auf dem Startbildschirm installiert werden. Der Service Worker cached nur statische Assets und die Offline-Hinweisseite. Fachliche Seiten und sensible Daten werden nicht offline zwischengespeichert oder bearbeitet.

## Qualitätsprüfung

GitHub Actions führt auf jedem Push nach `main` und für Pull Requests aus:

- PHP-Lint über Anwendung, Migrationen, Views, CLI und Tests
- statische Routen-/View-Konsistenzprüfung
- Prüfung auf produktive TODO/FIXME-Markierungen
- Prüfung, dass Views keinen direkten Datenbankzugriff enthalten
- JavaScript-Syntaxprüfung
- JSON-Validierung für Manifest und Changelog

Lokal:

```bash
php tests/static_quality.php
find app bootstrap config database public routes resources bin tests -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/assets/app.js
```

## Verzeichnisrechte und sensible Daten

Nicht in Git gehören insbesondere:

- `.env`
- Datenbank-/SMTP-Passwörter
- API-Schlüssel
- Sessions
- Uploads
- Logs
- generierte Exporte/Dokumente

Die mitgelieferte `.gitignore` schließt diese Laufzeitdaten aus.

## Fehlerdiagnose

- Benutzer sehen im Produktivbetrieb keine Stacktraces.
- Technische Fehler werden nach `storage/logs/application.log` geschrieben und – sobald die Datenbankstruktur verfügbar ist – zusätzlich im Admin-Fehlerprotokoll erfasst.
- Der Systemstatus prüft PHP, Datenbank, Kerntabellen, Speicher, Schreibrechte, Mailkonfiguration, Cronjobs und Dokumenterzeugung.

## Lizenz / interner Einsatz

Vor einem produktiven Einsatz müssen Organisation, Datenschutzverantwortliche, IT-Betrieb und betriebliche Interessenvertretungen prüfen, welche Datenkategorien, Aufbewahrungsfristen und Zugriffsrechte am konkreten Einsatzort zulässig und erforderlich sind.
