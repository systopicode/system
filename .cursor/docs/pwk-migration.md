# PatchworkKit – Migration Legacy → `projects/pwk.systopic.net`

> **Diese Datei ist der Einstiegspunkt.**  
> Details sind aufgeteilt in:
> - [`pwk-migration/server.md`](pwk-migration/server.md) – PHP/Server-Migration
> - [`pwk-migration/client.md`](pwk-migration/client.md) – JS/Client-Migration

---

## Status-Übersicht

| Phase | Titel | Status |
|-------|-------|--------|
| 1 | `public/` Ordner anlegen | ✅ |
| 2 | `settings.php` anlegen | ⬜ |
| 3 | Root Panel (`panels/`) | ✅ |
| 3b | Library Panel (`panels/library/`) | ✅ |
| 3c | AJAX-Navigation Home→Library Bugfixes | ✅ |
| 3d | JS-Dateien für Library Panel | ✅ |
| 4 | Anwendungslogik migrieren (`class/` + `modules/`) | ⬜ |
| 5 | Datenbankmigrationen | ⬜ |
| 6 | Medien-Migration (`var/files/`) | ⬜ |
| 7 | PWK-spezifische System-Integrationen | ⬜ |
| 8 | Bereinigung & Tests | ⬜ |

---

## Schlüssel-Entscheidungen

| Thema | Entscheidung |
|-------|-------------|
| `cms` vs `sys` | `cms === sys` (Alias in `sys.js`) — Legacy-Code läuft ohne Änderung |
| Panel-JS-Pfad | `public/panels/<name>/panel.js` (HTTP-zugänglich, getrennt von PHP-Templates) |
| Class-JS-Pfad | `public/lib/class/<name>/<name>.js` (wie bisher, durch `clientPreload` auto-registriert) |
| `module.js → panel.js` | Route: `modules.*` → `panels.*`, extends: `modules` → `classes.panelNode` |
| `.htaccess` Static Files | `RewriteCond %{REQUEST_FILENAME} -f` statt pfadbasierter Regex |
| Render Mode | Root: `slices` (Default), alle Sub-Panels: `slices` |

---

## Referenzen

- **Spaceticker Entry Point:** `projects/spaceticker.app/public/index.php`
- **System-Paket:** `packages/system/`
- **Panel-System Architektur:** [`panel-system.md`](panel-system.md)
- **PanelNode API:** [`panel-node-api.md`](panel-node-api.md)
- **JS-Plugin-System:** [`js-plugin.md`](js-plugin.md)
