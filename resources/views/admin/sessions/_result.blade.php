@php $map = ['correct' => ['success', 'Correct'], 'incorrect' => ['danger', 'Wrong'], 'partial' => ['info', 'Partial'], 'passed' => ['secondary', 'Passed'], 'timed_out' => ['warning', 'Timed out']]; @endphp
@if ($result && isset($map[$result]))<span class="badge text-bg-{{ $map[$result][0] }}">{{ $map[$result][1] }}</span>@else<span class="badge text-bg-light">open</span>@endif
