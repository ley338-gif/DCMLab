<?php

namespace App\Console\Commands;

use App\Activities\ActivityRegistry;
use App\Content\ContentVersioningService;
use App\Content\LessonDraftImporter;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lesson;
use App\Models\User;
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
 */
class ContentDraft extends Command
{
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

        $author = $this->author();

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
            $this->error('Der Entwurf ist ungueltig -- nichts angelegt:');
            foreach ($issues as $issue) {
                $this->line("  - {$issue}");
            }

            return self::FAILURE;
        }

        if (! $this->mayReplaceOpenVersions($activity)) {
            return self::FAILURE;
        }

        foreach ($drafts as $kind => $payload) {
            $version = $versions->createDraft($activity, $payload, $author);
            $this->info(sprintf('Entwurf #%d angelegt (%s, Lektion %s, Autor %s).', $version->id, $kind === 'quiz' ? 'Quiz' : 'Lektionsfelder', $lesson->lesson_id, $author->email));
        }

        $this->line('Nicht eingereicht, nicht veroeffentlicht: weiter in Studio (Review durch eine zweite Rolle), danach make content-export.');

        return self::SUCCESS;
    }

    private function author(): ?User
    {
        $value = $this->stringOption('author');

        if ($value === '') {
            $this->error('--author ist Pflicht (Nutzer-ID oder E-Mail) -- ein Entwurf braucht eine echte Autorschaft.');

            return null;
        }

        $author = ctype_digit($value)
            ? User::query()->find((int) $value)
            : User::query()->where('email', $value)->first();

        if ($author === null) {
            $this->error("Nutzer \"{$value}\" existiert nicht.");
        }

        return $author;
    }

    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : '';
    }

    private function readOption(string $option, bool $required): ?string
    {
        $path = $this->stringOption($option);

        if ($path === '') {
            if ($required) {
                $this->error("--{$option} ist Pflicht.");
            }

            return null;
        }

        if (! is_file($path)) {
            $this->error("Datei \"{$path}\" (--{$option}) existiert nicht.");

            return null;
        }

        return (string) file_get_contents($path);
    }

    /**
     * ADR 0121: createDraft() setzt offene Versionen derselben Activity auf
     * `superseded` -- nie ohne Wissen dessen, der den Befehl ausfuehrt.
     */
    private function mayReplaceOpenVersions(Activity $activity): bool
    {
        $open = ContentVersion::query()
            ->where('activity_id', $activity->id)
            ->whereIn('status', ['draft', 'review'])
            ->orderBy('id')
            ->get();

        if ($open->isEmpty() || $this->option('supersede')) {
            return true;
        }

        $list = $open->map(fn (ContentVersion $v): string => "#{$v->id} ({$v->status}, {$v->created_at})")->implode(', ');
        $this->warn("Offene Version(en) dieser Lektion: {$list} -- ein neuer Entwurf setzt sie auf superseded (ADR 0121).");

        if ($this->confirm('Fortfahren?', false)) {
            return true;
        }

        $this->warn('Abgebrochen -- nichts angelegt. Mit --supersede ohne Rueckfrage ersetzen.');

        return false;
    }
}
