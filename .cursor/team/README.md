# Team: Vorschläge an die zuständige Person

Stand 2026-10-30.

Hier liegt, was jemand für einen Bereich vorschlägt, für den ein anderer
zuständig ist. Die zuständige Person entscheidet, ob und wie es in ihr
Konzept passt. Sie übernimmt den Vorschlag in Code und Doku, überarbeitet
ihn oder lehnt ihn ab.

## Zuständigkeiten

| Bereich | Lead | Git |
|---|---|---|
| GUI: Layout, CSS, Pylon, Navigation, Client-JS, Optik der Panels | Lars | `sabietzki` |
| alles andere (Rechte, ORM, Datenbank, Auth, Controlmap, Deployment …) | Oliver | `ohjay666` |

Wer eine Doku in `.cursor/docs/` verantwortet, steht in ihrer zweiten
Zeile:

```markdown
# Titel
Lead: Lars (sabietzki)
```

Fehlt die Zeile, gilt die Tabelle oben.

## Die Regel

1. **Eine Doku eines anderen Leads wird nicht direkt geändert.** Betrifft
   eine Änderung etwas, das jemand anderes dokumentiert hat oder verantwortet,
   kommt sie als Vorschlag hierher. `.cursor/docs/` bleibt unangetastet.
2. **Auch Code dieses Bereichs** (GUI: CSS, Layout, JS) wird nicht nebenbei
   umgebaut. Wer ihn für die eigene Arbeit braucht, nimmt die kleinste
   Lösung, die das bestehende Konzept benutzt, und schreibt den Rest als
   Vorschlag auf.
3. **Ein Vorschlag ist eine Datei** `JJJJ-MM-TT-thema.md` mit diesem Kopf:

   ```markdown
   # Thema
   Von: Oliver · Für: Lars · Status: offen · 2026-10-30
   ```

   Darunter stehen der Anlass, der Vorschlag (gern mit Code-Schnipsel) und
   die betroffenen Dateien.
4. **Der Lead entscheidet** und trägt das Ergebnis in die Liste ein:
   `übernommen` (mit Commit bzw. Doku), `überarbeitet` (wie), `abgelehnt`
   (warum). Die Datei bleibt liegen, bis sie übernommen oder abgelehnt ist,
   danach kommt sie nach `team/archive/`.
5. **Claude** prüft vor jeder Änderung an `.cursor/docs/` die Lead-Zeile. Ist
   der Lead nicht die Person, mit der Claude gerade arbeitet, legt Claude
   hier einen Vorschlag an und sagt Bescheid, statt die Doku zu ändern.

## Liste

| Datum | Thema | Von | Für | Status | Ergebnis |
|---|---|---|---|---|---|
| 2026-10-30 | [Pylon so breit wie sein Inhalt, gezogene Breite für jedes Panel merken](2026-10-30-pylon-breite.md) | Oliver | Lars | offen | in cms/controlmap eingebaut, zur Entscheidung |
