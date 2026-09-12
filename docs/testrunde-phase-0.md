# Testrunde Phase 0 — Ablauf

*Die Node „Silent CT" an fünf Leute geben und danach eine Entscheidung treffen können. Zeitrahmen: eine Woche.*

Die Frage, die diese Runde beantwortet, ist nicht „gefällt es?", sondern: **Löst jemand die Aufgabe ohne dich — und will danach noch eine?** Alles andere ist Beiwerk.

---

## 1. Vorher: den Link einmal selbst prüfen

Der Link muss über das Teilen-Menü der Seite freigegeben werden. Öffne ihn danach **einmal in einem privaten Browserfenster**, ohne Anmeldung. Das ist der einzige verlässliche Test, ob deine Kollegen ihn wirklich öffnen können. Erst danach verschicken.

## 2. Wen du fragst

Fünf Leute, ausgewählt nach Rolle, nicht nach Sympathie. Die Mischung ist der Punkt:

| Wer | Was diese Person beantwortet |
|---|---|
| **2× Support, 1st/2nd Level** | Die Hauptzielgruppe. Kommen sie durch? |
| **1× IT-Admin ohne PACS-Bezug** | Trägt das Briefing ohne Vorwissen, oder braucht es mich? |
| **1× MTR/MTRA mit IT-Affinität** | Ist die Modalitätenseite glaubwürdig dargestellt? |
| **1× erfahrener PACS-Kollege** | Realismus. „So war das bei uns auch" oder „so nie"? |

Jeder einzeln. Nicht in der Gruppe, nicht im Schulungsraum — sonst löst einer und vier schauen zu.

## 3. Was du dazu sagst

So wenig wie möglich. Der ganze Test ist, ob die Node ohne dich funktioniert. Vorlage:

> Ich baue nebenbei was zum DICOM-Lernen und brauche einen ehrlichen Blick drauf.
>
> Eine Aufgabe, etwa 15 Minuten, läuft im Browser, nichts zu installieren, keine Anmeldung. Du kriegst eine Störung wie aus dem Alltag und sollst sie lösen.
>
> Ich sage dir absichtlich nichts weiter dazu. Wenn du nicht weiterkommst, ist das ein Ergebnis und kein Versagen — dann ist die Aufgabe schlecht gebaut, nicht du zu langsam. Hints sind eingebaut, benutz sie ruhig.
>
> Am Ende spuckt die Seite einen kurzen Text aus. Zwei Zeilen ausfüllen, kopieren, mir zurückschicken. Fertig.

## 4. Was du dabei *nicht* tust

- **Nicht danebensitzen.** Du würdest die Hälfte der Fragen unbewusst beantworten.
- **Nicht erklären**, auch nicht auf Nachfrage. „Steht alles da" ist die richtige Antwort.
- **Nicht nachbessern**, solange die Runde läuft. Sonst testest du fünf verschiedene Versionen.
- **Nicht nach der Meinung fragen.** Die Zahlen sagen mehr als „ja, ganz nett".

## 5. Was du misst

Vier Signale, in dieser Reihenfolge der Wichtigkeit:

1. **Abbruch oder Flag.** Wer hört auf, bevor der Flag kommt? Das ist die eine Zahl, die zählt.
2. **Zeit bis zum Flag.** Zielkorridor 10–25 Minuten. Unter 5 Minuten ist die Node zu leicht, über 35 stimmt die Hint-Staffelung nicht.
3. **Wann der erste Hint gezogen wird.** Früh und bei allen an derselben Stelle heißt: Dort fehlt eine Erklärung, kein Hint.
4. **Der Satz „das hatten wir auch mal".** Wenn er fällt, stimmt der Realismus — und das ist der eigentliche Verkaufsgrund der Plattform.

Eine Zeile pro Tester reicht:

| Wer (Rolle) | Flag? | Zeit | Hints | Wo festgehangen | Nochmal? | Bemerkenswerter Satz |
|---|---|---|---|---|---|---|
| | | | | | | |

## 6. Das Erfolgskriterium — jetzt festlegen, nicht hinterher

Sonst redet man sich jedes Ergebnis schön. Phase 1 wird gebaut, wenn:

- **mindestens 4 von 5** ohne deine Hilfe zum Flag kommen,
- **mindestens 3 von 5** von sich aus sagen, dass sie eine zweite Aufgabe machen würden,
- **mindestens 1** das Muster aus dem eigenen Alltag wiedererkennt.

Wird das nicht erreicht, ist die richtige Konsequenz nicht „trotzdem weiterbauen", sondern: die Node überarbeiten und dieselben fünf Leute mit der überarbeiteten Fassung noch einmal fragen. Das kostet eine Woche statt sechs Monate.

## 7. Wenn es schiefgeht — was das jeweils bedeutet

| Beobachtung | Die wahrscheinliche Ursache | Was du änderst |
|---|---|---|
| Mehrere brechen in den ersten 3 Minuten ab | Der Einstieg fehlt — niemand weiß, was der erste Befehl sein soll | Ein sichtbarer erster Schritt im Briefing („fang mit einem C-ECHO an") |
| Alle ziehen Hint 2 an derselben Stelle | Die Fehlermeldung wird nicht als Information erkannt | Gehört in Lektion 4.1, nicht in einen Hint |
| Flag zu schnell gefunden | Der Netzplan ist zu offensichtlich | Netzplan unvollständig machen, nicht die Node schwerer |
| „Schön, aber wofür?" | Der Nutzen ist nicht erzählt | Ein Satz zum Profil/Nachweis ins Briefing — das ist der Sekundärnutzen aus dem Konzept |
| Realismus wird bezweifelt | Szenario zu konstruiert | Mit dem erfahrenen Kollegen ein echtes Ticket aus deinem Haus in eine Node übersetzen |

## 8. Danach

Ergebnis ins Projekt schreiben — eine halbe Seite, die Tabelle aus Abschnitt 5 und die Entscheidung. Dann erst der nächste Bauschritt.

---

### Randnotiz: warum es keine gemeinsame Ergebnisliste in der Seite gibt

Die erste Fassung hatte eine geteilte Ablage mit Bestenliste. Eine Seite mit geteilter Datenablage ist technisch auf die eigene Organisation beschränkt und lässt sich nicht offen teilen — Kollegen im Haus hätten den Link nicht öffnen können. Für fünf Tester ist das die falsche Abwägung: Die Seite erzeugt jetzt am Ende eine kurze Zusammenfassung zum Kopieren, die per Mail oder Chat zurückkommt. Ab Phase 1 hat die Plattform ohnehin eigene Konten, und die Frage stellt sich nicht mehr.
