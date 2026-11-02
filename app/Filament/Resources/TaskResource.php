<?php

namespace App\Filament\Resources;

use App\Access\ScopesVisibleRecords;
use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use App\Support\RelationSearch;
use App\Support\StoredFile;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskResource extends Resource
{
    use ScopesVisibleRecords;

    protected static ?string $model = Task::class;

    public static function getModelLabel(): string
    {
        return __('Task');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tasks');
    }

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Pekerjaan Vendor';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        // Rapat: 3 isian per baris di desktop; vendor digabung ke sini karena hanya satu isian.
        return $form
            ->schema([
                Section::make('Informasi Umum')
                    ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Pekerjaan')
                            ->required()
                            ->columnSpan(['md' => 2]),

                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->relationship('vendor', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nama Vendor')
                                    ->required(),

                                TextInput::make('last_price')
                                    ->label('Harga Terakhir (Rp)')
                                    ->numeric()
                                    ->prefix('Rp ')
                                    ->required()
                                    ->placeholder('Masukkan harga terakhir'),
                            ]),

                        TextInput::make('cost')
                            ->label('Biaya')
                            ->numeric()
                            ->prefix('Rp ')
                            ->required(),

                        DateTimePicker::make('work_timestamp')
                            ->native(false)
                            ->default(now())
                            ->required(),

                        TextInput::make('location')
                            ->label('Lokasi')
                            ->required(),

                        Select::make('business_entity_id')
                            ->label('Entitas Bisnis')
                            ->relationship('businessEntity', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('user_id')
                            ->label('PIC')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->id())
                            // ->disabled(fn() => !auth()->user()->hasRole('super_admin'))
                            ->dehydrated(true),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->required()
                            ->columnSpanFull(),
                    ]),

                // Attachment Section
                Section::make('Lampiran')
                    ->schema([
                        FileUpload::make('document_upload')
                            ->label('Upload Dokumen')
                            ->directory('documents') // Define a different directory for documents
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']) // Allow only document types
                            ->maxSize(5120) // Set a max size of 5MB
                            ->required() // Optionally, limit the number of files (example: 5)
                            ->hiddenOn('create'),
                    ])
                    ->columns(1)
                    ->collapsible()
                    // Isinya hanya muncul saat edit; tanpa ini halaman buat menampilkan kotak kosong.
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('businessEntity.name')
                    ->label('Badan Usaha')
                    ->searchable(query: RelationSearch::on('businessEntity', 'name'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable(query: RelationSearch::on('vendor', 'name'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cost')
                    ->money('IDR')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('location')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('PIC')
                    ->searchable(query: RelationSearch::on('user', 'name'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status') // Label in Indonesian
                    ->badge() // Enables badge display
                    ->colors([
                        'danger' => 'open',         // Red badge for 'open' status
                        'warning' => 'in_progress', // Yellow badge for 'in_progress' status
                        'success' => 'completed',   // Green badge for 'completed' status
                    ])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('document_upload')
                    ->url(fn ($record) => $record && $record->document_upload ? StoredFile::url($record->document_upload) : null, true) // Membuat kolom URL untuk unduh
                    ->openUrlInNewTab()
                    ->translateLabel()
                    ->getStateUsing(fn ($record) => $record && $record->document_upload ? 'Dokumen' : '-')
                    ->icon('heroicon-o-document-text')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                SelectFilter::make('businessEntity')->preload()->searchable()->relationship('businessEntity', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())->translateLabel(),
                SelectFilter::make('vendor')
                    ->relationship('vendor', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutTrashed())
                    ->label('Vendor')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->label('PIC')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ]),
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columnToggleFormColumns(2)
            ->actions([
                RestoreAction::make(),
                // Group the custom actions together
                ActionGroup::make([
                    // Edit Action (with pencil icon)
                    EditAction::make()
                        ->label('Edit')
                        ->icon('heroicon-o-pencil') // Use the pencil icon for edit
                        ->visible(fn ($record) => ! in_array($record->status, ['in_progress', 'completed'])),

                    // Custom Process Action (color: blue, with play icon)
                    Action::make('process')
                        ->label('Process')
                        ->icon('heroicon-o-play') // Use the play icon for process
                        ->color('primary') // Use 'primary' for blue
                        ->visible(fn ($record) => $record->status === 'open')
                        ->action(function ($record) {
                            $record->update(['status' => 'in_progress']);
                        }),

                    // Custom Complete Action (color: green, with check icon)
                    Action::make('complete')
                        ->label('Complete')
                        ->icon('heroicon-o-check-circle') // Use the check circle icon for complete
                        ->color('success') // Use 'success' for green
                        ->visible(fn ($record) => $record->status === 'in_progress')
                        ->form([
                            FileUpload::make('attachment')
                                ->label('Upload Lampiran')
                                ->directory('task') // Define the directory to store images
                                ->image() // Only allow image uploads
                                ->maxSize(2048) // Maximum size (optional)
                                ->required()
                                ->multiple() // Enable multiple file uploads
                                ->maxFiles(5), // Optionally, limit the number of files (example: 5)
                        ])
                        ->action(function ($record, $data) {
                            $record->update([
                                'status' => 'completed',
                                'attachment' => $data['attachment'],
                            ]);
                        }),

                    Action::make('upload')
                        ->label('Upload')
                        ->icon('heroicon-o-check-circle') // Use the check circle icon for complete
                        ->color('success') // Use 'success' for green
                        ->visible(fn ($record) => $record->status === 'completed' && is_null($record->document_upload))
                        ->form([
                            FileUpload::make('document_upload')
                                ->label('Upload Dokumen')
                                ->directory('documents') // Define a different directory for documents
                                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']) // Allow only document types
                                ->maxSize(5120) // Set a max size of 5MB
                                ->required(),
                        ])
                        ->action(function ($record, $data) {
                            $record->update([
                                'document_upload' => $data['document_upload'], // Save the uploaded document
                            ]);
                        }),

                    // Custom Download Action (color: red, with download icon)
                    Action::make('download')
                        ->label('Download')
                        ->icon('heroicon-o-arrow-down-tray') // Use the download icon for download
                        ->color('danger') // Use 'danger' for red
                        ->visible(fn ($record) => $record->status === 'completed' && is_null($record->document_upload))
                        ->url(fn ($record) => route('task-completion.download', $record->id))
                        ->openUrlInNewTab(),
                ]),

            ])
            ->bulkActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        // Satu kolom selebar layar: data pekerjaan, lalu lampiran. Nama pekerjaan jadi judul halaman.
        return $infolist
            ->columns(1)
            ->schema([
                Section::make('Informasi Pekerjaan')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])->schema([
                            TextEntry::make('code')
                                ->label('Nomor Tugas')
                                ->placeholder('Belum dibuat')
                                ->columnSpan(['default' => 2]),
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                                ->color(fn (?string $state): string => static::statusColor($state)),
                            TextEntry::make('cost')
                                ->label('Biaya')
                                ->money('IDR', decimalPlaces: 0)
                                ->weight(FontWeight::Bold)
                                ->placeholder('-'),
                            TextEntry::make('vendor.name')->label('Vendor')->placeholder('-'),
                            TextEntry::make('work_timestamp')->label('Waktu Pekerjaan')->dateTime('d M Y, H:i')->placeholder('-'),
                            TextEntry::make('location')->label('Lokasi')->placeholder('-'),
                            TextEntry::make('user.name')->label('PIC')->placeholder('-'),
                            TextEntry::make('businessEntity.name')->label('Badan Usaha')->placeholder('-'),
                            TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y, H:i'),
                            TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d M Y, H:i'),
                            TextEntry::make('document_upload')
                                ->label('Dokumen')
                                ->state(fn (Task $record): string => filled($record->document_upload) ? 'Lihat Dokumen' : 'Belum diunggah')
                                ->url(fn (Task $record): ?string => filled($record->document_upload) ? StoredFile::url($record->document_upload) : null, true)
                                ->icon(fn (Task $record): ?string => filled($record->document_upload) ? 'heroicon-o-document-text' : null)
                                ->color(fn (Task $record): string => filled($record->document_upload) ? 'primary' : 'gray'),
                            TextEntry::make('description')
                                ->label('Deskripsi')
                                ->placeholder('-')
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('Lampiran')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        // Kolom attachment berisi JSON daftar path; ditampilkan sebagai foto biasa.
                        ImageEntry::make('attachment')
                            ->hiddenLabel()
                            ->state(fn (Task $record): array => collect(static::attachmentPaths($record))->map(fn (string $path): string => StoredFile::url($path))->all())
                            ->height(120)
                            ->placeholder('Tidak ada lampiran'),
                    ]),
            ]);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'open' => 'Terbuka',
            'in_progress' => 'Dikerjakan',
            'completed' => 'Selesai',
            default => $status ?? '-',
        };
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'open' => 'danger',
            'in_progress' => 'warning',
            'completed' => 'success',
            default => 'gray',
        };
    }

    /**
     * @return array<int, string>
     */
    private static function attachmentPaths(Task $record): array
    {
        $state = $record->attachment;

        if (is_string($state) && str_starts_with($state, '[')) {
            $state = json_decode($state, true);
        }

        return collect(is_array($state) ? $state : [$state])->filter(fn ($path): bool => is_string($path) && $path !== '')->values()->all();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'view' => Pages\ViewTask::route('/{record}'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
