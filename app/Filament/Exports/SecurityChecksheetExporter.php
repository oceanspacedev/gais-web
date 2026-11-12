<?php

namespace App\Filament\Exports;

use App\Filament\Resources\SecurityChecksheetResource;
use App\Models\SecurityChecklistItem;
use App\Models\SecurityChecksheet;
use App\Support\StoredFile;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class SecurityChecksheetExporter extends Exporter
{
    protected static ?string $model = SecurityChecksheet::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_number')
                ->label('No. Referensi'),

            ExportColumn::make('user.name')
                ->label('Petugas Security'),

            ExportColumn::make('location')
                ->label('Lokasi Kerja'),

            ExportColumn::make('report_date')
                ->label('Tanggal')
                ->state(fn (SecurityChecksheet $record): ?string => $record->report_date?->format('d/m/Y')),

            ExportColumn::make('checked_at')
                ->label('Waktu Pengecekan')
                ->state(fn (SecurityChecksheet $record): ?string => $record->checked_at?->format('d/m/Y H:i:s')),

            ...self::checklistColumns(),

            ExportColumn::make('mismatch_count')
                ->label('Jumlah Tidak Sesuai'),

            ExportColumn::make('mismatched_questions')
                ->label('Butir Tidak Sesuai')
                ->state(fn (SecurityChecksheet $record): string => implode(', ', $record->mismatchedQuestions())),

            ExportColumn::make('findings')
                ->label('Kondisi Darurat / Temuan Kejadian'),

            ExportColumn::make('attachments')
                ->label('Lampiran')
                ->state(fn (SecurityChecksheet $record): string => collect($record->attachmentPaths())
                    ->map(fn (string $path): string => StoredFile::downloadUrl(ltrim($path, '/')))
                    ->implode("\n")),

            ExportColumn::make('created_at')
                ->label('Tanggal Dibuat')
                ->state(fn (SecurityChecksheet $record): ?string => $record->created_at?->format('d/m/Y H:i:s')),
        ];
    }

    /**
     * Satu kolom per pertanyaan, mis. "Keamanan - Pintu utama / gerbang",
     * termasuk pertanyaan nonaktif/terhapus agar laporan lama tetap lengkap.
     *
     * @return list<ExportColumn>
     */
    private static function checklistColumns(): array
    {
        return SecurityChecklistItem::withTrashed()
            ->ordered()
            ->get()
            ->map(fn (SecurityChecklistItem $item): ExportColumn => ExportColumn::make("checklist_q{$item->id}")
                ->label("{$item->section} - {$item->question}")
                ->state(fn (SecurityChecksheet $record): ?string => $record->answerFor($item->id)))
            ->values()
            ->all();
    }

    /**
     * @return array<mixed>
     */
    public function __invoke(Model $record): array
    {
        $this->record = $record;

        $columns = $this->getCachedColumns();
        $data = [];

        foreach (array_keys($this->columnMap) as $column) {
            $data[] = array_key_exists($column, $columns)
                ? $columns[$column]->getFormattedState()
                : '';
        }

        return $data;
    }

    public static function modifyQuery(Builder $query): Builder
    {
        $user = auth()->user();

        $query = $query->with(['user']);

        // Job ekspor Filament berjalan sebagai user pemilik ekspor; aturan lihatnya sama dengan daftar.
        return SecurityChecksheetResource::scopeToViewer($query, $user);
    }

    public function getFormats(): array
    {
        return [
            ExportFormat::Xlsx,
            ExportFormat::Csv,
        ];
    }

    public function getJobQueue(): ?string
    {
        return 'exports';
    }

    /**
     * @return array<int, object>
     */
    public function getJobMiddleware(): array
    {
        return [];
    }

    public function getFileName(Export $export): string
    {
        return 'export_security_checksheet_'.now()->format('Y-m-d_His');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export Security Checksheet selesai: '.Number::format($export->successful_rows).' baris berhasil diexport.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' baris gagal.';
        }

        return $body;
    }
}
