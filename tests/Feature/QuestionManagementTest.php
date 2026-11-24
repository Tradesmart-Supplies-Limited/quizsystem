<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionCategory;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QuestionManagementTest extends TestCase
{
    public function test_question_can_be_created_with_options(): void
    {
        $cat = QuestionCategory::factory()->create();
        $this->actingAs($this->facilitator())->post(route('admin.questions.store'), [
            'question_category_id' => $cat->id, 'type' => 'multiple_choice', 'difficulty' => 'easy',
            'question_text' => 'What does FIFO mean?', 'correct_answer' => 'First In, First Out', 'points' => 10,
            'options' => [['text' => 'First In, First Out'], ['text' => 'Fast In'], ['text' => ''], ['text' => 'Other']],
            'correct_option' => 0, 'is_active' => 1,
        ])->assertRedirect(route('admin.questions.index'));

        $q = Question::with('options')->firstOrFail();
        $this->assertCount(3, $q->options);
        $this->assertTrue($q->options->firstWhere('label', 'A')->is_correct);
        $this->assertFalse($q->options->firstWhere('label', 'B')->is_correct);
    }

    public function test_question_can_be_edited_toggled_and_soft_deleted(): void
    {
        $q = Question::factory()->create();
        $this->actingAs($this->facilitator());
        $this->put(route('admin.questions.update', $q), [
            'question_category_id' => $q->question_category_id, 'type' => 'true_false', 'difficulty' => 'hard',
            'question_text' => 'Edited?', 'correct_answer' => 'True', 'points' => 25, 'is_active' => 1,
        ])->assertRedirect();
        $q->refresh();
        $this->assertSame('Edited?', $q->question_text);
        $this->assertSame(25, $q->points);
        $this->assertCount(2, $q->options); // True / False generated automatically
        $this->patch(route('admin.questions.toggle', $q));
        $this->assertFalse($q->fresh()->is_active);
        $this->delete(route('admin.questions.destroy', $q));
        $this->assertSoftDeleted($q);
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->facilitator())->post(route('admin.questions.store'), ['type' => 'hacker', 'points' => -5])
            ->assertSessionHasErrors(['question_category_id', 'type', 'question_text', 'points']);
    }

    public function test_csv_import_and_export(): void
    {
        $csv = "category,type,difficulty,question,option_a,option_b,correct_option,correct_answer,points\n"
            ."Warehouse,multiple_choice,easy,What is a SKU?,Stock Keeping Unit,Other,A,Stock Keeping Unit,10\n"
            ."New Cat,short_answer,hard,Name a KPI,,,,OTIF,15\n"
            .",,,,,,,,\n";
        $file = UploadedFile::fake()->createWithContent('q.csv', $csv);
        $this->actingAs($this->facilitator())->post(route('admin.questions.import'), ['file' => $file])->assertSessionHas('success');
        $this->assertSame(2, Question::count());
        $this->assertDatabaseHas('question_categories', ['name' => 'New Cat']);
        $this->assertTrue(Question::where('question_text', 'What is a SKU?')->first()->options()->where('is_correct', true)->exists());

        $res = $this->get(route('admin.questions.export'))->assertOk();
        $this->assertStringContainsString('What is a SKU?', $res->streamedContent());
    }

    public function test_csv_export_neutralises_formula_injection(): void
    {
        Question::factory()->create(['question_text' => '=HYPERLINK("http://evil")']);
        $out = $this->actingAs($this->facilitator())->get(route('admin.questions.export'))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $out);
    }

    public function test_search_and_filter(): void
    {
        Question::factory()->create(['question_text' => 'Forklift safety rules?', 'difficulty' => 'hard']);
        Question::factory()->create(['question_text' => 'Invoice matching?', 'difficulty' => 'easy']);
        $this->actingAs($this->facilitator())->get(route('admin.questions.index', ['q' => 'Forklift']))
            ->assertSee('Forklift safety rules?')->assertDontSee('Invoice matching?');
        $this->get(route('admin.questions.index', ['difficulty' => 'easy']))->assertSee('Invoice matching?')->assertDontSee('Forklift safety');
    }

    public function test_question_text_is_escaped(): void
    {
        Question::factory()->create(['question_text' => '<script>alert(1)</script>']);
        $this->actingAs($this->facilitator())->get(route('admin.questions.index'))
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
