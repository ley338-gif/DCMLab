<?php

namespace App\Content;

use App\Content\RichContent\LessonPayloadNormalizer;
use App\Content\RichContent\RichContentToMarkdownSerializer;
use App\Models\Lesson;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Baut aus einer ausserhalb von `content/` geschriebenen Lektionsdatei
 * dieselben zwei Entwurfs-Payloads, die Studio anlegt (`content:draft`,
 * Vorschlag aus `docs/lektions-backlog.md`): einen Lektionsfeld-Entwurf wie
 * `LessonEditorController::validatedFields()` und -- getrennt, wie im
 * Quiz-Editor -- einen `quiz`-Entwurf. Reiner Rechenschritt ohne
 * DB-Schreibzugriff; angelegt wird im Befehl.
 *
 * Die Datei ist nur Eingabe fuer einen Entwurf, nie Wahrheit: veroeffentlicht
 * wird ausschliesslich ueber Studio-Review (ADR 0122). Felder, die die Datei
 * nicht setzt, kommen aus dem aktuellen Live-Stand der Lektion.
 */
final class LessonDraftImporter
{
    private const META_FIELDS = ['level', 'duration_minutes', 'tools', 'requires', 'glossary_terms', 'sandbox', 'related_node'];

    public function __construct(
        private readonly RichContentToMarkdownSerializer $serializer = new RichContentToMarkdownSerializer,
    ) {}

    /**
     * @return array{lesson: array<string, mixed>|null, quiz: array{quiz: list<array{id: string, type: string, answer: mixed, question: string, options: list<string>}>}|null}
     *                                                                                                                                                                         `null` je Teil = inhaltlich identisch mit dem Live-Stand, kein Entwurf noetig
     */
    public function build(Lesson $lesson, string $markdown, ?string $metaYaml = null, ?string $part = null): array
    {
        $markdown = str_replace("\r\n", "\n", $markdown);
        $frontMatter = FrontMatter::parse($markdown);
        $attributes = $frontMatter['attributes'];
        $meta = $metaYaml !== null ? (Yaml::parse($metaYaml) ?? []) : [];

        if (! is_array($meta)) {
            throw new RuntimeException('meta.yml ist kein YAML-Objekt.');
        }

        $split = QuizContent::splitBody($frontMatter['body']);
        $prose = trim($split['after'] !== '' ? $split['before']."\n\n".$split['after'] : $split['before']);

        if ($prose === '') {
            throw new RuntimeException('Die Datei enthaelt keinen Fliesstext (Frontmatter oder Body fehlt).');
        }

        $lessonPayload = [
            'title' => (string) ($attributes['title'] ?? $lesson->title['de'] ?? ''),
            'teaser' => (string) ($attributes['teaser'] ?? $lesson->teaser['de'] ?? ''),
            'objectives' => array_values($attributes['objectives'] ?? $lesson->objectives ?? []),
        ];

        foreach (self::META_FIELDS as $field) {
            $lessonPayload[$field] = array_key_exists($field, $meta) ? $meta[$field] : $lesson->{$field};
        }

        // Wie der Editor: Listen-Eintraege sind immer Strings -- ein
        // ungequotetes `requires: [2.1]` in meta.yml kaeme sonst als Float an.
        foreach (['tools', 'requires', 'glossary_terms'] as $field) {
            $lessonPayload[$field] = array_values(array_map('strval', (array) ($lessonPayload[$field] ?? [])));
        }

        $lessonPayload['sandbox'] = ContentFieldComparison::sandbox($lessonPayload['sandbox']) + ['dataset' => null, 'note' => null];
        $lessonPayload['related_node'] = ContentFieldComparison::relatedNode($lessonPayload['related_node']);
        $lessonPayload['rich_content'] = (new LessonPayloadNormalizer)->normalize(['body' => $prose])['rich_content'];

        // Nur den angeforderten Teil pruefen -- ein unpassender Quiz-Abschnitt
        // darf einen reinen Lektionsfeld-Entwurf nicht blockieren (und umgekehrt).
        return [
            'lesson' => $part === 'quiz' || $this->sameAsLive($lesson, $lessonPayload) ? null : $lessonPayload,
            'quiz' => $part === 'lesson' ? null : $this->quizPayload($lesson, $split['quiz_raw'], $meta),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{quiz: list<array{id: string, type: string, answer: mixed, question: string, options: list<string>}>}|null
     */
    private function quizPayload(Lesson $lesson, string $quizRaw, array $meta): ?array
    {
        $texts = QuizContent::parseRawQuestions($quizRaw);

        if ($texts === []) {
            return null;
        }

        /** @var list<array<string, mixed>> $quizMeta */
        $quizMeta = array_key_exists('quiz', $meta) ? ($meta['quiz'] ?? []) : ($lesson->quiz ?? []);
        $metaById = [];
        foreach ($quizMeta as $entry) {
            $metaById[(string) ($entry['id'] ?? '')] = $entry;
        }

        $missing = array_diff(array_keys($texts), array_keys($metaById));
        $orphans = array_diff(array_keys($metaById), array_keys($texts));

        if ($missing !== [] || $orphans !== []) {
            throw new RuntimeException(
                'Quiz passt nicht zu den Antworten: '
                .($missing !== [] ? 'ohne type/answer: '.implode(', ', $missing).'. ' : '')
                .($orphans !== [] ? 'ohne Fragetext: '.implode(', ', $orphans).'. ' : '')
                .'Fragetext steht in der Datei, type/answer in meta.yml (--meta) bzw. im Live-Stand.',
            );
        }

        $questions = [];
        foreach ($texts as $id => $text) {
            $questions[] = [
                'id' => $id,
                'type' => (string) $metaById[$id]['type'],
                'answer' => $metaById[$id]['answer'] ?? null,
                'question' => $text['question'],
                'options' => $text['options'],
            ];
        }

        $liveMeta = array_map(fn (array $q): array => ['id' => (string) $q['id'], 'type' => (string) $q['type'], 'answer' => $q['answer'] ?? null], $lesson->quiz ?? []);
        $newMeta = array_map(fn (array $q): array => ['id' => $q['id'], 'type' => $q['type'], 'answer' => $q['answer']], $questions);
        $liveTexts = QuizContent::parseRawQuestions(QuizContent::splitBody(str_replace("\r\n", "\n", (string) $lesson->body))['quiz_raw']);

        if (ContentFieldComparison::same($liveMeta, $newMeta) && ContentFieldComparison::same($liveTexts, $texts)) {
            return null;
        }

        return ['quiz' => $questions];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sameAsLive(Lesson $lesson, array $payload): bool
    {
        // Noch nicht migrierte Lektion: dieselbe Legacy-Prosa, die
        // LessonActivity::deserialize() dem Editor als Startwert gibt.
        $split = QuizContent::splitBody(str_replace("\r\n", "\n", (string) $lesson->body));
        $liveRichContent = $lesson->rich_content
            ?? (new LessonPayloadNormalizer)->normalize(['body' => trim($split['before']."\n\n".$split['after'])])['rich_content'];

        if (! $this->serializer->equivalent($liveRichContent, $payload['rich_content'])) {
            return false;
        }

        foreach (['title', 'teaser'] as $field) {
            if (! ContentFieldComparison::same($lesson->{$field}['de'] ?? '', $payload[$field])) {
                return false;
            }
        }

        foreach (['objectives', 'level', 'duration_minutes', 'tools', 'requires', 'glossary_terms'] as $field) {
            if (! ContentFieldComparison::same($lesson->{$field} ?? [], $payload[$field])) {
                return false;
            }
        }

        return ContentFieldComparison::same(ContentFieldComparison::sandbox($lesson->sandbox), ContentFieldComparison::sandbox($payload['sandbox']))
            && ContentFieldComparison::same(ContentFieldComparison::relatedNode($lesson->related_node), $payload['related_node']);
    }
}
