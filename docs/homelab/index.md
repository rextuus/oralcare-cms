# oralcare – Überblick

<div class="hl-showcase" data-device="phone" data-url="zahnarztpraxis-kaldauen.de" markdown="0">
<p class="hl-showcase__claim"><em>Termin buchen</em>, Weg finden, Team kennenlernen.</p>
<p class="hl-showcase__lead">Die Website der Zahnarztpraxis in Siegburg-Kaldauen: Termin online buchen, auch per QR-Code vom Flyer, dazu Öffnungszeiten, Anfahrt und Notdienst sofort zur Hand.</p>
<ol>
<li data-title="Termin online buchen" data-icon="calendar" data-badges="Dr. Flex|per QR-Code|Team-Kalender">
<p>Ein Klick auf „Termin buchen“ öffnet das Buchungsfenster direkt auf der Seite. Wer den QR-Code auf dem Flyer scannt, landet sofort darin.</p>
<img src="bilder/showcase/buchen.webp" alt="Kontaktseite am Handy mit Karte „Online-Termin – Termin buchen“">
</li>
<li data-title="Anfahrt & Öffnungszeiten" data-icon="map" data-badges="Google Maps|Route planen|Anruf per Tipp">
<p>Adresse mit Karte, Wegbeschreibung per Knopf und Anrufen mit einem Tippen. Die Öffnungszeiten stehen überall gleich und aktuell.</p>
<img src="bilder/showcase/anfahrt.webp" alt="Karte mit Praxisstandort, darunter die Knöpfe „Jetzt anrufen“ und „Anfahrt“">
</li>
<li data-title="Team kennenlernen" data-icon="users" data-badges="4 Bereiche|Schwerpunkte|Kontakt direkt">
<p>Wer behandelt mich? Das Team lässt sich nach Zahnärzten, Empfang, Assistenz und Prophylaxe filtern, jede Person hat eine eigene Seite.</p>
</li>
<li data-title="Leistungen & Ratgeber" data-icon="star" data-badges="Detailseiten|Ratgeber|FAQ">
<p>Jede Behandlung ist auf einer eigenen Seite erklärt, dazu Ratgeber-Artikel und Antworten auf häufige Fragen.</p>
<img src="bilder/showcase/leistungen.webp" alt="Leistungsübersicht am Handy mit Kacheln für Vorsorge und Prophylaxe">
</li>
<li data-title="Notdienst & Aktuelles" data-icon="bell" data-badges="Notdienst-Info|Urlaubshinweis|3 Farben">
<p>Bei Zahnschmerzen außerhalb der Sprechzeiten steht gleich da, wer hilft. Praxisurlaub und Neuigkeiten erscheinen gut sichtbar auf der Startseite.</p>
<img src="bilder/showcase/notdienst.webp" alt="Blaue Notfallbox mit Notdienst-Suche und Notdienstnummer">
</li>
<li data-title="Praxis pflegt selbst" data-icon="edit" data-badges="Entwurf & live|Rollenrechte|2. Faktor">
<p>Das Praxisteam ändert Texte, Bilder und Zeiten selbst und stellt sie erst online, wenn alles fertig ist.</p>
</li>
</ol>
<ul class="hl-showcase__tags"><li>Bildergalerie mit Vollbild</li><li>Eigene Handy-Bilder</li><li>Dunkler Modus</li><li>Leistungs-Karussell</li><li>Volltextsuche</li><li>QR-Weiterleitungen</li><li>Besucherstatistik</li><li>Öffnungszeiten je Seite</li><li>Social-Media-Links</li><li>Ohne Anmeldung</li><li>Impressum &amp; Datenschutz</li></ul>
</div>

oralcare ist die Website der **Zahnarztpraxis Kaldauen**, gebaut mit dem CMS [Sulu](https://sulu.io/) 3.0 auf
Symfony 7.4. Die Praxis pflegt Seiten, Team, Leistungen, Öffnungszeiten und Bilder selbst im Sulu-Admin. Termine
bucht man über das eingebettete Buchungsfenster von Dr. Flex. Eigener Code gibt es nur wenig: ein kleines
Besucher-Analytics im Admin und eine QR-Code-Weiterleitungsseite. Der Rest sind Sulu-Templates und Twig.

!!! info "Stand"
    Seit November 2026 läuft die Seite als Komodo-Stack **`oralcare`** aus einem fertigen Image. Der alte,
    von Hand gestartete Stack unter `/opt/oralcare-cms` ist nur noch Rückfallebene und wird später gelöscht.

## Architektur

```mermaid
flowchart TB
    v(["Besucher<br/>Browser"]) & p(["Praxis<br/>Sulu-Admin"]) -- "HTTPS" --> vps["VPS-Caddy<br/>TLS"]
    vps -- "WireGuard<br/>:8192" --> php

    subgraph abyss["abyss · Stack oralcare"]
        php["php<br/>Apache + Sulu"] --> db[("MySQL 8.4")]
        php --> files[("/srv/oralcare<br/>Bilder, Cache, Index")]
        bk["backup<br/>02:30"] -.-> db
        bk -.-> bdir[("/srv/backups<br/>/oralcare")]
    end

    php -. "Fehler" .-> sentry["Sentry"]
    v -. "Termin buchen" .-> flex["Dr. Flex"]
    v -. "Fonts, CSS, JS" .-> cdn["CDNs"]

    classDef user fill:transparent,stroke:#0d9488,stroke-width:2px
    classDef app fill:#0d9488,stroke:#0f766e,color:#fff
    classDef data fill:#f59e0b,stroke:#b45309,color:#1b1c1e
    classDef ext stroke-dasharray:5 4
    class v,p user
    class php,bk app
    class db,files,bdir data
    class vps,sentry,flex,cdn ext
```

*Ein Container macht alles: Apache mit mod_php liefert Website und Admin aus. Nach außen geht nur Port 8192 im
WireGuard-Netz, HTTPS macht der Caddy auf dem VPS. Der Backup-Dienst läuft nur nachts kurz.*

- **php** (PHP 8.5, Apache, Sulu) ist die ganze Anwendung. Website und Admin unterscheiden sich nur am Pfad:
  alles unter `/admin` ist der Admin-Kontext, der Rest die Website mit vorgeschaltetem Seiten-Cache.
- **database** (MySQL 8.4) hält Inhalte, Benutzer, Medien-Metadaten und die Analytics-Daten.
- **backup** startet nur über die Komodo-Procedure (nachts) oder vor jedem Deploy, siehe [Daten](daten.md).
- **Fremddienste:** Dr. Flex (Termin-Widget, im Browser des Besuchers), Sentry (Fehlermeldungen vom Server) sowie
  Google Fonts, Tailwind-CDN und jsDelivr (Bootstrap, Icons, Lightbox) direkt aus den Templates.

## Kennzahlen

<div class="hl-stats">
  <div class="hl-stat hl-stat--accent"><b>2</b><span>Dauerdienste<br>php, database</span></div>
  <div class="hl-stat"><b>1</b><span>Einmal-Dienst<br>backup</span></div>
  <div class="hl-stat"><b>93</b><span>Tabellen<br>davon 1 eigene</span></div>
  <div class="hl-stat"><b>12</b><span>Seiten-Templates<br>+ 1 Artikel</span></div>
  <div class="hl-stat"><b>6</b><span>Snippets<br>Adresse, Kopf, Fuß …</span></div>
  <div class="hl-stat"><b>8</b><span>eigene Routen<br>Analytics-API im Admin</span></div>
  <div class="hl-stat"><b>1</b><span>Konsolen-Befehl<br>app:analytics:cleanup</span></div>
  <div class="hl-stat"><b>4</b><span>Komodo-Secrets<br>Präfix ORALCARE_</span></div>
</div>

## Auf einen Blick

<div class="grid cards" markdown>

-   :material-tooth-outline:{ .lg } **Für wen**

    ---

    Patientinnen und Patienten der Praxis (öffentliche Website, Deutsch und Englisch) und das Praxisteam,
    das die Inhalte selbst pflegt.

-   :material-web:{ .lg } **Was es kann**

    ---

    Siehe Showcase oben. Technisch: Sulu-Seiten und Snippets, Dr.-Flex-Widget, QR-Landingpage und
    ein eigenes Besucher-Analytics im Admin.

-   :material-shield-lock-outline:{ .lg } **Wer was darf**

    ---

    Die Website ist öffentlich, ohne Konten. Den Admin unter `/admin` nutzt nur die Praxis: Login mit Passwort,
    optional zweiter Faktor. Was jemand im Admin sieht, regeln Sulu-Rollen; Analytics ist ein eigenes Recht.

</div>

## Weitere Seiten

| Seite | Inhalt |
|---|---|
| [Aufbau](aufbau.md) | Seitenaufruf mit Cache, Admin-Login, QR-Weiterleitung, Inhaltsmodell, Datenmodell |
| [Betrieb](betrieb.md) | Deploy, Rollback, Image-Bau, Dienste, Variablen, nützliche Befehle |
| [Daten](daten.md) | Was wo liegt, Backup, Wiederherstellung |
| [Wenn's hakt](probleme.md) | Symptome → Ursache → Abhilfe |

## Vorhandene Doku im Repo

| Datei | Worum es geht |
|---|---|
| [deploy/UMZUG.md](https://github.com/rextuus/oralcare-cms/blob/main/deploy/UMZUG.md) | Umzug vom manuellen Stack nach Komodo: Prüfungen, Rückfall, Herkunft der Secrets, Altlasten in `/opt` |
| [deploy/compose.yaml](https://github.com/rextuus/oralcare-cms/blob/main/deploy/compose.yaml) | Der Produktions-Stack, mit Kommentaren |
| [deploy/komodo/oralcare.toml](https://github.com/rextuus/oralcare-cms/blob/main/deploy/komodo/oralcare.toml) | Komodo-Stack und Backup-Procedure als Resource Sync |
| [README.md](https://github.com/rextuus/oralcare-cms/blob/main/README.md) | Unverändertes README des Sulu-Skeletons, nichts Projektspezifisches |
