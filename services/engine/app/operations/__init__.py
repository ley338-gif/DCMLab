"""PACS-Operations-Module (ADR 0120, Phase A: Object & Routing Foundation).

Kein neuer Service (ADR 0120, 5.1 "Module vor Services") -- reine
Python-Module neben `rules.py`, nach demselben Muster wie `find.py`
(ADR 0011/P10.1): pure Funktionen ueber dem Session-`state`-Dict, keine
eigene Persistenz, kein eigener Prozess.
"""
