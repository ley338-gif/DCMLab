<?php

namespace App\Console\Commands\Concerns;

use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\User;
use Illuminate\Console\Command;
use Stringable;

/**
 * Gemeinsame Schutzregeln von `content:draft` (Lektion) und
 * `content:draft-node`: echte Autorschaft, Eingabe aus Dateien ausserhalb
 * von `content/`, keine Version bei Befunden, keine stille Supersession
 * (ADR 0121).
 *
 * @phpstan-require-extends Command
 */
trait CreatesContentDrafts
{
    private function draftAuthor(): ?User
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
     * @param  array<string|Stringable>  $issues  ContentIssue-Befunde oder Meldungen der Formregeln
     */
    private function reportInvalidDraft(array $issues): void
    {
        $this->error('Der Entwurf ist ungueltig -- nichts angelegt:');

        foreach ($issues as $issue) {
            $this->line("  - {$issue}");
        }
    }

    /**
     * ADR 0121: createDraft() setzt offene Versionen derselben Activity auf
     * `superseded` -- nie ohne Wissen dessen, der den Befehl ausfuehrt.
     *
     * @param  string  $resource  z. B. "dieser Lektion"
     */
    private function mayReplaceOpenVersions(Activity $activity, string $resource): bool
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
        $this->warn("Offene Version(en) {$resource}: {$list} -- ein neuer Entwurf setzt sie auf superseded (ADR 0121).");

        if ($this->confirm('Fortfahren?', false)) {
            return true;
        }

        $this->warn('Abgebrochen -- nichts angelegt. Mit --supersede ohne Rueckfrage ersetzen.');

        return false;
    }
}
