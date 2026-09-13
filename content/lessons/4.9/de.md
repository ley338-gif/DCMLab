---
title: „Nach TLS-Aktivierung geht nichts mehr"
teaser: TLS verschiebt den Fehler von der DICOM-Ebene auf die Transportebene — und macht dabei jedes Legacy-Gerät sichtbar.
objectives:
  - Einen TLS-Fehler von einem DICOM-Fehler unterscheiden
  - Zertifikatskette, Gültigkeit und Namensprüfung als getrennte Ursachen prüfen
  - Legacy-Geräte als harte Grenze einer TLS-Einführung einschätzen
---

## Ein Ticket nach einem Umstellungswochenende

Übers Wochenende wurde DICOM-TLS für eine Verbindung aktiviert. Montag
morgens: keine Assoziation kommt mehr zustande. Kein „Association
rejected", keine DICOM-Statusmeldung — die Fehlermeldung klingt anders
als alles, was in den vorigen neun Lektionen aufgetaucht ist.

## Was TLS an der Association ändert

TLS setzt sich zwischen TCP und die eigentliche DICOM-Aushandlung: erst
läuft ein TLS-Handshake (Zertifikate, Cipher-Aushandlung), erst danach
beginnt überhaupt die A-ASSOCIATE-Anfrage — verschlüsselt. Schlägt der
Handshake fehl, sieht die Gegenstelle nie eine DICOM-Nachricht, weder
eine Ablehnung noch sonst etwas. Genau das erklärt, warum sich ein
TLS-Fehler anders anfühlt als jeder Fehler aus den Lektionen 4.1–4.8:
Dort kam immer *irgendeine* DICOM-Antwort zurück, und sei es eine
Ablehnung. Ein gescheiterter Handshake liefert gar keine.

Orthanc (das Archiv dieser Spielwiese) kennt nur einen einzigen
DICOM-Port für alle Lektionen — TLS ließe sich dort nicht aktivieren,
ohne jede andere, bereits reale Lektion dieses Tracks zu brechen
(verifiziert: mit `DicomTlsEnabled` weist Orthanc jede unverschlüsselte
Verbindung ab). Die TLS-Gegenstelle dieser Lektion ist deshalb ein
eigener `storescp` mit TLS, den du selbst startest — unabhängig von
Orthanc.

## Ein Zertifikat, das die DCMTK-Werkzeuge nicht kennt

`echoscu`/`storescu` sind in dieser Spielwiese pip-installierte
`pynetdicom`-Skripte, die die echten DCMTK-Programme verdecken (siehe
Lektion 4.2) — und `pynetdicom`s Kommandozeile kennt DCMTK-TLS-Flags
wie `+tla` schlicht nicht. Für diese Lektion brauchst du deshalb den
vollen Pfad zu den echten DCMTK-Programmen: `/usr/bin/echoscu` und
`/usr/bin/storescu`.

```
$ /usr/bin/storescp +tls /opt/tools/tls/server-key.pem /opt/tools/tls/server-cert.pem \
    -ic -aet TLS-SCP -od /tmp/received 11113 &
$ /usr/bin/echoscu -v +tla +cf /opt/tools/tls/server-cert.pem -aec TLS-SCP 127.0.0.1 11113
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Echo Request (MsgID 1)
I: Received Echo Response (Success)
I: Releasing Association
```
**Was du daran abliest:** `+tla` (anonyme TLS, kein eigenes
Client-Zertifikat) und `+cf` (das Server-Zertifikat als vertrauenswürdig
eintragen) reichen für eine erfolgreiche, verschlüsselte Association.
Auf der SCP-Seite verlangt `+tls` normalerweise ein Client-Zertifikat
zurück — `-ic` (`--ignore-peer-cert`) schaltet das ab, sonst scheitert
die Verbindung mit „peer did not return a certificate", obwohl das
Serverzertifikat selbst vollkommen in Ordnung ist.

```
$ /usr/bin/storescu +tla +cf /opt/tools/tls/server-cert.pem -aec TLS-SCP 127.0.0.1 11113 instance-0001.dcm
$ echo $?
0
```
**Was du daran abliest:** Derselbe Mechanismus trägt auch einen
echten Bildtransfer — sobald der Handshake steht, läuft C-STORE
darüber wie gewohnt, nur eben verschlüsselt.

## Wenn niemand dem Zertifikat vertraut

```
$ /usr/bin/echoscu +tla -aec TLS-SCP 127.0.0.1 11113
I: Requesting Association
F: Association Request Failed: 000b:0886 TLS error: certificate verify failed
```
**Was du daran abliest:** Derselbe Aufruf ohne `+cf` — die Association
scheitert schon *vor* jeder DICOM-Nachricht, mit einem reinen
TLS-Fehlercode (`000b:0886`), nicht mit einer DICOM-Ablehnung. Kein
„Association rejected", kein Status-Code aus PS3.7 — die Fehlermeldung
kommt aus einer ganz anderen Schicht.

## Eine Sache, die *nicht* automatisch geprüft wird

Das Testzertifikat dieser Spielwiese trägt `CN=dcmlab-spielwiese-tls`
— ein Name, der mit der tatsächlichen Adresse (`127.0.0.1`) nichts zu
tun hat. Die Verbindung oben ist trotzdem erfolgreich:

```
$ openssl x509 -in /opt/tools/tls/server-cert.pem -noout -subject
subject=CN = dcmlab-spielwiese-tls
```
**Was du daran abliest:** DCMTKs Standardverhalten prüft nicht, ob der
Zertifikatsname zur Zieladresse passt — anders als ein Browser bei
HTTPS. Der einzige tatsächlich geprüfte Punkt ist die Vertrauenskette
(`+cf`), nicht der Name. Wer sich bei DICOM-TLS auf eine Namensprüfung
verlässt, verlässt sich auf etwas, das hier gar nicht stattfindet —
eine Fehlannahme, die die Fehlersuche in echten Häusern in die falsche
Richtung lenken kann.

## Auf der Leitung: nur noch Handshake, dann nichts mehr

```
$ tshark -i lo -f "tcp port 11113" -Y "tls || dicom"
4   0.000256   127.0.0.1 → 127.0.0.1   TLSv1   358   Client Hello
6   0.001449   127.0.0.1 → 127.0.0.1   TLSv1.3 1460  Server Hello, Change Cipher Spec, Application Data, Application Data, Application Data, Application Data
8   0.001950   127.0.0.1 → 127.0.0.1   TLSv1.3 146   Change Cipher Spec, Application Data
9   0.002091   127.0.0.1 → 127.0.0.1   TLSv1.3 321   Application Data
10  0.002124   127.0.0.1 → 127.0.0.1   TLSv1.3 299   Application Data
11  0.002198   127.0.0.1 → 127.0.0.1   TLSv1.3 321   Application Data
```
**Was du daran abliest:** `-Y "tls || dicom"` findet ab dem
`Client Hello` kein einziges Paket mehr, das Wireshark als `dicom`
erkennt — nur noch `Application Data`. Genau das ist mit „der Mitschnitt
zeigt ab hier weniger" gemeint: die A-ASSOCIATE-Anfrage, die
Presentation-Context-Aushandlung, sogar der C-ECHO selbst stecken alle
in diesen verschlüsselten Paketen. Ein Mitschnitt kann noch bestätigen,
*dass* gesprochen wird — nicht mehr, *worüber*.

## Was hier nicht reproduzierbar ist

Zwei der drei in der Gliederung genannten Ursachenklassen ließen sich
in dieser Spielwiese nicht ehrlich live erzeugen: ein abgelaufenes
Zertifikat (das lokale Systemdatum eines Containers lässt sich nicht
ohne Weiteres vor- oder zurückstellen, ein rückdatiertes Zertifikat
lässt sich mit den hier verfügbaren `openssl`-Werkzeugen nicht direkt
ausstellen) und eine echte Cipher-/Protokollversions-Ablehnung
(beide Seiten sind derselbe, aktuelle OpenSSL-Stand — ein erzwungener
Suite-Konflikt wäre erfunden, kein tatsächliches Verhalten). Ein Gerät,
das gar kein TLS kann, ist strukturell dasselbe wie der Trust-Fehler
oben, nur mit umgekehrter Rolle (der Client spricht gar kein TLS statt
ihm nicht zu vertrauen) — dafür reicht ein `-tls`-Aufruf gegen den
TLS-Endpunkt, das Ergebnis ist ein einfacher Verbindungsfehler ohne
TLS-Details, wenig zusätzlicher Erkenntniswert gegenüber dem bereits
gezeigten Trust-Fehler.

## Im Alltag

| Frage | Werkzeug |
|---|---|
| Kommt überhaupt eine Verbindung zustande? | `ping`/Portcheck, unverändert gegenüber unverschlüsseltem DICOM |
| Scheitert der TLS-Handshake selbst? | Fehlercode beginnt mit `000b:` statt einem DICOM-Statuscode |
| Vertraut die Gegenstelle dem Zertifikat? | `+cf`/die passende Trust-Chain prüfen — nicht den Namen |
| Was genau ging über die Leitung? | `tshark -Y tls` — mehr als „es wurde gesprochen" zeigt es nicht |

## Stolperfallen

- **„TLS ist aktiv, also ist der Rest egal."** TLS ändert nichts an
  Presentation-Context-Aushandlung, AE-Titles oder Timeouts — es kommt
  nur eine zusätzliche Schicht *davor* hinzu, die eigene Fehlerbilder
  hat.
- **Auf Namensprüfung verlassen.** DCMTKs Standardverhalten prüft die
  Vertrauenskette, nicht den Zertifikatsnamen gegen die Zieladresse —
  ein Zertifikat mit „falschem" Namen kann trotzdem akzeptiert werden.
- **DCMTK-TLS-Flags an die falschen Programme schicken.** In dieser
  Spielwiese verdecken `pynetdicom`-Skripte die echten
  `echoscu`/`storescu` (Lektion 4.2) — `+tla`/`+cf` brauchen den vollen
  Pfad zu `/usr/bin/echoscu`.

## Selbstcheck

1. Eine Verbindung scheitert mit einem Fehlercode, der mit `000b:`
   beginnt. Auf welcher Schicht liegt der Fehler — DICOM oder darunter?
2. Ein Zertifikat trägt einen Namen, der nicht zur Zieladresse passt.
   Verhindert das die Verbindung bei DICOM-TLS in der Standardeinstellung?
3. Ein Mitschnitt einer TLS-verschlüsselten Verbindung zeigt nur noch
   `Application Data`. Was kannst du daraus noch ableiten — und was
   nicht mehr?
