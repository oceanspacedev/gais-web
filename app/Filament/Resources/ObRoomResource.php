<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ObRoomResource\Pages;
use App\Models\ObRoom;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Unique;

class ObRoomResource extends Resource
{
    protected static ?string $model = ObRoom::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|\UnitEnum|null $navigationGroup = 'OB / Kebersihan';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Ruangan OB');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ruangan OB');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema(self::formFields());
    }

    /**
     * Dipakai juga untuk menambah ruangan langsung dari form shift.
     *
     * @return array<int, mixed>
     */
    public static function formFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Nama Ruangan')
                ->placeholder('Contoh: Toilet Lt. 1, Mushola, Ruang Meeting Finance')
                ->required()
                ->maxLength(255)
                ->dehydrateStateUsing(fn (?string $state): string => ObRoom::normalizeName($state))
                // Nama yang sama boleh ada di lokasi berbeda.
                ->unique(
                    table: 'ob_rooms',
                    column: 'name',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('asset_location_id', $get('asset_location_id'))
                        ->whereNull('deleted_at'),
                ),

            Select::make('asset_location_id')
                ->label('Lokasi')
                ->relationship('location', 'name')
                ->placeholder('Semua lokasi')
                ->helperText('Kosongkan bila ruangan ini tidak terikat lokasi tertentu.')
                ->searchable()
                ->preload()
                ->live(),

            Toggle::make('is_active')
                ->label('Aktif')
                ->helperText('Ruangan nonaktif tidak muncul saat menugaskan OB.')
                ->default(true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Ruangan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('location.name')
                    ->label('Lokasi')
                    ->placeholder('Semua lokasi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shifts_count')
                    ->label('Dipakai di Shift')
                    ->counts('shifts')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('checksheets_count')
                    ->label('Riwayat Checksheet')
                    ->counts('checksheets')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('asset_location_id')
                    ->label('Lokasi')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::mergeBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageObRooms::route('/'),
        ];
    }

    /**
     * Rapikan ruangan duplikat atau salah ketik: pilih semua variannya, tentukan
     * nama yang benar, riwayat checksheet dan daftar shift ikut pindah.
     */
    private static function mergeBulkAction(): BulkAction
    {
        return BulkAction::make('merge')
            ->label('Gabungkan')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->visible(fn (): bool => (auth()->user()?->can('update', new ObRoom) ?? false)
                && (auth()->user()?->can('deleteAny', ObRoom::class) ?? false))
            ->modalHeading('Gabungkan Ruangan')
            ->modalDescription('Riwayat checksheet dan daftar shift dari ruangan yang dipilih dipindahkan ke ruangan tujuan, lalu ruangan lainnya dihapus. Nama ruangan di riwayat checksheet ikut dibetulkan.')
            ->modalSubmitActionLabel('Gabungkan')
            ->form(fn (Collection $records): array => [
                Select::make('target_id')
                    ->label('Gabungkan ke')
                    ->options($records
                        ->loadMissing('location')
                        ->mapWithKeys(fn (ObRoom $room): array => [$room->id => $room->label()])
                        ->all())
                    ->required(),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $target = $records->firstWhere('id', (int) $data['target_id']);

                if (! $target instanceof ObRoom) {
                    return;
                }

                $merged = ObRoom::mergeInto($target, $records);

                Notification::make()
                    ->title("{$merged} ruangan digabungkan ke {$target->name}")
                    ->success()
                    ->send();
            });
    }
}
