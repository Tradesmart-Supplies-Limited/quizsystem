<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\QuizRound;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParticipantController extends Controller
{
    public function index(Request $request)
    {
        $q = Participant::query()
            ->withSum('sessionEntries as total_score', 'score')
            ->withSum('sessionEntries as total_answered', 'answered')
            ->withSum('sessionEntries as total_correct', 'correct')
            ->withSum('sessionEntries as total_incorrect', 'incorrect')
            ->withCount('sessionEntries as sessions_count');
        if ($term = $request->string('q')->trim()->value()) {
            $q->where(fn ($w) => $w->where('full_name', 'like', "%{$term}%")
                ->orWhere('display_name', 'like', "%{$term}%")
                ->orWhere('department', 'like', "%{$term}%")
                ->orWhere('job_title', 'like', "%{$term}%"));
        }
        if ($request->filled('status')) {
            $q->where('is_active', $request->query('status') === 'active');
        }
        if ($d = $request->query('department')) {
            $q->where('department', $d);
        }

        return view('admin.participants.index', [
            'participants' => $q->orderBy('full_name')->paginate(30)->withQueryString(),
            'departments' => Participant::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    public function create()
    {
        return view('admin.participants.form', ['participant' => new Participant(['is_active' => true, 'department' => 'Operations'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $p = Participant::create($data);
        $this->handlePhoto($request, $p);

        return redirect()->route('admin.participants.index')->with('success', 'Team member added.');
    }

    public function show(Participant $participant)
    {
        $entries = $participant->sessionEntries()->with('session')->latest()->get();
        $rounds = QuizRound::with(['question.category', 'session'])
            ->where('participant_id', $participant->id)->latest()->limit(50)->get();

        return view('admin.participants.show', [
            'participant' => $participant,
            'entries' => $entries,
            'rounds' => $rounds,
            'stats' => $participant->lifetimeStats(),
        ]);
    }

    public function edit(Participant $participant)
    {
        return view('admin.participants.form', compact('participant'));
    }

    public function update(Request $request, Participant $participant)
    {
        $participant->update($this->validated($request));
        if ($request->boolean('remove_photo')) {
            $this->deletePhoto($participant);
        }
        $this->handlePhoto($request, $participant);

        return redirect()->route('admin.participants.index')->with('success', 'Team member updated.');
    }

    public function toggle(Participant $participant)
    {
        $participant->update(['is_active' => ! $participant->is_active]);

        return back()->with('success', $participant->display_name.' '.($participant->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Participant $participant)
    {
        $participant->delete(); // soft delete: historic scores stay in reports

        return back()->with('success', 'Team member removed.');
    }

    public function export()
    {
        $rows = Participant::withSum('sessionEntries as total_score', 'score')
            ->withSum('sessionEntries as total_answered', 'answered')
            ->withSum('sessionEntries as total_correct', 'correct')
            ->orderBy('full_name')->cursor()
            ->map(fn ($p) => [$p->id, $p->full_name, $p->display_name, $p->department, $p->job_title, $p->email,
                $p->is_active ? 1 : 0, (int) $p->total_score, (int) $p->total_answered, (int) $p->total_correct]);

        return Csv::download('participants-'.now()->format('Ymd-His').'.csv',
            ['id', 'full_name', 'display_name', 'department', 'job_title', 'email', 'is_active', 'total_score', 'answered', 'correct'], $rows);
    }

    public function template()
    {
        return Csv::download('participants-import-template.csv', ['full_name', 'display_name', 'department', 'job_title', 'email', 'is_active'],
            [['Jane Wanjiku', 'Jane', 'Operations', 'Warehouse Supervisor', '', 1]]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $rows = Csv::read($request->file('file')->getRealPath(), 2000);
        $created = 0;
        $updated = 0;
        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $r) {
                $name = mb_substr($r['full_name'] ?? $r['name'] ?? '', 0, 150);
                if ($name === '') {
                    continue;
                }
                $data = [
                    'display_name' => mb_substr($r['display_name'] ?? '', 0, 60) ?: null,
                    'department' => mb_substr($r['department'] ?? '', 0, 100) ?: null,
                    'job_title' => mb_substr($r['job_title'] ?? '', 0, 100) ?: null,
                    'email' => filter_var($r['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
                    'is_active' => ($r['is_active'] ?? '') === '' ? true : filter_var($r['is_active'], FILTER_VALIDATE_BOOLEAN),
                ];
                $existing = Participant::where('full_name', $name)->first();
                if ($existing) {
                    $existing->update(array_filter($data, fn ($v) => $v !== null));
                    $updated++;
                } else {
                    Participant::create(['full_name' => $name] + $data);
                    $created++;
                }
            }
        });

        return redirect()->route('admin.participants.index')->with('success', "Import finished: {$created} added, {$updated} updated.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'display_name' => ['nullable', 'string', 'max:60'],
            'department' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        unset($data['photo']);

        return $data;
    }

    private function handlePhoto(Request $request, Participant $p): void
    {
        $file = $request->file('photo');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return;
        }
        $this->deletePhoto($p);
        $dir = public_path('uploads/participants');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        // Random name + extension derived from the detected MIME type (never the client name).
        $name = Str::random(32).'.'.$file->guessExtension();
        $file->move($dir, $name);
        $p->forceFill(['photo_path' => 'participants/'.$name])->save();
    }

    private function deletePhoto(Participant $p): void
    {
        if ($p->photo_path && str_starts_with($p->photo_path, 'participants/')) {
            @unlink(public_path('uploads/'.basename(dirname($p->photo_path)).'/'.basename($p->photo_path)));
        }
        $p->forceFill(['photo_path' => null])->save();
    }
}
