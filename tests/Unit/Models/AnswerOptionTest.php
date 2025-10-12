<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\AnswerOption;
use App\Models\Question;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\Test;

class AnswerOptionTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'id' => 'option-1',
            'question_id' => 'question-1',
            'answer' => '42',
            'is_correct' => true,
        ];

        $option = new AnswerOption($data);

        $this->assertEquals('option-1', $option->id);
        $this->assertEquals('question-1', $option->question_id);
        $this->assertEquals('42', $option->answer);
        $this->assertTrue($option->is_correct);
    }

    #[Test]
    public function it_has_belongs_to_question_relationship()
    {
        $option = new AnswerOption();
        $relation = $option->question();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(Question::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $option = new AnswerOption();

        $this->assertEquals('answer_options', $option->getTable());
        $this->assertFalse($option->incrementing);
        $this->assertEquals('string', $option->getKeyType());
    }
}
