<?php

namespace App\Console\Commands;

use App\Content\RichContent\RichContentValidator;
use App\Models\Lesson;
use App\Models\Node;
use Illuminate\Console\Command;

/**
 * CMS-7d.4: leichtgewichtiger Healthcheck fuer den Rich-Content-Cutover
 * (ADR 0118) -- "present" zaehlt nur, ob `rich_content` gesetzt ist,
 * "valid" prueft zusaetzlich jedes gesetzte Dokument gegen
 * `RichContentValidator`. Bewusst KEIN Wiederverwenden von
 * `rich-content:migrate`s schwererer Preflight-Arbeit (Markdown-
 * Konvertierung, Divergenz-Abgleich) -- das Dokument existiert hier
 * bereits, es muss nur gelesen und geprueft werden.
 *
 * Fuer eine Node wird jedes der drei `node_content`-Teildokumente
 * (`briefing`, jeder Eintrag in `hints`, `write_up`) einzeln validiert
 * (der Envelope selbst ist kein `{type: "doc", ...}`-Dokument) -- eine
 * Node zaehlt nur als "valid", wenn alle drei es sind.
 *
 * Rein lesend, kein `--apply`, kein Nebeneffekt auf DB oder `content/`.
 */
class RichContentCoverage extends Command
{
    protected $signature = 'rich-content:coverage';

    protected $description = 'Zaehlt, wie viele Lessons/Nodes rich_content gesetzt und valide haben (CMS-7d.4 Healthcheck)';

    public function handle(RichContentValidator $validator): int
    {
        $lessons = Lesson::query()->get(['id', 'rich_content']);
        $lessonsPresent = $lessons->filter(fn (Lesson $lesson) => $lesson->rich_content !== null);
        $lessonsValid = $lessonsPresent->filter(fn (Lesson $lesson) => $validator->validate($lesson->rich_content) === []);

        $nodes = Node::query()->get(['id', 'rich_content']);
        $nodesPresent = $nodes->filter(fn (Node $node) => $node->rich_content !== null);
        $nodesValid = $nodesPresent->filter(fn (Node $node) => $this->nodeRichContentIsValid($node, $validator));

        $this->line('Lessons:');
        $this->line("rich_content present: {$lessonsPresent->count()}/{$lessons->count()}");
        $this->line("rich_content valid:   {$lessonsValid->count()}/{$lessons->count()}");
        $this->newLine();
        $this->line('Nodes:');
        $this->line("rich_content present: {$nodesPresent->count()}/{$nodes->count()}");
        $this->line("rich_content valid:   {$nodesValid->count()}/{$nodes->count()}");

        return self::SUCCESS;
    }

    private function nodeRichContentIsValid(Node $node, RichContentValidator $validator): bool
    {
        $envelope = $node->rich_content;

        if (! is_array($envelope) || ! is_array($envelope['hints'] ?? null)) {
            return false;
        }

        if ($validator->validate($envelope['briefing'] ?? null) !== []) {
            return false;
        }

        if ($validator->validate($envelope['write_up'] ?? null) !== []) {
            return false;
        }

        foreach ($envelope['hints'] as $hint) {
            if ($validator->validate($hint) !== []) {
                return false;
            }
        }

        return true;
    }
}
