<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonElement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonElement>
 */
class LessonElementFactory extends Factory
{
    protected $model = LessonElement::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'type' => 'content',
            'position' => 0,
        ];
    }
}
