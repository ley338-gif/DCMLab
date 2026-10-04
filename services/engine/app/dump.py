"""Ausgabeformat von DCMTK (`dcmdump`, `findscu -v`) fuer die simulierte Engine.

Die Zeilen sind gegen echtes DCMTK 3.6.7 (Toolbox-Image der Spielwiese)
abgeglichen, nicht nachempfunden:

    (0008,0060) CS [CT]                                     #   2, 1 Modality
    (0020,000d) UI [1.2.276.0.7230010.3.1.4.541902387012]   #  36, 1 StudyInstanceUID
    (0008,0016) UI =CTImageStorage                          #  26, 1 SOPClassUID
    (0010,0010) PN (no value available)                     #   0, 0 PatientName

- Tag mit kleinen Hex-Ziffern, danach VR und Wert; bis Spalte 56 mit
  Leerzeichen aufgefuellt (mindestens eines), dann `#`, Laenge (4 Stellen
  rechtsbuendig), VM und Keyword.
- Die Laenge ist die des kodierten Werts, auf eine gerade Zahl aufgefuellt
  (PS3.5 Abschnitt 7.1.1) -- `MEYER, HANS` (11 Zeichen) zeigt 12.
- Bekannte UIDs zeigt DCMTK als `=Name` (Voreinstellung, `-Un` schaltet das
  ab); die Laenge bleibt die der UID.
- Verschachtelte Elemente (Sequenzen) sind je Ebene um zwei Leerzeichen
  eingerueckt; die Spalte 56 zaehlt ab der Einrueckung.
"""

from __future__ import annotations

LINE_WIDTH = 56

# Namen exakt wie `dcmdump` (DCMTK 3.6.7) sie ausgibt -- nur UIDs, die gegen die
# Toolbox geprueft sind. Unbekannte UIDs erscheinen wie bei DCMTK als Nummer.
UID_NAMES = {
    "1.2.840.10008.1.2": "LittleEndianImplicit",
    "1.2.840.10008.1.2.1": "LittleEndianExplicit",
    "1.2.840.10008.1.2.2": "BigEndianExplicit",
    "1.2.840.10008.1.2.4.50": "JPEGBaseline",
    "1.2.840.10008.1.2.4.51": "JPEGExtended:Process2+4",
    "1.2.840.10008.1.2.4.57": "JPEGLossless:Non-hierarchical:Process14",
    "1.2.840.10008.1.2.4.70": "JPEGLossless:Non-hierarchical-1stOrderPrediction",
    "1.2.840.10008.1.2.4.80": "JPEGLSLossless",
    "1.2.840.10008.1.2.4.81": "JPEGLSLossy",
    "1.2.840.10008.1.2.4.90": "JPEG2000LosslessOnly",
    "1.2.840.10008.1.2.4.91": "JPEG2000",
    "1.2.840.10008.1.2.5": "RLELossless",
    "1.2.840.10008.5.1.4.1.1.1": "ComputedRadiographyImageStorage",
    "1.2.840.10008.5.1.4.1.1.1.1": "DigitalXRayImageStorageForPresentation",
    "1.2.840.10008.5.1.4.1.1.2": "CTImageStorage",
    "1.2.840.10008.5.1.4.1.1.2.1": "EnhancedCTImageStorage",
    "1.2.840.10008.5.1.4.1.1.4": "MRImageStorage",
    "1.2.840.10008.5.1.4.1.1.6.1": "UltrasoundImageStorage",
    "1.2.840.10008.5.1.4.1.1.7": "SecondaryCaptureImageStorage",
    "1.2.840.10008.5.1.4.1.1.11.1": "GrayscaleSoftcopyPresentationStateStorage",
    "1.2.840.10008.5.1.4.1.1.20": "NuclearMedicineImageStorage",
    "1.2.840.10008.5.1.4.1.1.88.11": "BasicTextSRStorage",
    "1.2.840.10008.5.1.4.1.1.88.22": "EnhancedSRStorage",
    "1.2.840.10008.5.1.4.1.1.88.33": "ComprehensiveSRStorage",
    "1.2.840.10008.5.1.4.1.1.88.59": "KeyObjectSelectionDocumentStorage",
    "1.2.840.10008.5.1.4.1.1.88.67": "XRayRadiationDoseSRStorage",
    "1.2.840.10008.5.1.4.1.1.104.1": "EncapsulatedPDFStorage",
    "1.2.840.10008.5.1.4.1.1.128": "PositronEmissionTomographyImageStorage",
    "1.2.840.10008.5.1.4.1.1.481.2": "RTDoseStorage",
}

# Text der Zeile `# Used TransferSyntax: ...` im Datensatzteil von `dcmdump`
# (DCMTK 3.6.7, gegen die Toolbox geprueft).
TRANSFER_SYNTAX_LABELS = {
    "1.2.840.10008.1.2": "Little Endian Implicit",
    "1.2.840.10008.1.2.1": "Little Endian Explicit",
    "1.2.840.10008.1.2.4.50": "JPEG Baseline",
    "1.2.840.10008.1.2.4.70": "JPEG Lossless, Non-hierarchical, 1st Order Prediction",
    "1.2.840.10008.1.2.4.91": "JPEG 2000 (Lossless or Lossy)",
}

# Diese VRs kodiert Explicit VR Little Endian mit 2-Byte-Laenge und damit
# 8 Byte Elementkopf (PS3.5 Abschnitt 7.1.2) -- alle, die die Simulation
# innerhalb von Sequenzen ausgibt.
SHORT_HEADER_VRS = {"AE", "AS", "CS", "DA", "DS", "DT", "IS", "LO", "PN", "SH", "TM", "UI"}


def value_length(value: str) -> int:
    """Laenge des kodierten Werts, auf eine gerade Zahl aufgefuellt."""
    length = len(value.encode("utf-8"))

    return length + (length % 2)


def _info_line(
    indent: int, tag: str, vr: str, shown: str, length: str, vm: int, keyword: str,
) -> str:
    content = f"({tag.lower()}) {vr} {shown}"
    padding = " " * max(1, LINE_WIDTH - len(content))

    return f"{' ' * indent}{content}{padding}#{length:>4}, {vm} {keyword}"


def element_line(
    tag: str, vr: str, value: str | None, keyword: str,
    indent: int = 0, map_uid_names: bool = True,
) -> str:
    """Eine Elementzeile ohne Zeilenende, wie `dcmdump` sie druckt."""
    if value is None or value == "":
        return _info_line(indent, tag, vr, "(no value available)", "0", 0, keyword)

    vm = value.count("\\") + 1

    if vr == "UI" and map_uid_names and value in UID_NAMES:
        shown = "=" + UID_NAMES[value]
    else:
        shown = f"[{value}]"

    return _info_line(indent, tag, vr, shown, str(value_length(value)), vm, keyword)


Element = tuple[str, str, str | None, str]


def _element_size(vr: str, value: str | None) -> int:
    if vr not in SHORT_HEADER_VRS:
        raise ValueError(f"VR {vr} wird in Sequenzen nicht simuliert")

    return 8 + (value_length(value) if value else 0)


def sequence_lines(
    tag: str, keyword: str, items: list[list[Element]], indent: int = 0,
) -> list[str]:
    """Eine Sequenz mit expliziten Laengen, wie `dcmdump` sie fuer eine Datei
    in Explicit VR Little Endian druckt: Sequenzzeile, je Item eine Itemzeile,
    die Elemente des Items (zwei Ebenen eingerueckt), Item- und
    Sequenz-Ende. Die Laengen zaehlen 8 Byte Kopf je Element und Item. Das
    genaue Bild steht in tests/test_dump_format.py.
    """
    item_lengths = [sum(_element_size(vr, value) for _, vr, value, _ in item) for item in items]
    sequence_length = sum(8 + length for length in item_lengths)

    lines = [
        _info_line(
            indent, tag, "SQ", f"(Sequence with explicit length #={len(items)})",
            str(sequence_length), 1, keyword,
        ),
    ]

    for item, item_length in zip(items, item_lengths, strict=True):
        lines.append(_info_line(
            indent + 2, "fffe,e000", "na", f"(Item with explicit length #={len(item)})",
            str(item_length), 1, "Item",
        ))
        lines += [
            element_line(sub_tag, vr, value, sub_keyword, indent=indent + 4)
            for sub_tag, vr, value, sub_keyword in sorted(item, key=lambda e: e[0])
        ]
        lines.append(_info_line(
            indent + 2, "fffe,e00d", "na", "(ItemDelimitationItem for re-encoding)",
            "0", 0, "ItemDelimitationItem",
        ))

    lines.append(_info_line(
        indent, "fffe,e0dd", "na", "(SequenceDelimitationItem for re-encod.)",
        "0", 0, "SequenceDelimitationItem",
    ))

    return lines
