<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $session->title }} – Results</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <style>
        body { font-size: 12px; } h1 { font-size: 20px; } .brand { border-bottom: 4px solid #86C440; }
        @media print { .no-print { display: none !important; } @page { margin: 14mm; } }
        th { background: #f1f5f9 !important; }
    </style>
</head>
<body class="p-4">
<div class="no-print mb-3 d-flex gap-2"><button class="btn btn-primary btn-sm" onclick="window.print()">Print / Save as PDF</button><a class="btn btn-light btn-sm" href="{{ route('admin.sessions.show', $session) }}">Back</a></div>
<div class="d-flex align-items-center brand pb-2 mb-3">
    <img src="{{ asset('images/logo.png') }}" alt="" style="height:60px">
    <div class="ms-3"><h1 class="mb-0">Operations Quiz Arena – Session Report</h1><div class="text-muted">{{ $session->title }} · {{ $session->workshop_title }}</div></div>
    <div class="ms-auto text-end small">Date: {{ $session->session_date?->format('j M Y') }}<br>Facilitator: {{ $session->facilitator?->name }}<br>Status: {{ ucfirst($session->status) }}</div>
</div>
<table class="table table-sm table-bordered w-auto"><tr>
    <th>Rounds played</th><td>{{ $stats['rounds_played'] }} / {{ $session->total_rounds }}</td>
    <th>Correct</th><td>{{ $stats['total_correct'] }}</td><th>Wrong / timed out</th><td>{{ $stats['total_incorrect'] }}</td>
    <th>Average score</th><td>{{ $stats['average_score'] }}</td></tr></table>
@if (! empty($leaderboard))<p><strong>Champion:</strong> {{ $leaderboard[0]['full_name'] }} ({{ $leaderboard[0]['score'] }} pts)</p>@endif
<h2 class="h6 mt-3">Final leaderboard</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Rank</th><th>Participant</th><th>Team</th><th>Score</th><th>Correct</th><th>Answered</th><th>Accuracy</th><th>Best streak</th></tr></thead><tbody>
@foreach ($leaderboard as $r)<tr><td>{{ $r['rank'] }}</td><td>{{ $r['full_name'] }}</td><td>{{ $r['team'] }}</td><td><strong>{{ $r['score'] }}</strong></td><td>{{ $r['correct'] }}</td><td>{{ $r['answered'] }}</td><td>{{ $r['accuracy'] }}%</td><td>{{ $r['best_streak'] }}</td></tr>@endforeach
</tbody></table>
@if ($teamLeaderboard)
<h2 class="h6">Teams</h2>
<table class="table table-sm table-bordered w-auto"><tbody>@foreach ($teamLeaderboard as $t)<tr><td>{{ $t['rank'] }}</td><td>{{ $t['name'] }}</td><td>{{ $t['score'] }} pts</td></tr>@endforeach</tbody></table>
@endif
<h2 class="h6">Rounds</h2>
<table class="table table-sm table-bordered"><thead><tr><th>#</th><th>Participant</th><th>Category</th><th>Question</th><th>Type</th><th>Result</th><th>Points</th></tr></thead><tbody>
@foreach ($rounds as $r)<tr><td>{{ $r->round_number }}</td><td>{{ $r->participant?->full_name }}</td><td>{{ $r->question?->category?->name }}</td><td>{{ $r->question?->question_text ?? $r->challenge_text }}</td><td>{{ str_replace('_', ' ', $r->round_type) }}</td><td>{{ str_replace('_', ' ', $r->result ?? '—') }}</td><td>{{ $r->points_awarded }}</td></tr>@endforeach
</tbody></table>
<p class="text-muted small">Generated {{ now()->format('j M Y H:i') }} · TradeSmart Supplies Limited – Together Values</p>
<script>if (location.hash === '#print') window.print();</script>
</body>
</html>
