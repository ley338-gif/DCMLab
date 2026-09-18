<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

/**
 * PR #153 (Assertion-Label-/Progress-Audit): Lektion 2.2 ("C-STORE: Bilder
 * senden und empfangen") zeigte direkt vor dem Lab `c-store-live` ein
 * Worked Example mit dem exakten Pfad des Lab-Datensatzes
 * (`daten/ct-thorax-60/`) -- ein Lernender, der die Lektion linear liest,
 * hatte die Loesung des unmittelbar folgenden Labs damit woertlich vor
 * Augen. Die Aenderung selbst wurde -- wie das Label auf `c-store-live`
 * (siehe CStoreLiveLabTest) -- ueber den echten Studio/ContentVersioning-
 * Pfad an der Lektion vorgenommen, nicht an einer Datei: Lesson-Content ist
 * seit ADR 0102 reiner DB-Content, `content/lessons/2.2/de.md` ist fuer
 * einen Studio-Publish irrelevant (siehe Lab-Content-Lifecycle-Audit,
 * PR #152). Es gibt deshalb -- genau wie fuer Lab-Content vor #152 --
 * keine Datei, gegen die ein Test laufen koennte.
 *
 * Diese Klasse haelt stattdessen fest, WAS an der Lektion 2.2 gelten soll:
 * das Worked Example lehrt weiterhin die `storescu`-Syntax fuer "einen
 * ganzen Ordner senden", nennt aber nicht mehr den konkreten Lab-Pfad. Live
 * am 2026-09-18 gegen den echten Docker/Postgres-Stack verifiziert (siehe
 * PR-Beschreibung) -- dieser Test dokumentiert die Erwartung als
 * ausfuehrbare Spezifikation, keine Live-DB-Pruefung.
 */
class Lesson22WorkedExampleTest extends TestCase
{
    public function test_the_whole_folder_example_no_longer_names_the_labs_concrete_dataset_path(): void
    {
        $codeBlock = $this->wholeFolderExampleCodeBlock();

        $this->assertStringNotContainsString('ct-thorax-60', $codeBlock);
        $this->assertStringContainsString('storescu -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242', $codeBlock);
    }

    public function test_the_explanatory_paragraph_no_longer_names_the_labs_specific_instance_count(): void
    {
        $paragraph = 'Ein ganzer Ordner lässt sich genauso mit einem einzigen Aufruf senden — storescu iteriert selbst über alle passenden Dateien.';

        $this->assertStringNotContainsString('60 Dateien', $paragraph);
        $this->assertStringContainsString('storescu', $paragraph);
    }

    private function wholeFolderExampleCodeBlock(): string
    {
        return "\$ storescu -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 daten/<datensatz>/\n\$ echo \$?\n0";
    }
}
