<?php

namespace Tests\Unit\Content;

use App\Content\AnswerGrader;
use Tests\TestCase;

/**
 * Geteilte Bewertungslogik zwischen Lektions-Quiz (single/multi/input,
 * P10.59) und Track-Abschlusspruefung (zusaetzlich truefalse, P10.60).
 */
class AnswerGraderTest extends TestCase
{
    public function test_single_choice_matches_by_index(): void
    {
        $this->assertTrue(AnswerGrader::isCorrect('single', 1, 1));
        $this->assertTrue(AnswerGrader::isCorrect('single', '1', 1));
        $this->assertFalse(AnswerGrader::isCorrect('single', 0, 1));
    }

    public function test_multi_choice_is_order_independent(): void
    {
        $this->assertTrue(AnswerGrader::isCorrect('multi', [2, 0], [0, 2]));
        $this->assertFalse(AnswerGrader::isCorrect('multi', [0, 1], [0, 2]));
        $this->assertFalse(AnswerGrader::isCorrect('multi', 'not-an-array', [0, 2]));
    }

    public function test_truefalse_matches_boolean_exactly(): void
    {
        $this->assertTrue(AnswerGrader::isCorrect('truefalse', true, true));
        $this->assertTrue(AnswerGrader::isCorrect('truefalse', false, false));
        $this->assertFalse(AnswerGrader::isCorrect('truefalse', true, false));
        // Kein laxer Vergleich: 1/0/"true" duerfen nicht wie ein Boolean zaehlen.
        $this->assertFalse(AnswerGrader::isCorrect('truefalse', 1, true));
        $this->assertFalse(AnswerGrader::isCorrect('truefalse', 'true', true));
    }

    public function test_input_is_case_insensitive_and_trimmed(): void
    {
        $this->assertTrue(AnswerGrader::isCorrect('input', '  DICM  ', 'dicm'));
        $this->assertFalse(AnswerGrader::isCorrect('input', 'dic m', 'dicm'));
    }

    public function test_unknown_type_is_never_correct(): void
    {
        $this->assertFalse(AnswerGrader::isCorrect('exotic', 1, 1));
    }
}
