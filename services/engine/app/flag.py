"""Flag-Normalisierung (Abschnitt 5.2).

Muss bei jeder Aenderung synchron zu App\\Content\\FlagNormalizer (PHP,
apps/web/app/Content/FlagNormalizer.php) bleiben -- dort wird derselbe Wert
beim Build gehasht, hier wird die Eingabe des Lernenden gegen den Hash geprueft.
"""

from __future__ import annotations

import hashlib
import re


def normalize(value: str, case_sensitive: bool) -> str:
    value = value.strip()
    value = re.sub(r"\s+", " ", value)

    return value if case_sensitive else value.lower()


def hash_value(value: str, case_sensitive: bool) -> str:
    return hashlib.sha256(normalize(value, case_sensitive).encode("utf-8")).hexdigest()
