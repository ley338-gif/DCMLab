# 0043 — P10.33: `services/sandbox`-Tests — zehn vorbestehende mypy-Fehler behoben

Status: akzeptiert
Datum: 2026-09-13

## Kontext

ADR 0042 (P10.32) vermerkte am Rande zehn vorbestehende mypy-Fehler in
`services/sandbox/tests/test_orchestrator.py`, unverändert von dieser
Slice betroffen. Der Fund war nirgends sonst festgehalten — ein
echter, aber untracked liegen gebliebener Punkt. Mit Track 2
vollständig abgeschlossen wurde dieser als nächste, klar abgegrenzte
und rein mechanische Aufgabe aufgegriffen (kein Content, keine
Sandbox-Verifikation nötig).

Zwei Fehlerklassen, beide ohne Bezug zur eigentlichen Programmlogik:
- Vier Fake-Funktionen und ein Testparameter trugen keine
  Typ-Annotationen (`no-untyped-def`).
- `SandboxView.sandbox_id` ist `str | None`
  (`services/sandbox/app/orchestrator.py:31`); mehrere Tests reichten
  diesen Wert ungeprüft an Funktionen weiter, die zwingend `str`
  erwarten (`delete_sandbox`, `get_sandbox`, `exec_command`).

## Entscheidung — Annotationen ergänzt, `str | None` an der Quelle verengt

**`services/sandbox/tests/test_orchestrator.py`**:
- `fake_build_session`, `fake_exec_command`, `fake_teardown_session`
  sowie der `_fake_docker_ops`-Parameter in
  `test_deleting_a_sandbox_promotes_the_next_queued_request` erhielten
  vollständige Typannotationen (die vorherigen `# noqa: ANN001` sind
  damit hinfällig und entfernt).
- An jeder Stelle, an der ein `SandboxView.sandbox_id` (`str | None`)
  weitergereicht wird, aber zwingend `str` gebraucht wird, steht jetzt
  ein `assert ... is not None` direkt nach der Erzeugung des Views —
  echte Testabsicherung (ein `None` an dieser Stelle wäre ein realer
  Fehler in `create_sandbox`), keine kosmetische Typumgehung.

## Manuell verifiziert

- `mypy .` in `services/sandbox`: „Success: no issues found in 13
  source files" (vorher 10 Fehler).
- `ruff check .`: unverändert „All checks passed!".
- `pytest -q`: unverändert 14 passed.
- `datasets/build`: unverändert 5 passed (nicht von dieser Slice
  betroffen, nur zur Regressionskontrolle erneut ausgeführt).

## Nicht Teil dieser Slice

- Kein Content geändert, keine Sandbox-Infrastruktur berührt.
