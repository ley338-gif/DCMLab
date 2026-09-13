# 0020 — P10.10: Node „Teiltransfer" — Feature 5 (Abstract-Syntax-Ablehnung pro Objekt)

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 4.3 ("Nur manche Bilder kommen an") lag seit P9 als Gerüst vor,
mit dem Node `oversized-image` bereits als Lab verknüpft. Ihr eigener
Kommentar benannte präzise, was fehlte: `oversized-image` deckt nur die
Größenlimit-Hälfte (Feature 2, P10.2) ab — die zweite, in der Lektion
gleichrangig genannte Ursache ("SOP Class des Objekttyps nicht
freigeschaltet") brauchte eine SOP-Class-Prüfung, die es bislang nur
auf Ebene eines ganzen Sendeauftrags gab (Feature 4, P10.9), nicht pro
einzelnem Objekt beim direkten `storescu` von einer Shell aus
(Abschnitt 6b). `oversized-image` selbst wurde nicht verändert — ein
fertiger, bereits durchgespielter Node bekommt keine nachträgliche
zweite Ablehnungsursache; stattdessen eine neue, dediziert dafür
gebaute Node (`teiltransfer`), analog zur Entscheidung in P10.6/P10.9,
verwandte aber unterschiedliche Ursachen nie in derselben Node zu
vermischen.

## Entscheidung — Feature 5: Abstract-Syntax-Ablehnung pro Objekt

**Neuer Engine-Mechanismus** (`services/engine/app/rules.py`,
`_exec_storescu`): Jedes Objekt in `environment.objects` trägt jetzt
optional ein eigenes `sop_class`-Feld. Der Check läuft direkt vor dem
bestehenden Größenlimit-Check (Feature 2), gegen dieselbe
`accepted_sop_classes`-Liste des Ziel-Diensts, die Feature 4 (P10.9)
bereits für Sendeaufträge eingeführt hat — hier greift sie pro
einzelner Datei. Ein gemischter Ordner kann dadurch **teilweise**
ankommen: einzelne `storescu`-Aufrufe scheitern, bereits angekommene
Objekte bleiben unberührt. Details und die neue Schema-Sektion:
`content-schema.md` Abschnitt 6e.

**Zusätzlich, kein separates Feature, aber notwendig für die
Lektion:** `dcmdump <datei>` konnte bislang für keine Node echte
Objektinhalte zeigen (`"keine lokale Datei in dieser Simulation"` war
der einzige Pfad). Lektion 4.3s eigener Plan verlangt aber ausdrücklich
`dcmdump`, um die SOP Class UID eines Objekts nachzuweisen, **bevor**
gesendet wird. `_exec_dcmdump` ist deshalb neu: trägt ein Objekt ein
`sop_class`-Feld, gibt `dcmdump <datei>` eine reale, im DCMTK-Stil
formatierte Zeile für Tag `(0008,0016) SOPClassUID` zurück (dieselbe
`_dcmtk_line`-Hilfsfunktion, die `findscu` bereits nutzt) — Objekte ohne
`sop_class` und Nodes ohne `objects` verhalten sich unverändert (alter
Fehlertext bleibt Fallback).

**Reale UIDs, per pydicom verifiziert:**
- CT Image Storage: `1.2.840.10008.5.1.4.1.1.2` (bereits aus P10.9)
- Secondary Capture Image Storage: `1.2.840.10008.5.1.4.1.1.7`

**Node-Design:** Eine Serie aus drei Objekten im selben Ordner — zwei
echte CT-Schichten und ein Screenshot, den die Befund-Software beim
Speichern automatisch mitgespeichert hat (ein reales, alltägliches
Muster: PACS-Clients legen Annotations-Screenshots oft als eigene
Secondary-Capture-Objekte neben die eigentlichen Bilder). Das Archiv
registriert nur CT Image Storage. Der Spieler kann die Ursache schon
vor dem Senden per `dcmdump` nachweisen (Lernziel 2 der Lektion: "Eine
nicht akzeptierte SOP Class als Ursache nachweisen") oder sie beim
Senden selbst beobachten (still angenommene Bilder, eine explizite
Ablehnung nur für den Screenshot). Flag ist die SOP Class UID des
abgelehnten Objekts, nicht eine Byte-Zahl wie bei `oversized-image` —
das hält die beiden Nodes trotz ähnlicher Struktur unterscheidbar
lösbar.

**`content/lessons/4.3/meta.yml`**: Kommentar zum fehlenden
Engine-Feature entfernt, `lab.node` bleibt `oversized-image` — die
Lektion selbst braucht künftig **beide** Nodes als vollständiges Bild
(Größenlimit und SOP-Class), das ist als Hinweis in der Datei vermerkt.
`lab.optional` bleibt vorerst `true`, da die Lektion nur einen
einzelnen `lab.node`-Wert unterstützt und ihr eigener Fließtext noch
nicht geschrieben ist.

## Manuell verifiziert

- `services/engine/tests/test_store_sop_class.py` (neu, 6 Tests):
  akzeptiertes Objekt kommt an, nicht unterstütztes wird abgelehnt und
  nicht gezählt, ein gemischter Ordner kommt teilweise an, `dcmdump`
  zeigt die reale SOP Class UID, `dcmdump` ohne `sop_class`-Feld bleibt
  beim alten Fehlertext, Nodes ohne `accepted_sop_classes` unverändert.
- `services/engine/tests/test_real_content.py`: neuer Test lädt die
  echte, ausgelieferte Node `teiltransfer` — bestätigt `dcmdump` vor
  dem Senden, beide CT-Schichten kommen an, der Screenshot wird mit
  `abstract-syntax-not-supported` abgelehnt, Bestand zeigt korrekt
  2 statt 3 Instanzen, Flag korrekt.
- Vollständige Engine-Testsuite (84 Tests), `ruff check .`: grün.
  `mypy app`: ein vorbestehender, unveränderter Fehler (fehlende
  `types-PyYAML`-Stubs).

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Lektion 4.3s eigener Fließtext ist weiterhin Gerüst — sie braucht
  jetzt beide Nodes (`oversized-image` und `teiltransfer`) als
  vollständige Grundlage; das Content-Schema unterstützt aktuell nur
  einen `lab.node` pro Lektion, das ist bei Bedarf ein eigenes,
  separates Thema.
