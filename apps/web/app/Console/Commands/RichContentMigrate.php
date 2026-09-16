<?php

namespace App\Console\Commands;

use App\Content\ContentRepository;
use App\Content\NodeSections;
use App\Content\QuizContent;
use App\Content\RichContent\MarkdownToRichContentConverter;
use App\Content\RichContent\RichContentValidator;
use App\Models\Lesson;
use App\Models\Node;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * CMS-7d.2 (ADR 0117): der eigentliche Backfill nach CMS-7d.1s Audit- und
 * Persistenz-Grundlage. Ohne `--apply` reiner Dry-Run (auch mit
 * `--dry-run` explizit, ein reines Synonym fuer "kein `--apply`") --
 * schreibt niemals etwas, egal welche Option gesetzt ist.
 *
 * Betreiber-Korrektur zum urspruenglichen CMS-7d.2-Plan: Quelle ist die
 * DB (`Lesson::body`/`Node::body`), NICHT `content/`. Seit ADR 0102/0108
 * schreiben `LessonContentPublisher`/`NodeContentPublisher` jede
 * Studio-Freigabe direkt in die DB-Spalte, nie zurueck nach `content/` --
 * `content/` kann danach absichtlich veraltet sein (siehe
 * `docs/offene-fragen.md`, `content:export` fehlt noch). `content/` dient
 * hier nur als Fallback fuer den (im echten Bestand seltenen) Fall, dass
 * `body` in der DB `null` ist. `rich-content:audit` (ADR 0115/0116)
 * bleibt wertvoll als fruehe, dateibasierte Kontrolle, ist aber KEIN
 * Ersatz fuer den Nachweis dieses Commands -- der laeuft eigenstaendig
 * gegen die tatsaechliche Migrationsquelle.
 *
 * Sicherheitsregeln (Betreiber-Vorgabe, keine Abstriche):
 * - JEDE Ressource (Lesson und Node, insgesamt 59 im echten Bestand)
 *   durchlaeuft Konverter + Validator + Skip-Pruefung, unabhaengig davon,
 *   ob ihr `rich_content` schon gesetzt ist oder nicht.
 * - Ist `rich_content` bereits gesetzt: gegen das neu berechnete
 *   Legacy-Dokument validiert UND verglichen. Ungueltig -> Abbruch.
 *   Weicht es ab ("diverged") -> ebenfalls Abbruch, niemals stilles
 *   Ueberschreiben.
 * - Ist AUCH NUR EINE Ressource nicht bereit (kein Body, Schema-Verstoss,
 *   uebersprungener Knoten, ungueltiges oder abweichendes bestehendes
 *   `rich_content`), schreibt der Befehl NICHTS -- auch nicht mit
 *   `--apply` -- und meldet alle Befunde.
 * - Erst wenn ALLE Ressourcen bereit sind, oeffnet `--apply` eine einzige
 *   DB-Transaktion und schreibt NUR Zeilen, deren `rich_content` zum
 *   Schreibzeitpunkt (per Row-Lock erneut geprueft) noch `NULL` ist. Kein
 *   `--force`, kein Weg, ein bestehendes `rich_content` zu ueberschreiben.
 *
 * Node bekommt den in ADR 0115 festgezogenen `node_content`-Umschlag
 * (Briefing/jeder Hint/Write-up als eigenes RichContentDocument);
 * Lesson bekommt ein einzelnes RichContentDocument (nur der Prosa-Teil
 * vor dem Quiz, wie in `rich-content:audit`).
 */
class RichContentMigrate extends Command
{
    protected $signature = 'rich-content:migrate {--apply : Tatsaechlich schreiben, statt nur zu pruefen} {--dry-run : Synonym fuer "kein --apply", nur zur Lesbarkeit}';

    protected $description = 'Backfuellt lessons.rich_content/nodes.rich_content aus der DB (CMS-7d.2) -- ohne --apply ein reiner Dry-Run';

    /**
     * @var list<array{resource: string, kind: string, reason: string}>
     */
    private array $failures = [];

    /**
     * @var list<array{model: Lesson|Node, document: array<string, mixed>}>
     */
    private array $pendingWrites = [];

    private int $toMigrate = 0;

    private int $alreadyPopulated = 0;

    public function handle(ContentRepository $content): int
    {
        $this->failures = [];
        $this->pendingWrites = [];
        $this->toMigrate = 0;
        $this->alreadyPopulated = 0;

        $converter = new MarkdownToRichContentConverter;
        $validator = new RichContentValidator;

        $lessons = Lesson::all();
        $nodes = Node::all();

        foreach ($lessons as $lesson) {
            $this->prepareLesson($lesson, $content, $converter, $validator);
        }

        foreach ($nodes as $node) {
            $this->prepareNode($node, $content, $converter, $validator);
        }

        $this->info(sprintf(
            'rich-content:migrate — %d Lektionen, %d Nodes geprueft.',
            $lessons->count(),
            $nodes->count(),
        ));
        $this->info(sprintf(
            '%d zu migrieren, %d bereits vorhanden.',
            $this->toMigrate,
            $this->alreadyPopulated,
        ));

        if ($this->failures !== []) {
            $this->line('');

            foreach ($this->failures as $failure) {
                $this->error("{$failure['resource']}: {$failure['kind']} -- {$failure['reason']}");
            }

            $this->line('');
            $this->error(sprintf(
                '%d Ressource(n) nicht bereit -- kein Schreibvorgang (0 geschrieben).',
                count($this->failures),
            ));

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info('Dry-run -- 0 geschrieben (--apply fuer den echten Schreibvorgang).');

            return self::SUCCESS;
        }

        $written = $this->writePending();

        $this->info("{$written} geschrieben.");

        return self::SUCCESS;
    }

    private function prepareLesson(
        Lesson $lesson,
        ContentRepository $content,
        MarkdownToRichContentConverter $converter,
        RichContentValidator $validator,
    ): void {
        $resource = "Lektion {$lesson->lesson_id}";
        $effectiveBody = $lesson->body ?? ($content->lessons()[$lesson->lesson_id]['body'] ?? null);

        if ($effectiveBody === null) {
            $this->recordFailure($resource, 'kein_body', 'weder Lesson.body (DB) noch content/ liefern einen Body');

            return;
        }

        $prose = QuizContent::splitBody($effectiveBody)['before'];
        $fresh = $this->convertSection($resource, 'prose', $prose, $converter, $validator);

        if ($fresh === null) {
            return;
        }

        $this->finalize(
            $resource,
            $lesson,
            $fresh,
            fn (mixed $existing): array => $validator->validate($existing),
        );
    }

    private function prepareNode(
        Node $node,
        ContentRepository $content,
        MarkdownToRichContentConverter $converter,
        RichContentValidator $validator,
    ): void {
        $resource = "Node {$node->slug}";
        $effectiveBody = $node->body ?? ($content->nodes()[$node->slug]['body'] ?? null);

        if ($effectiveBody === null) {
            $this->recordFailure($resource, 'kein_body', 'weder Node.body (DB) noch content/ liefern einen Body');

            return;
        }

        $sections = NodeSections::parse($effectiveBody);

        $briefing = $this->convertSection($resource, 'briefing', $sections['briefing'], $converter, $validator);
        $writeUp = $this->convertSection($resource, 'write_up', $sections['write_up'], $converter, $validator);

        $hints = [];
        $hintsOk = true;

        foreach ($sections['hints'] as $hintId => $hintBody) {
            $hintDoc = $this->convertSection($resource, "hints.{$hintId}", $hintBody, $converter, $validator);

            if ($hintDoc === null) {
                $hintsOk = false;

                continue;
            }

            $hints[$hintId] = $hintDoc;
        }

        if ($briefing === null || $writeUp === null || ! $hintsOk) {
            return;
        }

        $envelope = [
            'type' => 'node_content',
            'version' => 1,
            'briefing' => $briefing,
            'hints' => $hints,
            'write_up' => $writeUp,
        ];

        $this->finalize(
            $resource,
            $node,
            $envelope,
            fn (mixed $existing): array => $this->validateNodeContentEnvelope($existing, $validator),
        );
    }

    /**
     * Konvertiert+validiert EINEN Abschnitt (Lesson-Prosa oder ein
     * einzelner Node-Abschnitt) und meldet jeden Befund einzeln --
     * dieselbe Pruefung wie `rich-content:audit`, hier aber gegen die
     * DB-Quelle statt gegen `content/`.
     *
     * @return array<string, mixed>|null null, wenn der Abschnitt nicht
     *                                   bereit ist (Befund(e) bereits in
     *                                   $this->failures erfasst)
     */
    private function convertSection(
        string $resource,
        string $section,
        string $markdown,
        MarkdownToRichContentConverter $converter,
        RichContentValidator $validator,
    ): ?array {
        $doc = $converter->convert($markdown);
        $issues = $validator->validate($doc);
        $skips = $converter->skippedNodes();

        foreach ($issues as $issue) {
            $this->recordFailure($resource, 'schema_verstoss', "[{$section}] {$issue}");
        }

        foreach ($skips as $skip) {
            $line = $skip['line'] !== null ? "Zeile {$skip['line']}" : 'Zeile unbekannt';
            $this->recordFailure($resource, $skip['type'], "[{$section}] {$line}: {$skip['snippet']}");
        }

        return $issues === [] && $skips === [] ? $doc : null;
    }

    /**
     * Gemeinsamer Abschluss fuer Lesson und Node: neu und bestehend
     * vergleichen (nicht ueberschreiben), oder als "zu migrieren" vormerken.
     *
     * @param  array<string, mixed>  $fresh
     * @param  callable(mixed): list<string>  $validateExisting
     */
    private function finalize(string $resource, Lesson|Node $model, array $fresh, callable $validateExisting): void
    {
        $existing = $model->rich_content;

        if ($existing === null) {
            $this->toMigrate++;
            $this->pendingWrites[] = ['model' => $model, 'document' => $fresh];

            return;
        }

        $existingIssues = $validateExisting($existing);

        if ($existingIssues !== []) {
            $this->recordFailure($resource, 'invalid_existing', $existingIssues[0]);

            return;
        }

        if ($existing != $fresh) {
            $this->recordFailure($resource, 'diverged', 'gespeichertes rich_content weicht vom neu berechneten Legacy-Dokument ab');

            return;
        }

        $this->alreadyPopulated++;
    }

    /**
     * @return list<string>
     */
    private function validateNodeContentEnvelope(mixed $envelope, RichContentValidator $validator): array
    {
        if (! is_array($envelope)) {
            return ['rich_content: muss ein Objekt sein'];
        }

        $issues = [];

        if (($envelope['type'] ?? null) !== 'node_content') {
            $issues[] = 'rich_content.type: muss "node_content" sein';
        }

        if (($envelope['version'] ?? null) !== 1) {
            $issues[] = 'rich_content.version: muss 1 sein';
        }

        foreach (['briefing', 'write_up'] as $field) {
            if (! is_array($envelope[$field] ?? null)) {
                $issues[] = "rich_content.{$field}: muss ein RichContentDocument sein";

                continue;
            }

            foreach ($validator->validate($envelope[$field]) as $issue) {
                $issues[] = "{$field}.{$issue}";
            }
        }

        if (! is_array($envelope['hints'] ?? null)) {
            $issues[] = 'rich_content.hints: muss ein Objekt sein';
        } else {
            foreach ($envelope['hints'] as $hintId => $hintDoc) {
                if (! is_array($hintDoc)) {
                    $issues[] = "hints.{$hintId}: muss ein RichContentDocument sein";

                    continue;
                }

                foreach ($validator->validate($hintDoc) as $issue) {
                    $issues[] = "hints.{$hintId}.{$issue}";
                }
            }
        }

        return $issues;
    }

    private function recordFailure(string $resource, string $kind, string $reason): void
    {
        $this->failures[] = ['resource' => $resource, 'kind' => $kind, 'reason' => $reason];
    }

    /**
     * Eine einzige Transaktion fuer alle vorbereiteten Schreibvorgaenge --
     * pro Zeile ein Row-Lock (`lockForUpdate()`) und ein erneuter
     * NULL-Check unmittelbar vor dem Schreiben: der Preflight-Check
     * (Sekunden zuvor) ist keine Garantie mehr, sobald ein anderer
     * Prozess dieselbe Zeile in der Zwischenzeit befuellt haette.
     */
    private function writePending(): int
    {
        $written = 0;

        DB::transaction(function () use (&$written): void {
            foreach ($this->pendingWrites as $pending) {
                $model = $pending['model'];
                $locked = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->first();

                if ($locked === null || $locked->rich_content !== null) {
                    throw new RuntimeException(
                        "rich_content fuer {$model->getKeyName()}={$model->getKey()} war beim Schreiben nicht mehr NULL -- Migration abgebrochen, Transaktion zurueckgerollt.",
                    );
                }

                $locked->update(['rich_content' => $pending['document']]);
                $written++;
            }
        });

        return $written;
    }
}
