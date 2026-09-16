<?php

namespace App\Console\Commands;

use App\Content\ContentRepository;
use App\Content\MarkdownRenderer;
use App\Content\NodeSections;
use App\Content\QuizContent;
use App\Content\RichContent\MarkdownToRichContentConverter;
use App\Content\RichContent\RichContentRenderer;
use App\Content\RichContent\RichContentValidator;
use Illuminate\Console\Command;

/**
 * CMS-7d.1 (ADR 0115): Dry-Run vor jeder Rich-Content-Migration. Prueft
 * jede Lektion und jede Node, ob ihr aktueller Markdown-Body verlustfrei
 * (ADR 0111/0112) zu einem Rich-Content-Dokument werden kann -- liest nur,
 * schreibt nichts, aendert keine Datenbank und keine content/-Datei.
 * Gegenstueck zu `content:validate` (dort das Schema fuer content/ selbst,
 * hier das Schema-Ziel fuer die Migration).
 *
 * Lektionen: nur der Prosa-Teil vor dem Quiz (`QuizContent::splitBody()
 * ['before']`) wird geprueft -- der Quiz-Abschnitt bleibt strukturierte
 * Markdown-Syntax, kein Rich-Content-Ziel (CMS-7d Betreiberauftrag).
 *
 * Nodes: `NodeSections::parse()` zerlegt den Body zuerst in Briefing/
 * Hints (pro Hint-Id)/Write-up -- jeder Abschnitt wird EINZELN geprueft,
 * weil CMS-7d.2 sie als eigene RichContentDocument-Felder im
 * `node_content`-Umschlag speichert, keinen gemeinsamen Fliesstext.
 *
 * Blockierend (Auftrag: "die Migration soll mit genauer Fundstelle
 * blockieren, bis das Konstrukt semantisch modelliert oder manuell
 * bereinigt wurde") sind: Validator-Verstoesse gegen das Rich-Content-
 * Schema, sowie alles, was `MarkdownToRichContentConverter::skippedNodes()`
 * nach dem Konvertieren meldet -- unbekanntes rohes HTML, horizontale
 * Trennlinien, Bilder, jeder sonst unbehandelte Knotentyp. Kein
 * `raw_html`-Fallback wird eingefuehrt, um das zu umgehen.
 *
 * Nicht blockierend: eine Abweichung im reinen Textinhalt zwischen dem
 * bestehenden `MarkdownRenderer`-Rendering und dem `RichContentRenderer`-
 * Rendering desselben Abschnitts (Tag-Struktur/Whitespace duerfen
 * abweichen, siehe `textContent()`) -- nur als Hinweis, meist ohnehin
 * Folge eines bereits blockierend gemeldeten Funds im selben Abschnitt.
 *
 * Zeilenangaben: bei Lektionen die echte Zeile in `de.md` (`prose` ist ein
 * reiner Praefix von body, der `body_start_line`-Offset aus
 * `FrontMatter::parse()` macht daraus wieder die Dateizeile). Bei Node-
 * Abschnitten ist die Zeile relativ zum jeweiligen Abschnittsinhalt NACH
 * dem Entfernen der Ueberschrift durch `NodeSections::parse()` -- zusammen
 * mit Node-Slug und Abschnittsname trotzdem eine eindeutige Fundstelle,
 * nur ohne direkten Bezug zur Dateizeile.
 */
class RichContentAudit extends Command
{
    protected $signature = 'rich-content:audit';

    protected $description = 'Prueft, ob content/ verlustfrei zu Rich-Content migriert werden kann (Dry-Run, CMS-7d.1)';

    /**
     * @var list<array{subject: string, section: string, kind: string, line: int|null, detail: string}>
     */
    private array $blocking = [];

    /**
     * @var list<array{subject: string, section: string, detail: string}>
     */
    private array $info = [];

    public function handle(ContentRepository $content): int
    {
        $this->blocking = [];
        $this->info = [];

        $lessons = $content->lessons();
        $nodes = $content->nodes();

        $converter = new MarkdownToRichContentConverter;
        $validator = new RichContentValidator;
        $legacyRenderer = new MarkdownRenderer($content->glossary());
        $richRenderer = new RichContentRenderer($content->glossary());

        foreach ($lessons as $id => $lesson) {
            $body = is_string($lesson['body'] ?? null) ? $lesson['body'] : '';
            $prose = QuizContent::splitBody($body)['before'];
            $bodyStartLine = is_int($lesson['body_start_line'] ?? null) ? $lesson['body_start_line'] : 1;

            // "prose" ist ein reiner Praefix von body (QuizContent::splitBody()
            // schneidet nur ab dem Quiz weg), Zeilennummern relativ zu ihm
            // sind deshalb auch relativ zum ganzen body -- der Offset macht
            // daraus die echte Zeile in der Quelldatei (siehe FrontMatter).
            $this->auditSection("Lektion {$id}", 'prose', $prose, $converter, $validator, $legacyRenderer, $richRenderer, $bodyStartLine - 1);
        }

        foreach ($nodes as $slug => $node) {
            $body = is_string($node['body'] ?? null) ? $node['body'] : '';
            $sections = NodeSections::parse($body);

            $this->auditSection("Node {$slug}", 'briefing', $sections['briefing'], $converter, $validator, $legacyRenderer, $richRenderer);

            foreach ($sections['hints'] as $hintId => $hintBody) {
                $this->auditSection("Node {$slug}", "hints.{$hintId}", $hintBody, $converter, $validator, $legacyRenderer, $richRenderer);
            }

            $this->auditSection("Node {$slug}", 'write_up', $sections['write_up'], $converter, $validator, $legacyRenderer, $richRenderer);
        }

        return $this->report(count($lessons), count($nodes));
    }

    private function auditSection(
        string $subject,
        string $section,
        string $markdown,
        MarkdownToRichContentConverter $converter,
        RichContentValidator $validator,
        MarkdownRenderer $legacyRenderer,
        RichContentRenderer $richRenderer,
        int $lineOffset = 0,
    ): void {
        if (trim($markdown) === '') {
            return;
        }

        $doc = $converter->convert($markdown);

        foreach ($validator->validate($doc) as $issue) {
            $this->blocking[] = [
                'subject' => $subject,
                'section' => $section,
                'kind' => 'schema_verstoss',
                'line' => null,
                'detail' => $issue,
            ];
        }

        foreach ($converter->skippedNodes() as $skip) {
            $this->blocking[] = [
                'subject' => $subject,
                'section' => $section,
                'kind' => $skip['type'],
                'line' => $skip['line'] !== null ? $skip['line'] + $lineOffset : null,
                'detail' => $skip['snippet'],
            ];
        }

        $hasBlockingFindingHere = collect($this->blocking)
            ->contains(fn (array $finding): bool => $finding['subject'] === $subject && $finding['section'] === $section);

        if ($hasBlockingFindingHere) {
            // Ein bereits gemeldeter Verlust (unbekanntes HTML, Bild, ...)
            // erklaert eine Textabweichung von selbst -- kein zusaetzlicher,
            // redundanter Hinweis noetig.
            return;
        }

        $legacyText = $this->textContent($legacyRenderer->render($markdown));
        $richText = $this->textContent($richRenderer->render($doc));

        if ($legacyText !== $richText) {
            $this->info[] = [
                'subject' => $subject,
                'section' => $section,
                'detail' => 'Textinhalt weicht vom bisherigen Rendering ab.',
            ];
        }
    }

    /**
     * Ersetzt jedes Tag durch ein Leerzeichen statt es einfach zu entfernen
     * (`strip_tags()` allein wuerde z. B. aus `Uhr<br>Der` "UhrDer" machen,
     * ein reiner Rendering-Unterschied zwischen einem echten Zeilenumbruch
     * im Legacy-HTML und `<br>` im Rich-Content-HTML, keine inhaltliche
     * Abweichung) -- danach zaehlt nur noch der sichtbare Wortinhalt.
     */
    private function textContent(string $html): string
    {
        $text = html_entity_decode(preg_replace('/<[^>]+>/', ' ', $html) ?? $html, ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function report(int $lessonCount, int $nodeCount): int
    {
        if ($this->blocking === []) {
            $this->info(sprintf(
                'rich-content:audit — keine blockierenden Funde (%d Lektionen, %d Nodes geprueft).',
                $lessonCount,
                $nodeCount,
            ));

            $this->reportInfo();

            return self::SUCCESS;
        }

        foreach ($this->blocking as $finding) {
            $location = $finding['line'] !== null ? "Zeile {$finding['line']}" : 'Zeile unbekannt';
            $this->error("{$finding['subject']} [{$finding['section']}], {$location}, {$finding['kind']}: {$finding['detail']}");
        }

        $this->line('');
        $this->error(sprintf(
            '%d blockierende(r) Fund(e) — Migration darf erst starten, wenn diese semantisch modelliert oder manuell bereinigt sind.',
            count($this->blocking),
        ));

        $this->reportInfo();

        return self::FAILURE;
    }

    private function reportInfo(): void
    {
        if ($this->info === []) {
            return;
        }

        $this->line('');
        $this->warn(sprintf('%d nicht-blockierende(r) Hinweis(e) (Renderer-Textabweichung):', count($this->info)));

        foreach ($this->info as $entry) {
            $this->line(" - {$entry['subject']} [{$entry['section']}]: {$entry['detail']}");
        }
    }
}
