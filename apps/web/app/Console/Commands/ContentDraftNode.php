<?php

namespace App\Console\Commands;

use App\Activities\ActivityRegistry;
use App\Concerns\NodeDraftValidationRules;
use App\Console\Commands\Concerns\CreatesContentDrafts;
use App\Content\ContentVersioningService;
use App\Content\NodeDraftImporter;
use App\Models\Activity;
use App\Models\Node;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Node-Gegenstueck zu `content:draft`: legt einen ausserhalb von `content/`
 * geschriebenen Node-Text als Studio-Entwurf an. Eine veroeffentlichte Node
 * rendert aus `rich_content` (ADR 0118), eine Aenderung an
 * `content/nodes/<slug>/de.md` erreicht sie nicht mehr -- ohne diesen Befehl
 * bleibt nur Abtippen im Editor.
 *
 * Derselbe Weg wie der Studio-Node-Editor: dessen Formregeln, dann
 * `NodeActivity::validate()`, dann `ContentVersioningService::
 * createDraft()`. Eingabe ist das Format von `content:export`; ein Export
 * des Live-Stands, unveraendert zurueckgegeben, legt nichts an.
 *
 * Bewusst NICHT: einreichen, veroeffentlichen, `content/` schreiben,
 * `environment`/`flag` anfassen. Supersession wie bei `content:draft`
 * (ADR 0121): nachfragen, ohne Interaktion abbrechen, ausser mit
 * `--supersede`.
 */
class ContentDraftNode extends Command
{
    use CreatesContentDrafts;
    use NodeDraftValidationRules;

    protected $signature = 'content:draft-node
        {node : Node-Slug, z. B. gefiltert}
        {--from= : Pfad zur de.md des Entwurfs (Frontmatter + ## Briefing, ## Hints, ## Write-up)}
        {--meta= : Optional: Pfad zu einer node.yml (Studio-Felder, hints id/cost); ohne gilt der Live-Stand}
        {--author= : Nutzer-ID oder E-Mail des Autors (braucht Bearbeitungsrecht an der Node)}
        {--supersede : Offene Entwuerfe/Reviews dieser Node ohne Rueckfrage ersetzen}';

    protected $description = 'Legt einen Node-Entwurf aus einer Datei als Studio-Entwurf an -- veroeffentlicht nie';

    public function handle(NodeDraftImporter $importer, ActivityRegistry $registry, ContentVersioningService $versions): int
    {
        $node = Node::query()->where('slug', (string) $this->argument('node'))->first();
        $activity = $node === null ? null : Activity::query()->where('type', 'node')->where('key', $node->slug)->first();

        if ($node === null || $activity === null) {
            $this->error("Node \"{$this->argument('node')}\" existiert nicht.");

            return self::FAILURE;
        }

        $author = $this->draftAuthor();

        if ($author === null) {
            return self::FAILURE;
        }

        if (! Gate::forUser($author)->allows('update', $activity)) {
            $this->error("{$author->email} darf Node {$node->slug} nicht bearbeiten (ActivityPolicy::update).");

            return self::FAILURE;
        }

        $markdown = $this->readOption('from', required: true);
        $meta = $this->readOption('meta', required: false);

        if ($markdown === null || ($this->option('meta') && $meta === null)) {
            return self::FAILURE;
        }

        try {
            $payload = $importer->build($node, $markdown, $meta);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($payload === null) {
            $this->info("Keine Aenderung gegenueber dem Live-Stand von Node {$node->slug} -- kein Entwurf angelegt.");

            return self::SUCCESS;
        }

        // Erst die Formregeln, wie im Editor: scheitern sie, kommt der
        // Entwurf dort gar nicht bis zu validate().
        $issues = Validator::make($payload, $this->nodeDraftRules())->errors()->all();

        if ($issues === []) {
            $issues = $registry->resolve($activity)->validate($payload);
        }

        if ($issues !== []) {
            $this->reportInvalidDraft($issues);

            return self::FAILURE;
        }

        if (! $this->mayReplaceOpenVersions($activity, 'dieser Node')) {
            return self::FAILURE;
        }

        $version = $versions->createDraft($activity, $payload, $author);
        $this->info(sprintf('Entwurf #%d angelegt (Node %s, Autor %s).', $version->id, $node->slug, $author->email));
        $this->line('Nicht eingereicht, nicht veroeffentlicht: weiter in Studio (Review durch eine zweite Rolle), danach make content-export.');

        return self::SUCCESS;
    }
}
