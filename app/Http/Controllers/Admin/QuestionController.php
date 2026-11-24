<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $q = Question::with('category')->withCount('options');
        if ($term = $request->string('q')->trim()->value()) {
            $q->where(fn ($w) => $w->where('question_text', 'like', "%{$term}%")->orWhere('correct_answer', 'like', "%{$term}%"));
        }
        foreach (['category' => 'question_category_id', 'difficulty' => 'difficulty', 'type' => 'type'] as $param => $col) {
            if ($v = $request->query($param)) {
                $q->where($col, $v);
            }
        }
        if ($request->filled('status')) {
            $q->where('is_active', $request->query('status') === 'active');
        }
        $sort = in_array($request->query('sort'), ['times_used', 'created_at', 'updated_at', 'points'], true) ? $request->query('sort') : 'id';

        return view('admin.questions.index', [
            'questions' => $q->orderByDesc($sort)->paginate(25)->withQueryString(),
            'categories' => QuestionCategory::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.questions.form', [
            'question' => new Question(['points' => 10, 'difficulty' => 'medium', 'type' => 'multiple_choice', 'is_active' => true]),
            'categories' => QuestionCategory::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        [$data, $options] = $this->validated($request);
        DB::transaction(function () use ($data, $options, $request) {
            $question = Question::create($data);
            $question->forceFill(['created_by' => $request->user()->id])->save();
            $question->syncOptions($options);
        });

        return redirect()->route('admin.questions.index')->with('success', 'Question added.');
    }

    public function edit(Question $question)
    {
        $question->load('options');
        $usage = $question->rounds()->with(['session', 'participant'])->latest()->limit(15)->get();

        return view('admin.questions.form', [
            'question' => $question,
            'categories' => QuestionCategory::orderBy('name')->get(),
            'usage' => $usage,
        ]);
    }

    public function update(Request $request, Question $question)
    {
        [$data, $options] = $this->validated($request);
        DB::transaction(function () use ($question, $data, $options) {
            $question->update($data);
            $question->syncOptions($options);
        });

        return redirect()->route('admin.questions.index')->with('success', 'Question updated.');
    }

    public function toggle(Question $question)
    {
        $question->update(['is_active' => ! $question->is_active]);

        return back()->with('success', 'Question '.($question->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Question $question)
    {
        $question->delete();

        return back()->with('success', 'Question deleted.');
    }

    public function export()
    {
        $rows = Question::with(['category', 'options'])->orderBy('id')->cursor()->map(function ($q) {
            $opts = $q->options->pluck('option_text')->pad(4, '')->all();

            return [
                $q->id, $q->category?->name, $q->type, $q->difficulty, $q->question_text,
                $opts[0], $opts[1], $opts[2], $opts[3],
                $q->options->firstWhere('is_correct', true)?->label,
                $q->correct_answer, $q->explanation, $q->points, $q->time_limit,
                $q->is_bonus ? 1 : 0, $q->is_danger ? 1 : 0, $q->is_active ? 1 : 0, $q->times_used,
            ];
        });

        return Csv::download('questions-'.now()->format('Ymd-His').'.csv', self::CSV_HEADER, $rows);
    }

    public function template()
    {
        return Csv::download('questions-import-template.csv', self::CSV_HEADER, [[
            '', 'Warehouse', 'multiple_choice', 'easy', 'What does FIFO stand for?',
            'First In, First Out', 'Fast In, Fast Out', 'First Item, Final Order', 'Free In, Free Out',
            'A', 'First In, First Out', 'Oldest stock is issued first.', 10, 30, 0, 0, 1, '',
        ]]);
    }

    private const CSV_HEADER = ['id', 'category', 'type', 'difficulty', 'question', 'option_a', 'option_b', 'option_c', 'option_d',
        'correct_option', 'correct_answer', 'explanation', 'points', 'time_limit', 'is_bonus', 'is_danger', 'is_active', 'times_used'];

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);
        $rows = Csv::read($request->file('file')->getRealPath());
        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($rows, &$created, &$updated, &$errors, $request) {
            foreach ($rows as $i => $r) {
                $line = $i + 2;
                $text = $r['question'] ?? $r['question_text'] ?? '';
                if ($text === '') {
                    $errors[] = "Line {$line}: question text is empty.";
                    continue;
                }
                $catName = trim($r['category'] ?? '') ?: 'General Knowledge';
                $category = QuestionCategory::withTrashed()->firstOrCreate(['name' => $catName], ['color' => '#4EAADF', 'is_active' => true]);
                if ($category->trashed()) {
                    $category->restore();
                }
                $type = array_key_exists($r['type'] ?? '', Question::TYPES) ? $r['type'] : 'short_answer';
                $diff = array_key_exists(strtolower($r['difficulty'] ?? ''), Question::DIFFICULTIES) ? strtolower($r['difficulty']) : 'medium';
                $options = [];
                $correctLabel = strtoupper(trim($r['correct_option'] ?? ''));
                foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $l) {
                    if (($r['option_'.$l] ?? '') !== '') {
                        $options[] = ['text' => $r['option_'.$l], 'is_correct' => $correctLabel === strtoupper($l)];
                    }
                }
                if ($type === 'true_false' && ! $options) {
                    $ans = strtolower($r['correct_answer'] ?? '');
                    $options = [['text' => 'True', 'is_correct' => $ans === 'true'], ['text' => 'False', 'is_correct' => $ans === 'false']];
                }
                $data = [
                    'question_category_id' => $category->id,
                    'type' => $type,
                    'difficulty' => $diff,
                    'question_text' => mb_substr($text, 0, 2000),
                    'correct_answer' => mb_substr($r['correct_answer'] ?? '', 0, 2000) ?: null,
                    'explanation' => mb_substr($r['explanation'] ?? '', 0, 2000) ?: null,
                    'points' => max(0, min(1000, (int) ($r['points'] ?? 10) ?: 10)),
                    'time_limit' => ($r['time_limit'] ?? '') !== '' ? max(5, min(600, (int) $r['time_limit'])) : null,
                    'is_bonus' => filter_var($r['is_bonus'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_danger' => filter_var($r['is_danger'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_active' => ($r['is_active'] ?? '') === '' ? true : filter_var($r['is_active'], FILTER_VALIDATE_BOOLEAN),
                ];
                $existing = ! empty($r['id']) ? Question::find((int) $r['id']) : null;
                if ($existing) {
                    $existing->update($data);
                    $existing->syncOptions($options);
                    $updated++;
                } else {
                    $q = Question::create($data);
                    $q->forceFill(['created_by' => $request->user()->id])->save();
                    $q->syncOptions($options);
                    $created++;
                }
            }
        });

        $msg = "Import finished: {$created} created, {$updated} updated.";

        return redirect()->route('admin.questions.index')->with('success', $msg)->with('import_errors', array_slice($errors, 0, 20));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question_category_id' => ['required', 'integer', 'exists:question_categories,id'],
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'difficulty' => ['required', Rule::in(array_keys(Question::DIFFICULTIES))],
            'question_text' => ['required', 'string', 'max:2000'],
            'correct_answer' => ['nullable', 'string', 'max:2000'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'points' => ['required', 'integer', 'min:0', 'max:1000'],
            'time_limit' => ['nullable', 'integer', 'min:5', 'max:600'],
            'options' => ['nullable', 'array', 'max:8'],
            'options.*.text' => ['nullable', 'string', 'max:500'],
            'correct_option' => ['nullable', 'integer', 'min:0', 'max:7'],
        ]);
        foreach (['is_bonus', 'is_danger', 'is_active'] as $b) {
            $data[$b] = $request->boolean($b);
        }
        $options = [];
        foreach ($data['options'] ?? [] as $i => $opt) {
            $options[] = ['text' => $opt['text'] ?? '', 'is_correct' => isset($data['correct_option']) && (int) $data['correct_option'] === (int) $i];
        }
        if ($data['type'] === 'true_false' && collect($options)->filter(fn ($o) => trim($o['text']) !== '')->isEmpty()) {
            $ans = strtolower(trim($data['correct_answer'] ?? ''));
            $options = [['text' => 'True', 'is_correct' => $ans === 'true'], ['text' => 'False', 'is_correct' => $ans === 'false']];
        }
        unset($data['options'], $data['correct_option']);

        return [$data, $options];
    }
}
