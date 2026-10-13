@props(['name', 'caption' => null, 'shots' => []])

{{-- Gambar bergerak + screenshot per langkah. Dibuat otomatis oleh tools/guide-capture (npm run capture). --}}
@php
    $dir = public_path('images/guide');
    $anim = file_exists("{$dir}/{$name}.gif") ? asset("images/guide/{$name}.gif") : null;
@endphp

@if ($anim)
    <figure class="pg-demo">
        <img src="{{ $anim }}" alt="{{ $caption ?? 'Rekaman layar' }}" loading="lazy">
        @if ($caption)
            <figcaption>{{ $caption }}</figcaption>
        @endif
        @if ($shots !== [])
            <div class="pg-shots">
                @foreach ($shots as $file => $label)
                    @if (file_exists("{$dir}/{$file}"))
                        <a href="{{ asset("images/guide/{$file}") }}" target="_blank" rel="noopener">
                            <img src="{{ asset("images/guide/{$file}") }}" alt="{{ $label }}" loading="lazy">
                            <span>{{ $label }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </figure>
@endif
