<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Resources\UserResource;
use App\Services\TalentaUserImportService;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListUsers extends ListRecords
{
    use HasResizableColumn, MemoizesTableQuery;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $canImport = fn (): bool => auth()->user()?->can('import', static::$resource::getModel()) ?? false;

        // Data karyawan bersumber dari Talenta (sama seperti dnd-web), jadi import
        // hanya lewat ekspor JSON Talenta.
        return [
            Actions\Action::make('importTalenta')
                ->label('Import JSON (Talenta)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->authorize($canImport)
                ->visible($canImport)
                ->schema([
                    FileUpload::make('file')
                        ->label('Upload JSON Talenta')
                        ->helperText('Ekspor karyawan Talenta. User dicocokkan lewat Employee ID (atau nama untuk user lama); nama, Employee ID, nomor HP, email, badan usaha, dan jabatan mengikuti Talenta.')
                        ->acceptedFileTypes([
                            'application/json',
                            'text/json',
                            'text/plain',
                        ])
                        ->required()
                        ->storeFiles(false),
                ])
                ->modalHeading('Import User dari JSON Talenta')
                ->modalWidth('md')
                ->modalSubmitActionLabel('Import')
                ->action(function (array $data): void {
                    $file = $data['file'] ?? null;
                    $content = $file instanceof TemporaryUploadedFile
                        ? $file->get()
                        : (is_string($file) && is_readable($file) ? file_get_contents($file) : false);

                    if (! is_string($content) || $content === '') {
                        Notification::make()
                            ->title('File JSON Talenta tidak dapat dibaca.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $result = (new TalentaUserImportService)->importFromContent($content);
                    $notification = Notification::make()->title($result['message']);

                    if (! ($result['success'] ?? false) || ($result['error_count'] ?? 0) > 0) {
                        $notification
                            ->body(implode("\n", array_slice($result['errors'] ?? [], 0, 8)))
                            ->warning()
                            ->persistent();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
