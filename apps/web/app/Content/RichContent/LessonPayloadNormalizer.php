<?php

namespace App\Content\RichContent;

use App\Content\QuizContent;

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
 *
 * Betreiber-Review vor #126 gefunden: ein Legacy-`payload['body']` ist NIE
 * die vollstaendige Prosa -- der VOR 7d.3 gueltige `LessonEditorController`
 * speicherte im Draft-Payload ausdrueckluch nur `QuizContent::
 * splitBody($lesson->body)['before']` (der Nach-Quiz-Fusstext, `after`,
 * war ueber KEINE UI editierbar und kam erst beim Rendern live aus
 * `Lesson::body` dazu, siehe `LessonController::show()` vor ADR 0118).
 * Ein Restore/Publish, das ein solches historisches `body` als KOMPLETTES
 * Dokument interpretiert, wuerde `after` deshalb unbemerkt verlieren.
 * `$currentBody` (die LIVE `Lesson::body`-Spalte im Moment der
 * Normalisierung) liefert genau dieses fehlende `after` nach -- `null`
 * (Default) bedeutet: der Aufrufer hat `before`+`after` bereits selbst
 * zusammengefuehrt (`LessonEditorController::currentFields()`/
 * `LessonActivity::deserialize()` tun das ueber ihr eigenes
 * `legacyProse()`), kein zweites Anhaengen noetig.
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
    public function normalize(array $payload, ?string $currentBody = null): array
    {
        if (array_key_exists('rich_content', $payload)) {
            return $payload;
        }

        if (! is_string($payload['body'] ?? null)) {
            return $payload;
        }

        $before = $payload['body'];
        $after = $currentBody !== null ? QuizContent::splitBody($currentBody)['after'] : '';
        $prose = trim($after !== '' ? $before."\n\n".$after : $before);

        $payload['rich_content'] = $this->converter->convert($prose);
        unset($payload['body']);

        return $payload;
    }
}
