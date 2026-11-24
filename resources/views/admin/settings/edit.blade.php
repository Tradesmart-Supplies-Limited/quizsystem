@extends('layouts.admin')
@section('title', 'Default settings & sounds')
@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf @method('PUT')
    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card mb-4"><div class="card-header"><strong>Branding</strong></div><div class="card-body row g-3">
                <div class="col-md-7"><label class="form-label">Company name</label><input class="form-control" name="branding[company]" value="{{ $branding['company'] ?? '' }}"></div>
                <div class="col-md-5"><label class="form-label">Tagline</label><input class="form-control" name="branding[tagline]" value="{{ $branding['tagline'] ?? '' }}"></div>
            </div></div>
            <div class="card mb-4"><div class="card-header"><strong>Default game configuration</strong> <span class="small text-muted">— applied to new sessions; each session can override</span></div>
                <div class="card-body p-0">@include('admin.settings._game-fields', ['config' => $config])</div></div>
        </div>
        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center"><strong>Sound library</strong>
                    <select class="form-select form-select-sm w-auto ms-auto" id="previewTheme">@foreach ($audio['themes'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="card-body small text-muted pb-0">All sounds are original, synthesised for this application (royalty-free). Untick to disable a sound everywhere. Replace any file in <code>public/sounds/&lt;theme&gt;/</code> with your own licensed MP3 of the same name.</div>
                <div class="list-group list-group-flush sound-list">
                    @foreach (collect($audio['catalogue'])->groupBy(fn ($v) => $v[1], true) as $group => $sounds)
                        <div class="list-group-item bg-light fw-semibold small">{{ $group }}</div>
                        @foreach ($sounds as $key => [$label])
                            <div class="list-group-item d-flex align-items-center gap-2 py-1">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-preview="{{ $key }}"><i class="bi bi-play-fill"></i></button>
                                <span class="flex-grow-1">{{ $label }}</span>
                                <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="disabled[]" value="{{ $key }}" @checked(in_array($key, $audio['disabled'])) title="Disable"><label class="small text-muted">off</label></div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
            <div class="card mb-4"><div class="card-header"><strong>Event → sound mapping</strong> <span class="small text-muted">(multiple = random pick)</span></div>
                <div class="card-body">
                    @foreach ($audio['events'] as $event => $chosen)
                        <div class="mb-2"><label class="form-label small mb-0 text-capitalize">{{ str_replace('_', ' ', $event) }}</label>
                            <select multiple class="form-select form-select-sm" name="events[{{ $event }}][]" size="2">
                                @foreach ($audio['catalogue'] as $key => [$label])<option value="{{ $key }}" @selected(in_array($key, $chosen))>{{ $label }}</option>@endforeach
                            </select></div>
                    @endforeach
                </div></div>
        </div>
    </div>
    <div class="sticky-bottom bg-white border-top py-3"><button class="btn btn-brand btn-lg"><i class="bi bi-save"></i> Save defaults</button></div>
</form>
@push('scripts')
<script>
let current;
document.querySelectorAll('[data-preview]').forEach(b => b.addEventListener('click', () => {
    if (current) current.pause();
    current = new Audio(@json(asset('sounds')) + '/' + document.getElementById('previewTheme').value + '/' + b.dataset.preview + '.mp3');
    current.play().catch(() => alert('Could not play this sound file.'));
}));
</script>
@endpush
@endsection
