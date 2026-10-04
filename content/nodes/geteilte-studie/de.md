---
title: Geteilte Studie
scenario_title: Dieselbe Accession Number, zwei Study Instance UIDs
---

## Briefing

Eine Anfrage aus der Befundung: Zum Auftrag `A50231` (Patient 9310)
zeigt das Archiv zwei Einträge. Gleicher Patient, gleiche Beschreibung,
gleiche Accession Number, fast derselbe Zeitpunkt — auf den ersten Blick
wirkt das wie ein Duplikat.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.20.0.50 | Shell mit findscu — plus Befehlsvorlagen |
| Archiv | 10.20.0.10 | C-FIND stellen; Konfiguration gesperrt (Herstellerzugang) |

Deine Aufgabe: Entscheide, ob es sich um denselben Study-Kontext, zwei
echte unterschiedliche Studies, einen Study-Split oder eine andere
Ursache handelt — und gib deine Diagnose als Flag ein (`STUDY-SPLIT`,
wenn du zu diesem Schluss kommst).

Vorkenntnisse: Lektion 4.5. Rechne mit 18 Minuten.

## Hints

### h1

Zwei Einträge mit derselben Accession Number können zweierlei sein: ein
Auftrag mit mehreren Untersuchungen oder ein Fehler. Bevor du entscheidest,
was es hier ist, vergleiche die Study Instance UIDs
beider Treffer — sind sie wirklich verschieden?

### h2

Frage für jede der beiden Study Instance UIDs gezielt die Serien ab
(`QueryRetrieveLevel=SERIES`), und wirf einen Blick in
`geraeteprotokoll.txt`.

### h3

`geraeteprotokoll.txt` zeigt einen Geräteneustart mitten in der
Untersuchung. Zusammen mit der gemeinsamen Accession Number und der
Serienverteilung (1 Serie gegenüber 3) ergibt das eine eindeutige
Diagnose — genau die ist das Flag, nicht eine der beiden UIDs.

## Write-up

### Was beobachtet wurde

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k AccessionNumber=A50231 \
          -k PatientID -k PatientName -k StudyInstanceUID \
          -k StudyDescription -k StudyDate \
          -aet DCMLAB-WS -aec MR-ARCHIV 10.20.0.10 104
I: # Dicom-Data-Set
I: (0008,0020) DA [20260304]                               #   8, 1 StudyDate
I: (0008,0050) SH [A50231]                                 #   6, 1 AccessionNumber
I: (0008,0052) CS [STUDY]                                  #   6, 1 QueryRetrieveLevel
I: (0008,1030) LO [MR Wirbelsaeule nativ]                  #  22, 1 StudyDescription
I: (0010,0010) PN [KELLER^ANNA]                            #  12, 1 PatientName
I: (0010,0020) LO [9310]                                   #   4, 1 PatientID
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.410205118834]   #  36, 1 StudyInstanceUID
I: # Dicom-Data-Set
I: (0008,0020) DA [20260304]                               #   8, 1 StudyDate
I: (0008,0050) SH [A50231]                                 #   6, 1 AccessionNumber
I: (0008,0052) CS [STUDY]                                  #   6, 1 QueryRetrieveLevel
I: (0008,1030) LO [MR Wirbelsaeule nativ]                  #  22, 1 StudyDescription
I: (0010,0010) PN [KELLER^ANNA]                            #  12, 1 PatientName
I: (0010,0020) LO [9310]                                   #   4, 1 PatientID
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.410288227741]   #  36, 1 StudyInstanceUID
I: Number of Matches: 2
```

**Was du daran abliest:** Zwei vollständig getrennte Study Instance
UIDs unter derselben Accession Number, demselben Patienten und
demselben Datum. Eine Dublette (dieselben Objekte zweimal eingespielt)
würde am Archiv gar keinen zweiten Eintrag erzeugen — hier existieren
zwei echte, eigenständige Studies.

### Welche Hypothesen möglich waren

1. **Derselbe Study-Kontext / erneute Übertragung (Dublette).**
   Widerlegt: Eine Dublette verändert den Bestand nicht und würde
   weiterhin nur einen Eintrag mit unveränderter Serienzahl zeigen —
   hier gibt es zwei echte, unterschiedliche Study Instance UIDs.
2. **Zwei echte unterschiedliche Studies (zwei getrennte Untersuchungen).**
   Grundsätzlich möglich: Die Accession Number identifiziert den Auftrag,
   und ein Auftrag mit mehreren angeforderten Untersuchungen erzeugt mehrere
   Studies unter derselben Accession Number; unterschieden werden sie dann
   durch die Requested Procedure ID (IHE RAD TF-1, Abschnitt 34.4.1.2). Hier
   spricht dagegen, dass sich die Serien beider Studies zu genau einer
   Wirbelsäulenuntersuchung ergänzen und das Gerät dazwischen neu gestartet
   ist.
3. **PACS-Duplikat durch eine zweite Patientenidentität.** Nicht mit
   letzter Sicherheit ausschließbar: Patient ID allein ist laut Standard
   nicht global eindeutig — erst zusammen mit dem Issuer of Patient ID
   (0010,0021) ist sie es. Für diesen Fall gibt es aber keinerlei Hinweis
   auf eine zweite Identitätsdomäne (keine widersprüchliche Registrierung,
   kein zweiter Issuer im Umlauf), während gemeinsame Accession Number,
   Zeitpunkt und Geräteprotokoll klar in eine andere Richtung zeigen —
   diese Hypothese bleibt deshalb unbelegt, nicht bewiesen falsch.
4. **Study-Split.** Am besten belegt: durch die Kombination aus
   gemeinsamer Accession Number, nahezu identischem Zeitpunkt,
   `geraeteprotokoll.txt` (Geräteneustart mitten in der Untersuchung)
   und der Serienverteilung (1 gegenüber 3) — keine dieser vier
   Beobachtungen allein würde reichen.

### Welche Evidenz die Hypothesen trennt

```
$ findscu -S -k QueryRetrieveLevel=SERIES \
          -k StudyInstanceUID=1.2.276.0.7230010.3.1.4.410205118834 \
          -k SeriesInstanceUID -k SeriesDescription \
          -aet DCMLAB-WS -aec MR-ARCHIV 10.20.0.10 104
I: # Dicom-Data-Set
I: (0008,0052) CS [SERIES]                                 #   6, 1 QueryRetrieveLevel
I: (0008,103e) LO [Sag T2]                                 #   6, 1 SeriesDescription
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.410205118834]   #  36, 1 StudyInstanceUID
I: (0020,000e) UI [1.2.276.0.7230010.3.1.3.410205118901]   #  36, 1 SeriesInstanceUID
I: Number of Matches: 1

$ findscu -S -k QueryRetrieveLevel=SERIES \
          -k StudyInstanceUID=1.2.276.0.7230010.3.1.4.410288227741 \
          -k SeriesInstanceUID -k SeriesDescription \
          -aet DCMLAB-WS -aec MR-ARCHIV 10.20.0.10 104
I: # Dicom-Data-Set
I: (0008,0052) CS [SERIES]                                 #   6, 1 QueryRetrieveLevel
I: (0008,103e) LO [Sag T1]                                 #   6, 1 SeriesDescription
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.410288227741]   #  36, 1 StudyInstanceUID
I: (0020,000e) UI [1.2.276.0.7230010.3.1.3.410288227801]   #  36, 1 SeriesInstanceUID
I: # Dicom-Data-Set
I: (0008,0052) CS [SERIES]                                 #   6, 1 QueryRetrieveLevel
I: (0008,103e) LO [Tra T2]                                 #   6, 1 SeriesDescription
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.410288227741]   #  36, 1 StudyInstanceUID
I: (0020,000e) UI [1.2.276.0.7230010.3.1.3.410288227802]   #  36, 1 SeriesInstanceUID
I: # Dicom-Data-Set
I: (0008,0052) CS [SERIES]                                 #   6, 1 QueryRetrieveLevel
I: (0008,103e) LO [Cor STIR]                               #   8, 1 SeriesDescription
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.410288227741]   #  36, 1 StudyInstanceUID
I: (0020,000e) UI [1.2.276.0.7230010.3.1.3.410288227803]   #  36, 1 SeriesInstanceUID
I: Number of Matches: 3
```

**Was du daran abliest:** Eine Study trägt genau eine Serie, die andere
die übrigen drei — zusammen ergeben sie eine vollständige
Wirbelsäulenuntersuchung. `geraeteprotokoll.txt` liefert die passende
zeitliche Erklärung: Nach dem Neustart um 09:17 übernahm MR-3 die
bereits vergebene Study Instance UID nicht weiter, sondern begann eine
neue Study — genau das Verhalten, das Lektion 4.5 als typische
Split-Ursache beschreibt.

### Wo die erste fehlerhafte Stelle liegt

Nicht im Archiv und nicht in der Übertragung — die erste fehlerhafte
Stelle liegt am Gerät selbst, im Moment des Neustarts: MR-3 hat nach
dem Systemfehler eine neue Study Instance UID vergeben, statt die
bereits laufende Untersuchung fortzusetzen.

### Saubere betriebliche Maßnahme

Erneutes Senden behebt das nicht — beide Studies bleiben zwei Studies,
bis sie aktiv zusammengeführt werden. Die passende Maßnahme ist, den
Fall dem für Study-Merges zuständigen Prozess zu melden, nicht die
Bilder erneut zu senden oder eine der beiden Studies zu löschen. Wie
ein solcher Merge im Archiv technisch abläuft, ist Sache eines eigenen
Vorgangs, nicht dieses Nodes.

### Was du mitnimmst

Split und Dublette erzeugen am Archiv fundamental unterschiedliche
Bilder, obwohl sie sich für einen Menschen fast identisch lesen. Die
Study Instance UID sagt dir zuverlässig, ob zwei Einträge dieselbe oder
zwei unterschiedliche Study-Identitäten tragen — Patient ID,
Beschreibung, Datum und sogar die Accession Number können bei einem
Split trotzdem übereinstimmen. Sie beweist damit aber noch nicht, *wie*
zwei unterschiedliche Identitäten entstanden sind: Die Split-Diagnose
selbst ergibt sich erst aus der Kombination mehrerer Beobachtungen —
gemeinsame Accession Number, zeitlicher Zusammenhang, Serienverteilung
und ein dokumentierter Geräteneustart. Genau diese Kombination macht den
Unterschied zwischen "zwei verschiedene UIDs" und "das war ein Split".

### Verwandte Inhalte

Lektion 4.5 — Studie ist gesplittet / doppelt
