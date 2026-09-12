# Testdaten für Silent CT

Erwartet wird der Datensatz `ct-thorax-3-slices`: drei synthetische CT-Objekte
einer Patientin `MUSTER^ERIKA` / `4711`, Study `CT Thorax nativ`.

Die `SeriesDescription` dieser Serie ist der Flag. Sie steht bewusst **nicht**
im Repo — der Build erzeugt die Objekte und hasht den Wert nach
`node.yml: flag.hash`.

Erzeugen lassen sich die Objekte mit `pydicom`; nie echte Patientendaten
verwenden, auch nicht anonymisiert.
