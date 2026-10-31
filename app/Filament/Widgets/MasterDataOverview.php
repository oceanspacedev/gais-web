<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AreaResource;
use App\Filament\Resources\AssetLocationResource;
use App\Filament\Resources\BrandResource;
use App\Filament\Resources\BusinessEntityResource;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\CustomAssetAttributeResource;
use App\Filament\Resources\DivisionResource;
use App\Filament\Resources\InsuranceCategoryResource;
use App\Filament\Resources\InsuranceProviderResource;
use App\Filament\Resources\InsuranceScopeResource;
use App\Filament\Resources\ItemRequestTypeResource;
use App\Filament\Resources\JobTitleResource;
use App\Filament\Resources\ObRoomResource;
use App\Filament\Resources\ObShiftResource;
use App\Filament\Resources\ProblemReportCategoryResource;
use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\UnitTypeResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VendorResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Support\Dashboard\Rupiah;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;

/**
 * Dasbor > Organisasi & Data Master: jumlah record master per menu dalam satu
 * kartu ringkas (pengganti Overlook yang memenuhi dasbor dengan 40 kartu besar).
 * Hanya menu yang boleh dibuka pengguna yang ditampilkan.
 */
class MasterDataOverview extends Widget
{
    use GuardsWidgetWithPermission;

    protected string $view = 'filament.widgets.master-data-overview';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, list<class-string<resource>>> kelompok => resource
     */
    public static function groups(): array
    {
        return [
            'Organisasi' => [UserResource::class, BusinessEntityResource::class, DivisionResource::class, JobTitleResource::class, AreaResource::class, AssetLocationResource::class],
            'Master Aset' => [CategoryResource::class, BrandResource::class, VendorResource::class, CustomAssetAttributeResource::class],
            'Pengajuan & Gangguan' => [ProductResource::class, ProductCategoryResource::class, UnitTypeResource::class, ItemRequestTypeResource::class, ProblemReportCategoryResource::class],
            'Asuransi & OB' => [InsuranceProviderResource::class, InsuranceCategoryResource::class, InsuranceScopeResource::class, ObRoomResource::class, ObShiftResource::class],
        ];
    }

    protected static function hasAnythingToShow(): bool
    {
        return collect(self::groups())->flatten()->contains(fn (string $resource): bool => $resource::canViewAny());
    }

    /**
     * @return array<string, list<array{label: string, count: string, url: string, icon: mixed}>>
     */
    public function items(): array
    {
        $groups = [];

        foreach (self::groups() as $group => $resources) {
            foreach ($resources as $resource) {
                if (! $resource::canViewAny()) {
                    continue;
                }

                $groups[$group][] = [
                    'label' => $resource::getPluralModelLabel(),
                    'count' => Rupiah::number((int) $resource::getEloquentQuery()->count()),
                    'url' => $resource::getUrl(),
                    'icon' => $resource::getNavigationIcon(),
                ];
            }
        }

        return $groups;
    }

    protected function getViewData(): array
    {
        return ['groups' => $this->items()];
    }
}
