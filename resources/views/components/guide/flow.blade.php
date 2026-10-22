@props(['steps'])

@php
    $n = count($steps);
    $actors = [
        'pemohon' => 'Pemohon',
        'accounting' => 'Accounting',
        'manager' => 'Manager / Atasan',
        'executor' => 'Executor',
        'ga' => 'General Affair',
        'admin' => 'Admin / Developer',
        'sistem' => 'Sistem',
        'user' => 'User',
    ];
@endphp

<ol class="pg-flow" style="--n: {{ $n }}">
    @foreach ($steps as $i => $step)
        <li class="pg-step">
            <div class="pg-rail">
                <span class="pg-dot">
                    <span class="pg-dot-on" data-n="{{ $i + 1 }}" style="animation-name: pg-dot-{{ $n }}-{{ $i }}"></span>
                    {{ $i + 1 }}
                </span>
                @if (! $loop->last)
                    <span class="pg-seg"><span class="pg-fill" style="animation-name: pg-fill-{{ $n }}-{{ $i }}"></span></span>
                @endif
            </div>
            <div class="pg-card">
                <span class="pg-card-on" style="animation-name: pg-card-{{ $n }}-{{ $i }}"></span>
                <span class="pg-badge pg-a-{{ $step['actor'] }}">{{ $actors[$step['actor']] ?? $step['actor'] }}</span>
                <p class="pg-step-title">{{ $step['title'] }}</p>
                <p class="pg-step-text">{{ $step['text'] }}</p>
            </div>
        </li>
    @endforeach
</ol>
