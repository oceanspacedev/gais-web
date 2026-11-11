<?php

namespace Tests\Support;

use App\Models\AssetAttribute;
use App\Models\CustomAssetAttribute;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel atribut kustom untuk tes yang membangun skema SQLite sendiri. Serial
 * number, IMEI, dan tipe aset disimpan di asset_attributes (atribut ber-key),
 * dan pencarian aset membaca asset_attributes.search_value, jadi setiap skema
 * yang menyimpan aset membutuhkannya.
 */
final class AssetIdentitySchema
{
    public static function ensure(): void
    {
        if (! Schema::hasTable('custom_asset_attributes')) {
            Schema::create('custom_asset_attributes', function (Blueprint $table): void {
                $table->softDeletes();
                $table->id();
                $table->string('key', 50)->nullable()->unique();
                $table->string('name');
                $table->string('type')->default('text');
                $table->boolean('required')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('category_id')->nullable();
                $table->boolean('is_notifiable')->default(false);
                $table->string('notification_type')->nullable();
                $table->integer('notification_offset')->nullable();
                $table->date('fixed_notification_date')->nullable();
                $table->json('notification_channels')->nullable();
                $table->json('notification_recipient_user_ids')->nullable();
                $table->json('notification_recipient_emails')->nullable();
                $table->json('notification_recipient_whatsapp_numbers')->nullable();
                $table->timestamps();
            });
        } else {
            // Skema minimal di beberapa tes: lengkapi kolom yang dipakai CustomAssetAttribute::idForKey().
            $missing = array_filter(['key', 'required', 'is_active', 'category_id'], fn (string $column): bool => ! Schema::hasColumn('custom_asset_attributes', $column));

            Schema::table('custom_asset_attributes', function (Blueprint $table) use ($missing): void {
                foreach ($missing as $column) {
                    match ($column) {
                        'key' => $table->string('key', 50)->nullable()->unique(),
                        'category_id' => $table->json('category_id')->nullable(),
                        'required' => $table->boolean('required')->default(false),
                        'is_active' => $table->boolean('is_active')->default(true),
                    };
                }
            });
        }

        if (! Schema::hasTable('asset_attributes')) {
            Schema::create('asset_attributes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->unsignedBigInteger('custom_attribute_id');
                $table->text('attribute_value')->nullable();
                $table->text('search_value')->nullable()->storedAs(AssetAttribute::SEARCH_VALUE_SQL);
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('asset_attributes', 'search_value')) {
            // SQLite hanya bisa menambah kolom generated VIRTUAL lewat ALTER TABLE.
            Schema::table('asset_attributes', function (Blueprint $table): void {
                $table->text('search_value')->nullable()->virtualAs(AssetAttribute::SEARCH_VALUE_SQL);
            });
        }

        CustomAssetAttribute::forgetKeyIds();
    }
}
