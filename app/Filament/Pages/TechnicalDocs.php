<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Str;

/**
 * Dokumentasi teknis untuk developer: tech stack, peta kode, relasi database,
 * dan panduan maintenance. Isinya dirender dari docs/dokumentasi-teknis.md
 * supaya repo dan panel memakai satu sumber. Setiap `##` menjadi satu bab,
 * setiap `###` menjadi bagian yang bisa dibuka-tutup.
 */
class TechnicalDocs extends Page
{
    public const SOURCE = 'docs/dokumentasi-teknis.md';

    /** Ikon per bab, dicocokkan dari slug judul. */
    private const ICONS = [
        'ringkasan' => 'heroicon-o-home',
        'tech-stack' => 'heroicon-o-cpu-chip',
        'setup-lokal' => 'heroicon-o-command-line',
        'struktur-kode' => 'heroicon-o-folder-open',
        'modul' => 'heroicon-o-squares-2x2',
        'database' => 'heroicon-o-circle-stack',
        'akses-permission' => 'heroicon-o-shield-check',
        'testing-konvensi' => 'heroicon-o-beaker',
        'maintenance' => 'heroicon-o-wrench-screwdriver',
    ];

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-code-bracket-square';

    protected static ?int $navigationSort = 101;

    protected static ?string $navigationLabel = 'Dokumentasi Teknis';

    protected static ?string $title = 'Dokumentasi Teknis';

    protected static ?string $slug = 'dokumentasi-teknis';

    protected string $view = 'filament.pages.technical-docs';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:TechnicalDocs') ?? false;
    }

    /**
     * Bab-bab dokumen. Teks sebelum `##` pertama (judul dan catatan penulis) dilewati.
     *
     * @return array<int, array{id: string, title: string, description: string, icon: string, lead: string, intro: string, parts: array<int, array{title: string, html: string}>}>
     */
    public function sections(): array
    {
        $path = base_path(self::SOURCE);
        $markdown = is_file($path) ? str_replace("\r\n", "\n", file_get_contents($path)) : '';

        $chunks = preg_split('/^## +(.+)$/m', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        $sections = [];

        for ($i = 1; $i < count($chunks); $i += 2) {
            $title = trim($chunks[$i]);
            $body = $chunks[$i + 1] ?? '';
            $id = Str::slug($title);

            $description = '';
            if (preg_match('/<!--\s*desc:\s*(.*?)\s*-->/', $body, $match)) {
                $description = $match[1];
                $body = str_replace($match[0], '', $body);
            }

            $sections[] = ['id' => $id, 'title' => $title, 'description' => $description, 'icon' => self::ICONS[$id] ?? 'heroicon-o-document-text']
                + $this->splitParts($this->renderMarkdown($body));
        }

        return $sections;
    }

    private function renderMarkdown(string $markdown): string
    {
        $html = Str::markdown($markdown, ['html_input' => 'allow', 'allow_unsafe_links' => false]);

        return str_replace('<!-- erd -->', view('filament.pages.partials.technical-erd')->render(), $html);
    }

    /**
     * Pisahkan HTML bab menjadi paragraf pembuka, sisa pengantar, dan bagian per `<h3>`.
     *
     * @return array{lead: string, intro: string, parts: array<int, array{title: string, html: string}>}
     */
    private function splitParts(string $html): array
    {
        $pieces = preg_split('/<h3>(.*?)<\/h3>/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $intro = trim(array_shift($pieces));

        $lead = '';
        if (preg_match('/\A<p>(.*?)<\/p>/s', $intro, $match)) {
            $lead = $match[1];
            $intro = trim(substr($intro, strlen($match[0])));
        }

        $parts = [];
        for ($i = 0; $i < count($pieces); $i += 2) {
            $parts[] = ['title' => strip_tags($pieces[$i]), 'html' => trim($pieces[$i + 1] ?? '')];
        }

        return ['lead' => $lead, 'intro' => $intro, 'parts' => $parts];
    }
}
