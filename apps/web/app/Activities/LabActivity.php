<?php

namespace App\Activities;

use App\Content\ContentIssue;
use App\Content\RichContent\RichContentValidator;
use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\User;

/**
 * Aktivitaetsvertrag fuer ein Lab (CMS-8a, Entscheidung C, ADR-Nachtrag zu
 * 0096/0097/0099). Anders als Node hat ein Lab kein content/**-Dateipendant
 * -- der komplette Zustand lebt in `labs` (Autorenkonfiguration, versioniert
 * ueber content_versions) und `lab_attempts` (Lernfortschritt). `serialize()`
 * schreibt deshalb nie Dateien (immer `[]`): `LabContentPublisher` uebernimmt
 * einen freigegebenen Entwurf direkt in die DB, ohne je ueber `ContentWriter`
 * zu laufen (siehe ActivityContentApplier).
 *
 * Runtime-Anbindung (Sandbox-Start, Terminal, Assertion-Auswertung) ist
 * bewusst NICHT Teil dieser Klasse -- das ist CMS-8b/8d. `result()` liefert
 * bereits jetzt ein korrektes, aber bis dahin immer "nicht abgeschlossen"
 * lautendes Ergebnis, weil noch nichts existiert, das ein Lab als geloest
 * markieren koennte.
 */
final readonly class LabActivity implements ActivityContract
{
    public function __construct(
        private Activity $activity,
        private Lab $lab,
    ) {}

    public function activityType(): string
    {
        return ActivityType::Lab->value;
    }

    public function key(): string
    {
        return $this->lab->slug;
    }

    public function supports(): ActivitySupports
    {
        return new ActivitySupports(
            isGraded: true,
            tracksCompletion: true,
            needsContainer: true,
            authorable: true,
            freelyPlaceable: true,
            runtimeType: 'container',
            versionable: true,
            // reusable: ein Lab kann wie eine Node ueber lesson_elements aus
            // mehreren Lektionen heraus verlinkt werden (nur als Launch-/
            // Status-Karte, siehe LearnerViewBuilder), ist selbst keiner
            // Lektion exklusiv zugeordnet.
            reusable: true,
        );
    }

    public function learnerView(User $user): array
    {
        $attempt = $this->attemptFor($user);

        return [
            'type' => $this->activityType(),
            'slug' => $this->lab->slug,
            'title' => $this->lab->title['de'] ?? '',
            'difficulty' => $this->lab->difficulty,
            'points' => $this->lab->points,
            'estimated_minutes' => $this->lab->estimated_minutes,
            'status' => $attempt === null ? 'not_started' : $attempt->status,
        ];
    }

    public function authorView(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => 'Titel', 'required' => true],
            ['key' => 'scenario_title', 'type' => 'text', 'label' => 'Szenario-Titel', 'required' => true],
            ['key' => 'difficulty', 'type' => 'text', 'label' => 'Schwierigkeit', 'required' => true],
            ['key' => 'points', 'type' => 'number', 'label' => 'Punkte', 'required' => true],
            ['key' => 'estimated_minutes', 'type' => 'number', 'label' => 'Dauer (Minuten)', 'required' => true],
            ['key' => 'runtime_template', 'type' => 'text', 'label' => 'Runtime-Vorlage', 'required' => false],
            ['key' => 'dataset', 'type' => 'text', 'label' => 'Datensatz', 'required' => false],
            ['key' => 'assertions', 'type' => 'json', 'label' => 'Assertions', 'required' => false],
            ['key' => 'rich_content', 'type' => 'richtext', 'label' => 'Anleitung', 'required' => true],
        ];
    }

    /**
     * Kein Dateibestand, gegen den geprueft werden koennte -- die Pruefung
     * ist deshalb eine kleine, in sich geschlossene Schema-Kontrolle
     * (Pflichtfelder, Assertion-Grundform, RichContentValidator fuer die
     * Anleitung), keine Teilnahme an ContentValidator::validate() wie bei
     * Node/Lesson (das ist auf den content/**-Baum zugeschnitten). Ohne
     * $draft wird der aktuelle DB-Zustand geprueft.
     */
    public function validate(?array $draft = null): array
    {
        $fields = $draft ?? $this->deserialize();
        $file = "labs/{$this->lab->slug}";
        $issues = [];

        foreach (['title', 'scenario_title', 'difficulty'] as $key) {
            if (! is_string($fields[$key] ?? null) || $fields[$key] === '') {
                $issues[] = new ContentIssue($file, null, "{$key}: muss ein nicht-leerer String sein");
            }
        }

        foreach (['points', 'estimated_minutes'] as $key) {
            if (! is_int($fields[$key] ?? null) || $fields[$key] < 0) {
                $issues[] = new ContentIssue($file, null, "{$key}: muss eine nicht-negative Zahl sein");
            }
        }

        $assertions = $fields['assertions'] ?? [];

        if (! is_array($assertions) || ! array_is_list($assertions)) {
            $issues[] = new ContentIssue($file, null, 'assertions: muss eine Liste sein');
        } else {
            foreach ($assertions as $index => $assertion) {
                if (! is_array($assertion) || ! is_string($assertion['type'] ?? null) || $assertion['type'] === '') {
                    $issues[] = new ContentIssue($file, null, "assertions[{$index}].type: muss ein nicht-leerer String sein");
                }
            }
        }

        $richContent = $fields['rich_content'] ?? null;

        if ($richContent === null) {
            $issues[] = new ContentIssue($file, null, 'rich_content: darf nicht leer sein');
        } else {
            foreach ((new RichContentValidator)->validate($richContent) as $message) {
                $issues[] = new ContentIssue($file, null, "rich_content.{$message}");
            }
        }

        return $issues;
    }

    /**
     * Ein Lab hat kein content/**-Dateipendant -- es wird nie ueber
     * ContentWriter geschrieben (ActivityContentApplier haengt es an
     * LabContentPublisher, siehe Klassendoc). Immer `[]`.
     */
    public function serialize(?array $draft = null): array
    {
        return [];
    }

    public function deserialize(): array
    {
        return [
            'slug' => $this->lab->slug,
            'title' => $this->lab->title['de'] ?? '',
            'scenario_title' => $this->lab->scenario_title['de'] ?? '',
            'difficulty' => $this->lab->difficulty,
            'points' => $this->lab->points,
            'estimated_minutes' => $this->lab->estimated_minutes,
            'runtime_template' => $this->lab->runtime_template,
            'dataset' => $this->lab->dataset,
            'assertions' => $this->lab->assertions,
            'rich_content' => $this->lab->rich_content,
        ];
    }

    /**
     * Bis CMS-8d existiert kein Mechanismus, der ein Lab als geloest
     * markieren koennte -- ein Attempt wird zwar schon angelegt (sobald die
     * Learner-Route existiert, CMS-8d), sein Status bleibt bis dahin aber
     * technisch immer "started". Der Vertrag ist trotzdem schon jetzt
     * korrekt: sobald ein Attempt echt geloest wird, liest result() das
     * sofort mit.
     */
    public function result(User $user): ?ActivityResult
    {
        $attempt = $this->attemptFor($user);

        if ($attempt === null) {
            return null;
        }

        return new ActivityResult(
            completed: $attempt->status === 'solved',
            score: $attempt->status === 'solved' ? $this->lab->points : null,
            maxScore: $this->lab->points,
            completedAt: $attempt->completed_at,
        );
    }

    private function attemptFor(User $user): ?LabAttempt
    {
        return LabAttempt::query()
            ->where('user_id', $user->id)
            ->where('activity_id', $this->activity->id)
            ->first();
    }
}
