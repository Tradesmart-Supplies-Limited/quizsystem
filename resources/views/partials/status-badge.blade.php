@php $colors = ['draft' => 'secondary', 'ready' => 'info', 'active' => 'success', 'paused' => 'warning', 'completed' => 'dark']; @endphp
<span class="badge text-bg-{{ $colors[$status] ?? 'secondary' }} text-uppercase">{{ $status }}</span>
