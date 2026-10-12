<?php

namespace App\Providers;

use App\Enums\BadgeColor;
use App\Filament\Auth\Pages\PhoneLogin;
use App\Models\Area;
use App\Models\AssetRequest;
use App\Models\AssetService;
use App\Models\AssetTransfer;
use App\Models\Insurance;
use App\Models\InsuranceCategory;
use App\Models\InsuranceProvider;
use App\Models\InsuranceScope;
use App\Models\ItemRequest;
use App\Models\ItemRequestLog;
use App\Models\ItemRequestSetting;
use App\Models\ItemRequestType;
use App\Models\ObChecksheet;
use App\Models\ObRoom;
use App\Models\ObShift;
use App\Models\ProblemReport;
use App\Models\ProblemReportCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rent;
use App\Models\Task;
use App\Models\UnitType;
use App\Policies\ActivityPolicy;
use App\Policies\AreaPolicy;
use App\Policies\AssetRequestPolicy;
use App\Policies\AssetServicePolicy;
use App\Policies\AssetTransferPolicy;
use App\Policies\ExceptionPolicy;
use App\Policies\InsuranceCategoryPolicy;
use App\Policies\InsurancePolicy;
use App\Policies\InsuranceProviderPolicy;
use App\Policies\InsuranceScopePolicy;
use App\Policies\ItemRequestLogPolicy;
use App\Policies\ItemRequestPolicy;
use App\Policies\ItemRequestSettingPolicy;
use App\Policies\ItemRequestTypePolicy;
use App\Policies\ObChecksheetPolicy;
use App\Policies\ObRoomPolicy;
use App\Policies\ObShiftPolicy;
use App\Policies\ProblemReportCategoryPolicy;
use App\Policies\ProblemReportPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RentPolicy;
use App\Policies\RolePolicy;
use App\Policies\TaskPolicy;
use App\Policies\UnitTypePolicy;
use App\Support\MekayaAssets;
use App\Support\ObservabilityAccess;
use App\Support\StoredFile;
use Apriansyahrs\MekayaTheme\Mekaya;
use BezhanSalleh\FilamentExceptions\Models\Exception;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Facades\FilamentColor;
use Filament\Tables\Columns\ImageColumn;
use Filament\Widgets\Widget;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(Mekaya::class, fn (): MekayaAssets => new MekayaAssets);

        Livewire::component('phone-login', PhoneLogin::class);
        ObservabilityAccess::register();
        $this->registerAuthorization();
        $this->configureHttps();
        $this->configureLivewireUploads();
        $this->configureFilamentShield();
        $this->configureRateLimiting();
        $this->registerFilamentBadgeColors();

        View::prependNamespace('filament-panels', resource_path('views/vendor/filament-panels'));

        // Preserve Filament v3 layout behavior: Section/Grid/Fieldset span all columns.
        Fieldset::configureUsing(fn (Fieldset $fieldset) => $fieldset->columnSpanFull());
        Grid::configureUsing(fn (Grid $grid) => $grid->columnSpanFull());
        Section::configureUsing(fn (Section $section) => $section->columnSpanFull());
        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->disk('public')->visibility(fn (): string => StoredFile::uploadVisibility()));
        ImageEntry::configureUsing(fn (ImageEntry $image) => $image->visibility(fn (): string => StoredFile::uploadVisibility()));
        ImageColumn::configureUsing(fn (ImageColumn $image) => $image->visibility(fn (): string => StoredFile::uploadVisibility()));
        ExportAction::configureUsing(fn (ExportAction $action) => $action->fileDisk(fn (): string => config('filesystems.default')));
        ExportBulkAction::configureUsing(fn (ExportBulkAction $action) => $action->fileDisk(fn (): string => config('filesystems.default')));

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['id', 'en'])
                ->circular()
                ->renderHook('panels::user-menu.before');
        });
    }

    /**
     * Force HTTPS URLs when the app is served over HTTPS (e.g. Filament FileUpload previews).
     */
    protected function configureHttps(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    protected function registerAuthorization(): void
    {
        Gate::policy(AssetRequest::class, AssetRequestPolicy::class);
        Gate::policy(AssetService::class, AssetServicePolicy::class);
        Gate::policy(AssetTransfer::class, AssetTransferPolicy::class);
        Gate::policy(ObChecksheet::class, ObChecksheetPolicy::class);
        Gate::policy(ObRoom::class, ObRoomPolicy::class);
        Gate::policy(ObShift::class, ObShiftPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Exception::class, ExceptionPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Area::class, AreaPolicy::class);
        Gate::policy(Insurance::class, InsurancePolicy::class);
        Gate::policy(InsuranceCategory::class, InsuranceCategoryPolicy::class);
        Gate::policy(InsuranceProvider::class, InsuranceProviderPolicy::class);
        Gate::policy(InsuranceScope::class, InsuranceScopePolicy::class);
        Gate::policy(ItemRequest::class, ItemRequestPolicy::class);
        Gate::policy(ItemRequestLog::class, ItemRequestLogPolicy::class);
        Gate::policy(ItemRequestSetting::class, ItemRequestSettingPolicy::class);
        Gate::policy(ItemRequestType::class, ItemRequestTypePolicy::class);
        Gate::policy(ProblemReport::class, ProblemReportPolicy::class);
        Gate::policy(ProblemReportCategory::class, ProblemReportCategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Rent::class, RentPolicy::class);
        Gate::policy(UnitType::class, UnitTypePolicy::class);
    }

    /**
     * Keep Livewire temp uploads on the private local disk (not public/),
     * so they are not web-accessible and do not depend on public/ subdirectory perms.
     */
    protected function configureLivewireUploads(): void
    {
        config([
            'livewire.temporary_file_upload.disk' => 'local',
            'livewire.temporary_file_upload.directory' => 'livewire-tmp',
        ]);
    }

    /**
     * Keep permissions attached to roles created before the Shield v4 upgrade.
     *
     * Settings Hub pages already authorize Shield v4's `View:*` keys, while
     * application resources and widgets still have persisted Shield v3 keys.
     */
    protected function configureFilamentShield(): void
    {
        FilamentShield::buildPermissionKeyUsing(
            function (string $entity, string $affix, string $subject, string $case, string $separator): string {
                if (is_subclass_of($entity, Resource::class)) {
                    return Str::of($affix)
                        ->snake()
                        ->append('_')
                        ->append(
                            Str::of($entity)
                                ->afterLast('\\')
                                ->beforeLast('Resource')
                                ->snake()
                                ->replace('_', '::')
                        )
                        ->toString();
                }

                if (is_subclass_of($entity, Widget::class)) {
                    return Str::of('widget_')
                        ->append(class_basename($entity))
                        ->toString();
                }

                return FilamentShield::defaultPermissionKeyBuilder(
                    affix: $affix,
                    separator: $separator,
                    subject: $subject,
                    case: $case,
                );
            }
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter untuk endpoint publik pengajuan aset (tanpa auth).
        // Membatasi spam + memperkecil window race condition reference_number.
        RateLimiter::for('public', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($request->ip());
        });
    }

    /**
     * Warna BadgeColor (amber, pink, rose, …) dipakai di badge entitas bisnis.
     * Harus didaftarkan ke Filament agar CSS variable warna ter-generate.
     */
    protected function registerFilamentBadgeColors(): void
    {
        FilamentColor::register(
            collect(BadgeColor::cases())
                ->mapWithKeys(fn (BadgeColor $color) => [$color->value => $color->getColor()])
                ->all()
        );
    }
}
