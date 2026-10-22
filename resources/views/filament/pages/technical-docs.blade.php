@php
    $sections = $this->sections();
    $ids = array_column($sections, 'id');
@endphp

<x-filament-panels::page>
    <style>
        .td { --td-line: var(--gray-200, #e5e7eb); --td-soft: var(--gray-50, #f9fafb); --td-mute: var(--gray-500, #6b7280); --td-ink: var(--gray-900, #111827); --td-accent: var(--primary-600, #2563eb); --td-accent-soft: color-mix(in srgb, var(--primary-500, #3b82f6) 10%, transparent); }
        .dark .td { --td-line: var(--gray-700, #374151); --td-soft: color-mix(in srgb, var(--gray-800, #1f2937) 60%, transparent); --td-mute: var(--gray-400, #9ca3af); --td-ink: var(--gray-100, #f3f4f6); --td-accent: var(--primary-400, #60a5fa); --td-accent-soft: color-mix(in srgb, var(--primary-400, #60a5fa) 12%, transparent); }

        .td { display: grid; grid-template-columns: 15rem minmax(0, 1fr); gap: 2rem; align-items: start; }
        @media (max-width: 1023px) { .td { grid-template-columns: minmax(0, 1fr); gap: 1rem; } }

        /* Navigasi bab: kolom samping di layar lebar, deretan chip yang bisa digeser di layar sempit. */
        .td-nav { position: sticky; top: 1rem; display: flex; flex-direction: column; gap: .125rem; }
        .td-nav-title { font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--td-mute); padding: 0 .75rem .5rem; }
        .td-nav button { display: flex; align-items: center; gap: .6rem; width: 100%; padding: .5rem .75rem; border-radius: .5rem; font-size: .85rem; text-align: left; color: inherit; background: transparent; border: 0; cursor: pointer; }
        .td-nav button:hover { background: var(--td-soft); }
        .td-nav button[aria-current="true"] { background: var(--td-accent-soft); color: var(--td-accent); font-weight: 600; }
        .td-nav button:focus-visible, .td-pager button:focus-visible, .td-card:focus-visible { outline: 2px solid var(--td-accent); outline-offset: 2px; }
        .td-nav .td-num { width: 1.4rem; flex: none; font-size: .75rem; color: var(--td-mute); font-variant-numeric: tabular-nums; }
        .td-nav button[aria-current="true"] .td-num { color: var(--td-accent); }
        @media (max-width: 1023px) {
            .td-nav { position: static; flex-direction: row; overflow-x: auto; gap: .375rem; padding-bottom: .5rem; border-bottom: 1px solid var(--td-line); }
            .td-nav-title { display: none; }
            .td-nav button { width: auto; flex: none; white-space: nowrap; border: 1px solid var(--td-line); padding: .375rem .7rem; }
            .td-nav .td-num { width: auto; }
        }

        .td-main { min-width: 0; max-width: 54rem; scroll-margin-top: 6rem; }
        .td-head { display: flex; gap: .9rem; align-items: flex-start; padding-bottom: 1.1rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--td-line); }
        .td-head-icon { flex: none; display: grid; place-items: center; width: 2.6rem; height: 2.6rem; border-radius: .65rem; background: var(--td-accent-soft); color: var(--td-accent); }
        .td-head-icon svg { width: 1.35rem; height: 1.35rem; }
        .td-kicker { font-size: .75rem; color: var(--td-mute); margin: 0 0 .15rem; }
        .td-head h2 { font-size: 1.35rem; font-weight: 700; line-height: 1.3; margin: 0; color: var(--td-ink); }
        .td-lead { font-size: .95rem; line-height: 1.65; color: var(--td-mute); margin: .35rem 0 0; max-width: 44rem; }
        .td-lead code { font-size: .8em; padding: .1rem .35rem; border-radius: .3rem; background: var(--td-soft); border: 1px solid var(--td-line); }
        .td-lead strong { font-weight: 600; color: var(--td-ink); }

        /* Isi markdown. */
        .td-prose { font-size: .9rem; line-height: 1.7; }
        .td-prose > * + * { margin-top: .8rem; }
        .td-prose p { margin: 0; }
        .td-prose ul, .td-prose ol { padding-left: 1.25rem; margin: 0; }
        .td-prose ul { list-style: disc; }
        .td-prose ol { list-style: decimal; }
        .td-prose li + li { margin-top: .35rem; }
        .td-prose li::marker { color: var(--td-mute); }
        .td-prose ul:has(input[type="checkbox"]) { list-style: none; padding-left: .25rem; }
        .td-prose input[type="checkbox"] { margin-right: .5rem; accent-color: var(--td-accent); }
        .td-prose strong { font-weight: 600; color: var(--td-ink); }
        .td-prose a { color: var(--td-accent); }
        .td-prose code { font-size: .8em; padding: .1rem .35rem; border-radius: .3rem; background: var(--td-soft); border: 1px solid var(--td-line); word-break: break-word; }
        .td-prose pre { padding: .85rem 1rem; border-radius: .6rem; background: var(--td-soft); border: 1px solid var(--td-line); overflow-x: auto; font-size: .8rem; line-height: 1.6; }
        .td-prose pre code { padding: 0; border: 0; background: none; word-break: normal; font-size: inherit; }
        .td-prose table { display: block; width: 100%; overflow-x: auto; border-collapse: separate; border-spacing: 0; border: 1px solid var(--td-line); border-radius: .6rem; font-size: .83rem; line-height: 1.55; }
        .td-prose th, .td-prose td { padding: .55rem .8rem; text-align: left; vertical-align: top; border-bottom: 1px solid var(--td-line); }
        .td-prose tr:last-child td { border-bottom: 0; }
        .td-prose th { background: var(--td-soft); font-weight: 600; white-space: nowrap; color: var(--td-ink); }
        .td-prose td:first-child { white-space: nowrap; font-weight: 500; }

        /* Bagian yang bisa dibuka-tutup. */
        .td-parts { display: flex; flex-direction: column; gap: .6rem; margin-top: 1.25rem; }
        .td-acc { border: 1px solid var(--td-line); border-radius: .75rem; overflow: hidden; }
        .td-acc summary { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; cursor: pointer; font-weight: 600; font-size: .9rem; list-style: none; color: var(--td-ink); }
        .td-acc summary::-webkit-details-marker { display: none; }
        .td-acc summary:hover { background: var(--td-soft); }
        .td-acc summary svg { width: 1rem; height: 1rem; flex: none; color: var(--td-mute); transition: transform .15s; }
        .td-acc[open] summary svg { transform: rotate(180deg); }
        .td-acc[open] summary { border-bottom: 1px solid var(--td-line); }
        .td-acc-body { padding: 1rem; }
        .td-tools { display: flex; justify-content: flex-end; gap: .75rem; margin-top: 1.25rem; font-size: .8rem; }
        .td-tools button { color: var(--td-accent); background: none; border: 0; cursor: pointer; padding: 0; }
        .td-tools button:hover { text-decoration: underline; }

        /* Kartu bab di halaman pertama. */
        .td-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); gap: .75rem; margin-top: 1.75rem; }
        .td-cards-title { margin: 1.75rem 0 0; font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--td-mute); }
        .td-cards-title + .td-cards { margin-top: .6rem; }
        .td-card { display: flex; gap: .7rem; text-align: left; padding: .85rem; border: 1px solid var(--td-line); border-radius: .75rem; background: transparent; color: inherit; cursor: pointer; }
        .td-card:hover { border-color: var(--td-accent); background: var(--td-soft); }
        .td-card svg { width: 1.15rem; height: 1.15rem; flex: none; color: var(--td-accent); margin-top: .1rem; }
        .td-card strong { display: block; font-size: .875rem; font-weight: 600; color: var(--td-ink); }
        .td-card span { display: block; font-size: .78rem; line-height: 1.45; color: var(--td-mute); margin-top: .15rem; }

        .td-pager { display: flex; justify-content: space-between; gap: .75rem; margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid var(--td-line); }
        .td-pager button { display: flex; flex-direction: column; gap: .1rem; max-width: 48%; padding: .6rem .9rem; border: 1px solid var(--td-line); border-radius: .6rem; background: transparent; color: inherit; cursor: pointer; text-align: left; }
        .td-pager button:hover { border-color: var(--td-accent); }
        .td-pager button.next { margin-left: auto; text-align: right; }
        .td-pager small { font-size: .72rem; color: var(--td-mute); }
        .td-pager span { font-size: .875rem; font-weight: 600; color: var(--td-accent); }
        .td-source { margin-top: 1rem; font-size: .75rem; color: var(--td-mute); }
        .td-source code { font-size: .9em; }

        .td-erd { border: 1px solid var(--td-line); border-radius: .75rem; padding: .75rem; overflow-x: auto; margin: 0; }
        .td-erd svg { display: block; width: 100%; min-width: 40rem; height: auto; }
        .td-erd .t-ink { fill: var(--gray-900, #111827); }
        .td-erd .t-quiet { fill: var(--gray-500, #6b7280); }
        .td-erd .s-edge { stroke: var(--gray-400, #9ca3af); }
        .td-erd .core { fill: color-mix(in srgb, var(--primary-500, #3b82f6) 12%, transparent); stroke: var(--primary-500, #3b82f6); }
        .dark .td-erd .t-ink { fill: var(--gray-100, #f3f4f6); }
        .dark .td-erd .t-quiet { fill: var(--gray-400, #9ca3af); }
        .dark .td-erd .s-edge { stroke: var(--gray-500, #6b7280); }
    </style>

    <div
        class="td"
        x-data="{
            ids: @js($ids),
            active: @js($ids[0] ?? ''),
            init() {
                this.fromHash();
                window.addEventListener('hashchange', () => this.fromHash());
            },
            fromHash() {
                const id = window.location.hash.slice(1);
                if (this.ids.includes(id)) this.active = id;
            },
            go(id) {
                this.active = id;
                history.replaceState(null, '', '#' + id);
                this.$nextTick(() => this.$refs.main.scrollIntoView({ block: 'start', behavior: 'smooth' }));
            },
            toggleAll(open) {
                document.getElementById('td-' + this.active)?.querySelectorAll('details').forEach((d) => d.open = open);
            },
        }"
    >
        <nav class="td-nav" aria-label="Bab dokumentasi">
            <div class="td-nav-title">Bab</div>
            @foreach ($sections as $i => $section)
                <button
                    type="button"
                    x-on:click="go(@js($section['id']))"
                    x-bind:aria-current="active === @js($section['id']) ? 'true' : 'false'"
                >
                    <span class="td-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    {{ $section['title'] }}
                </button>
            @endforeach
        </nav>

        <div class="td-main" x-ref="main">
            @foreach ($sections as $i => $section)
                <section id="td-{{ $section['id'] }}" x-show="active === @js($section['id'])" @if ($i > 0) x-cloak @endif>
                    <header class="td-head">
                        <div class="td-head-icon"><x-filament::icon :icon="$section['icon']" /></div>
                        <div>
                            <p class="td-kicker">Bab {{ $i + 1 }} dari {{ count($sections) }}</p>
                            <h2 id="{{ $section['id'] }}">{{ $section['title'] }}</h2>
                            @if ($section['lead'] !== '')
                                <p class="td-lead">{!! $section['lead'] !!}</p>
                            @endif
                        </div>
                    </header>

                    @if ($section['intro'] !== '')
                        <div class="td-prose">{!! $section['intro'] !!}</div>
                    @endif

                    @if (count($section['parts']) > 2)
                        <div class="td-tools">
                            <button type="button" x-on:click="toggleAll(true)">Buka semua</button>
                            <button type="button" x-on:click="toggleAll(false)">Tutup semua</button>
                        </div>
                    @endif

                    <div class="td-parts">
                        @foreach ($section['parts'] as $p => $part)
                            <details class="td-acc" @if ($p === 0) open @endif>
                                <summary>
                                    {{ $part['title'] }}
                                    <x-filament::icon icon="heroicon-m-chevron-down" />
                                </summary>
                                <div class="td-acc-body td-prose">{!! $part['html'] !!}</div>
                            </details>
                        @endforeach
                    </div>

                    @if ($i === 0)
                        <p class="td-cards-title">Isi dokumentasi</p>
                        <div class="td-cards">
                            @foreach (array_slice($sections, 1) as $other)
                                <button type="button" class="td-card" x-on:click="go(@js($other['id']))">
                                    <x-filament::icon :icon="$other['icon']" />
                                    <div>
                                        <strong>{{ $other['title'] }}</strong>
                                        <span>{{ $other['description'] }}</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="td-pager">
                        @if ($i > 0)
                            <button type="button" x-on:click="go(@js($sections[$i - 1]['id']))">
                                <small>Sebelumnya</small>
                                <span>{{ $sections[$i - 1]['title'] }}</span>
                            </button>
                        @endif
                        @if ($i < count($sections) - 1)
                            <button type="button" class="next" x-on:click="go(@js($sections[$i + 1]['id']))">
                                <small>Berikutnya</small>
                                <span>{{ $sections[$i + 1]['title'] }}</span>
                            </button>
                        @endif
                    </div>
                </section>
            @endforeach

            <p class="td-source">
                Sumber halaman ini: <code>{{ \App\Filament\Pages\TechnicalDocs::SOURCE }}</code>. Ubah file itu untuk memperbarui isinya.
            </p>
        </div>
    </div>
</x-filament-panels::page>
