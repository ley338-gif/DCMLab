<?php

namespace App\Activities;

use App\Content\ContentIssue;
use App\Models\User;

/**
 * Der Aktivitaetsvertrag (ADR 0072): sechs Faehigkeiten, die jeder
 * Aktivitaetstyp -- Lektion, Quiz, Pruefung, Node, Spielwiese, und jeder
 * spaetere -- gegen einen gemeinsamen Kern erfuellt, statt als eigener
 * Sonderfall in Controllern und Services zu verzweigen. Vorbild: Moodles
 * Aktivitaetsmodule (`mod`) und Open edX' XBlocks (`student_view`,
 * `studio_view`, `has_score`).
 *
 * `authorView()`, `validate()`, `serialize()` und `deserialize()` beschreiben
 * bereits in W0 die volle Form des Vertrags, tragen aber noch keine eigene
 * Fachlogik: `validate()` liefert bewusst nichts, solange es noch keinen
 * wiederverwendbaren ContentValidator-Service gibt (W1) -- alles andere waere
 * die "zweite, schwaechere Pruefung", die ADR 0071 ausdruecklich verbietet.
 * `serialize()`/`deserialize()` spiegeln den heutigen Ist-Zustand aus
 * ContentRepository, bevor ContentWriter/ContentImporter (W2) daraus
 * tatsaechlich Dateien erzeugen bzw. Entwuerfe einlesen.
 */
interface ActivityContract
{
    /**
     * Der Type-String dieser Aktivitaet (`activities.type`). `ActivityType`
     * listet die heute bekannten Werte, ist aber keine geschlossene Menge --
     * ein neuer Typ liefert hier einfach seinen eigenen String und
     * registriert sich mit demselben Wert bei der ActivityRegistry (siehe
     * ActivityRegistryTest).
     */
    public function activityType(): string;

    /**
     * Fachlicher Schluessel dieser Instanz (lesson_id | Node-Slug |
     * Track-Slug), identisch mit `activities.key`.
     */
    public function key(): string;

    public function supports(): ActivitySupports;

    /**
     * Props fuer die Lernendenansicht (Inertia).
     *
     * @return array<string, mixed>
     */
    public function learnerView(User $user): array;

    /**
     * Feldschema fuer den Autoren-Editor (ADR 0071, W6).
     *
     * @return list<array{key: string, type: string, label: string, required: bool}>
     */
    public function authorView(): array;

    /**
     * `$draft` ist optional (ADR 0080, W6): ohne ihn wird der Ist-Zustand
     * geprueft (wie bisher), mit ihm ein noch nicht geschriebener Entwurf
     * -- z. B. `['quiz' => [...]]` fuer eine neue Fragenliste vor dem
     * Speichern. Nicht jeder Typ wertet jedes Feld eines Entwurfs aus; ein
     * Typ, der `$draft` nicht kennt, ignoriert ihn und prueft den
     * Ist-Zustand wie ohne Argument.
     *
     * @param  array<string, mixed>|null  $draft
     * @return list<ContentIssue>
     */
    public function validate(?array $draft = null): array;

    /**
     * Erzeugte Datei(en) unter content/** (ADR 0071/0080, W2/W6). `path`
     * ist relativ zu ContentRepository::basePath(). Leere Liste, wenn
     * dieser Typ keine eigene Datei hat (Quiz/Spielwiese haengen an ihrer
     * Lektion). Mit `$draft` wird aus dem Entwurf erzeugt statt aus dem
     * Ist-Zustand, siehe validate().
     *
     * @param  array<string, mixed>|null  $draft
     * @return list<array{path: string, contents: string}>
     */
    public function serialize(?array $draft = null): array;

    /**
     * Gegenstueck zum Importer: die aktuellen Nutzdaten dieser Instanz als
     * flaches, normalisiertes Feld-Array (die Form, die spaeter ein
     * content_drafts-Datensatz haette).
     *
     * @return array<string, mixed>
     */
    public function deserialize(): array;

    /**
     * Das dauerhafte Ergebnis eines Nutzers, oder `null` wenn dieser Typ
     * keinen Abschlusszustand kennt (siehe ActivitySupports::$tracksCompletion)
     * oder der Nutzer die Aktivitaet noch nicht begonnen hat.
     */
    public function result(User $user): ?ActivityResult;
}
