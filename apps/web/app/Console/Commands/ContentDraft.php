<?php

namespace App\Console\Commands;

use App\Activities\ActivityRegistry;
use App\Console\Commands\Concerns\CreatesContentDrafts;
use App\Content\ContentVersioningService;
use App\Content\LessonDraftImporter;
use App\Models\Activity;
use App\Models\Lesson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Legt einen ausserhalb von `content/` geschriebenen Lektionsentwurf als
 * Studio-Entwurf an, ohne Abtippen im Editor (Vorschlag aus
 * `docs/lektions-backlog.md`). Derselbe Weg wie Lesson- und Quiz-Editor:
 * `LessonActivity::validate()`, dann `ContentVersioningService::
 * createDraft()` -- ein Lektionsfeld-Entwurf und, falls die Datei ein Quiz
 * enthaelt, getrennt ein `quiz`-Entwurf.
 *
 * Bewusst NICHT: einreichen, veroeffentlichen, `content/` schreiben. Der
 * einzige Weg zu `published` bleibt Studio-Review (Reviewer != Autor) und
 * danach `make content-export` (ADR 0122) -- die Datei wird dadurch nicht
 * wieder zum zweiten Schreibpfad.
 *
 * Supersession (ADR 0121): ein offener Entwurf/Review derselben Lektion
 * wuerde durch `createDraft()` auf `superseded` gesetzt. Der Befehl nennt
 * ihn deshalb und fragt nach; ohne Interaktion bricht er ab, ausser mit
 * `--supersede`.
 *
 * Node-Gegenstueck: `content:draft-node` (`ContentDraftNode`).
 */
class ContentDraft extends Command
{
    use CreatesContentDrafts;

    protected $signature = 'content:draft
        {lesson : Lesson-ID, z. B. 2.2}
        {--from= : Pfad zur de.md des Entwurfs (Frontmatter + Body inklusive ## Quiz)}
        {--meta= : Optional: Pfad zu einer meta.yml (Metadaten, quiz type/answer); ohne gilt der Live-Stand}
        {--author= : Nutzer-ID oder E-Mail des Autors (braucht Bearbeitungsrecht an der Lektion)}
        {--part= : lesson oder quiz -- noetig, wenn beide Teile abweichen (pro Lektion nur ein offener Entwurf, ADR 0121)}
        {--supersede : Offene Entwuerfe/Reviews dieser Lektion ohne Rueckfrage ersetzen}';

    protected $description = 'Legt einen Lektionsentwurf aus einer Datei als Studio-Entwurf an -- veroeffentlicht nie';

    public function handle(LessonDraftImporter $importer, ActivityRegistry $registry, ContentVersioningService $versions): int
    {
        $lesson = Lesson::query()->where('lesson_id', (string) $this->argument('lesson'))->first();
        $activity = $lesson === null ? null : Activity::query()->where('type', 'lesson')->where('key', $lesson->lesson_id)->first();

        if ($lesson === null || $activity === null) {
            $this->error("Lektion \"{$this->argument('lesson')}\" existiert nicht.");

            return self::FAILURE;
        }

        $author = $this->draftAuthor();

        if ($author === null) {
            return self::FAILURE;
        }

        if (! Gate::forUser($author)->allows('update', $activity)) {
            $this->error("{$author->email} darf Lektion {$lesson->lesson_id} nicht bearbeiten (ActivityPolicy::update).");

            return self::FAILURE;
        }

        $markdown = $this->readOption('from', required: true);
        $meta = $this->readOption('meta', required: false);

        if ($markdown === null || ($this->option('meta') && $meta === null)) {
            return self::FAILURE;
        }

        $part = $this->option('part');

        if ($part !== null && ! in_array($part, ['lesson', 'quiz'], true)) {
            $this->error('--part muss "lesson" oder "quiz" sein.');

            return self::FAILURE;
        }

        try {
            $payloads = $importer->build($lesson, $markdown, $meta, $part);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $drafts = array_filter($payloads, fn (?array $payload): bool => $payload !== null);

        if ($drafts === []) {
            $this->info("Keine Aenderung gegenueber dem Live-Stand von Lektion {$lesson->lesson_id} -- kein Entwurf angelegt.");

            return self::SUCCESS;
        }

        // Lektionsfelder und Quiz teilen sich die Lesson-Activity, und pro
        // Activity gibt es hoechstens einen offenen Entwurf (ADR 0121) --
        // ein zweiter createDraft() wuerde den ersten sofort superseden.
        if (count($drafts) > 1) {
            $this->error(
                'Lektionsfelder UND Quiz weichen vom Live-Stand ab. Pro Lektion ist nur ein offener Entwurf moeglich (ADR 0121): '
                .'erst --part=lesson anlegen und in Studio veroeffentlichen, danach --part=quiz.',
            );

            return self::FAILURE;
        }

        $issues = [];
        foreach ($drafts as $payload) {
            $issues = [...$issues, ...$registry->resolve($activity)->validate($payload)];
        }

        if ($issues !== []) {
            $this->reportInvalidDraft($issues);

            return self::FAILURE;
        }

        if (! $this->mayReplaceOpenVersions($activity, 'dieser Lektion')) {
            return self::FAILURE;
        }

        foreach ($drafts as $kind => $payload) {
            $version = $versions->createDraft($activity, $payload, $author);
            $this->info(sprintf('Entwurf #%d angelegt (%s, Lektion %s, Autor %s).', $version->id, $kind === 'quiz' ? 'Quiz' : 'Lektionsfelder', $lesson->lesson_id, $author->email));
        }

        $this->line('Nicht eingereicht, nicht veroeffentlicht: weiter in Studio (Review durch eine zweite Rolle), danach make content-export.');

        return self::SUCCESS;
    }
}
