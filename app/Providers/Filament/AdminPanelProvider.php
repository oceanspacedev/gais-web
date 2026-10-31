<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Plugins\FilamentExceptionsPlugin;
use App\Filament\Resources\AssetLocationResource;
use App\Filament\Resources\AssetResource\Widgets\CustomAssetWidget;
use App\Filament\Resources\ObChecksheetResource\Pages\ListObChecksheets;
use App\Filament\Resources\ObChecksheetResource\Pages\ViewObChecksheet;
use Apriansyahrs\MekayaTheme\Livewire\MekayaSidebar;
use Apriansyahrs\MekayaTheme\MekayaPlugin;
use Asmit\ResizedColumn\ResizedColumnPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use TomatoPHP\FilamentPWA\FilamentPWAPlugin;
use TomatoPHP\FilamentSettingsHub\FilamentSettingsHubPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(
                MekayaPlugin::make()
                    ->colors(['primary' => Color::Blue]),
            )
            ->sidebarLivewireComponent(MekayaSidebar::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => view('auth.login-extra'),
            )
            // Foto OB Checksheet bisa diklik untuk diperbesar.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.ob-checksheet.image-lightbox'),
                scopes: [ListObChecksheets::class, ViewObChecksheet::class],
            )
            // Menu dikelompokkan per modul; urutan grup mengikuti daftar ini.
            ->navigationGroups([
                NavigationGroup::make('Aset'),
                NavigationGroup::make('Master Aset'),
                NavigationGroup::make('OB / Kebersihan'),
                NavigationGroup::make('Kendaraan'),
                NavigationGroup::make('Pekerjaan Vendor'),
                NavigationGroup::make('Pengajuan Barang'),
                NavigationGroup::make('Lapor Gangguan'),
                NavigationGroup::make('Asuransi & Sewa'),
                NavigationGroup::make('Organisasi'),
                NavigationGroup::make('Pengaturan')->collapsed(),
            ])
            // Lokasi dipakai aset dan OB, jadi grupnya mendapat pintasan ke menu Lokasi di Organisasi.
            ->navigationItems([
                $this->locationShortcut('Master Aset'),
                $this->locationShortcut('OB / Kebersihan'),
            ])
            ->passwordReset(null, null)
            ->profile(null)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            // Dasbor manajemen: seksi per modul, widget dijaga izin widget_* (lihat App\Filament\Pages\Dashboard).
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                CustomAssetWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->databaseNotifications(isLazy: false)
            // Tiap polling adalah satu request penuh per tab yang terbuka; 2 detik membebani
            // server dan memperlambat request lain. Tautan unduhan ekspor tetap muncul <= 10 detik.
            ->databaseNotificationsPolling('10s')
            ->globalSearchDebounce('300ms')
            ->plugins([
                ResizedColumnPlugin::make(),
                FilamentPWAPlugin::make(),
                FilamentSettingsHubPlugin::make()
                    ->allowSiteSettings()
                    ->allowSocialMenuSettings()
                    ->allowShield(),
                FilamentExceptionsPlugin::make(),
                FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 2,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
            ])
            ->maxContentWidth(Width::Full);
    }

    private function locationShortcut(string $group): NavigationItem
    {
        return NavigationItem::make(fn (): string => AssetLocationResource::getNavigationLabel())
            ->group($group)
            ->sort(99)
            ->icon('heroicon-o-map-pin')
            ->url(fn (): string => AssetLocationResource::getUrl())
            ->visible(fn (): bool => AssetLocationResource::canViewAny())
            // Yang ditandai aktif cukup menu aslinya.
            ->isActiveWhen(fn (): bool => false);
    }
}
