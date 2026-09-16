<?php

namespace App\Content\RichContent;

/**
 * CMS-7d.3 (ADR 0118): der eine Ort, an dem ein Legacy-`body`-Payload
 * (Markdown-String) zu `rich_content` (RichContentDocument) wird. Draft-
 * Bearbeitung, Publish, Restore und Draft-Preview rufen alle denselben
 * Normalizer auf, statt die Konvertierung an drei/vier Stellen getrennt
 * nachzubauen.
 *
 * Seit CMS-7d.3 liefert ein NEU angelegter Entwurf immer bereits
 * `rich_content` (der Editor schreibt kein `body` mehr) -- dieser
 * Normalizer greift praktisch nur noch fuer zwei Alt-Faelle: einen vor dem
 * Cutover angelegten, noch nicht veroeffentlichten Entwurf (`status`
 * draft/review) und eine historische, bereits veroeffentlichte Revision,
 * die per `restoreVersion()` wiederhergestellt wird. Beide Faelle bleiben
 * `payload.body` fuer immer, wie sie sind (ADR 0118: alte Revisionen werden
 * nie rueckwirkend umgeschrieben) -- der Normalizer wandelt nur die
 * KOPIE um, die tatsaechlich angewendet/gespeichert wird.
 */
final class LessonPayloadNormalizer
{
    public function __construct(
        private readonly MarkdownToRichContentConverter $converter = new MarkdownToRichContentConverter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        if (array_key_exists('rich_content', $payload)) {
            return $payload;
        }

        if (! is_string($payload['body'] ?? null)) {
            return $payload;
        }

        $payload['rich_content'] = $this->converter->convert($payload['body']);
        unset($payload['body']);

        return $payload;
    }
}
