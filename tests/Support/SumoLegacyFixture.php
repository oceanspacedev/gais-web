<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tiruan kecil skema sumo-web di SQLite memori untuk menguji sumo:import.
 * Hanya kolom yang dibaca importer yang dibuat; semuanya nullable.
 */
final class SumoLegacyFixture
{
    public const CONNECTION = 'sumo';

    /** @var array<string, array<string, string>> tabel => kolom => tipe Blueprint */
    private const TABLES = [
        'areas' => ['area' => 'string'],
        'badan_usahas' => ['badan_usaha' => 'string'],
        'divisions' => ['division' => 'string', 'area_id' => 'integer'],
        'roles' => ['role' => 'string'],
        'users' => [
            'username' => 'string', 'fullname' => 'string', 'password' => 'string', 'badan_usaha_id' => 'integer',
            'division_id' => 'integer', 'role_id' => 'integer', 'approval_id' => 'integer', 'profile_picture' => 'string', 'notif_id' => 'string',
        ],
        'categories' => ['category' => 'string'],
        'unit_types' => ['unit_type' => 'string'],
        'products' => [
            'item_code' => 'string', 'product' => 'string', 'category_id' => 'integer', 'unit_type_id' => 'integer',
            'price' => 'float', 'description' => 'string', 'product_image' => 'string', 'stock' => 'float',
        ],
        'request_type' => ['request_type' => 'string', 'pic_division_id' => 'integer'],
        'request_settings' => ['request_month' => 'dateTime', 'open_date' => 'dateTime', 'closed_date' => 'dateTime', 'request_detail' => 'string'],
        'requests' => [
            'request_code' => 'string', 'user_id' => 'integer', 'date' => 'dateTime', 'total_cost' => 'float', 'status_po' => 'integer',
            'po_number' => 'string', 'status_client' => 'integer', 'closed_by' => 'integer', 'closed_at' => 'dateTime',
            'request_type_id' => 'integer', 'request_file' => 'string', 'request_file_2' => 'string', 'approved_file' => 'string',
            'notes' => 'string', 'user_notes' => 'string', 'audit_notes' => 'text',
        ],
        'request_details' => ['request_id' => 'integer', 'product_id' => 'integer', 'qty_request' => 'float', 'qty_remaining' => 'float', 'qty_approved' => 'float', 'description' => 'string'],
        'request_approvals' => ['request_id' => 'string', 'approval_type' => 'string', 'approved_by' => 'integer', 'approved_at' => 'dateTime'],
        'request_logs' => ['user_id' => 'string', 'request_id' => 'string', 'activity' => 'string'],
        'problem_report_categories' => ['problem_report_category' => 'string'],
        'problem_report' => [
            'problem_report_code' => 'string', 'user_id' => 'integer', 'date' => 'dateTime', 'title' => 'string', 'description' => 'string',
            'photo_before' => 'string', 'photo_after' => 'string', 'status' => 'string', 'scheduled_at' => 'dateTime', 'status_client' => 'integer',
            'review_user' => 'string', 'closed_by' => 'integer', 'closed_at' => 'dateTime', 'pr_category_id' => 'integer', 'result_desc' => 'string',
        ],
        'insurance_providers' => ['insurance_provider' => 'string'],
        'insurance_categories' => ['insurance_category' => 'string'],
        'insurance_scopes' => ['insurance_scope' => 'string'],
        'insurances' => [
            'policy_number' => 'string', 'insured_name' => 'string', 'warehouse_code' => 'string', 'insured_address' => 'string',
            'insured_detail' => 'string', 'risk_address' => 'string', 'stock_inprov_id' => 'integer', 'building_inprov_id' => 'integer',
            'stock_worth' => 'integer', 'building_worth' => 'integer', 'actual_stock_worth' => 'integer', 'stock_premium' => 'integer',
            'building_premium' => 'integer', 'insurance_category_id' => 'integer', 'insurance_scope_id' => 'integer',
            'extension_of_policy' => 'string', 'join_date' => 'string', 'expired_date' => 'dateTime', 'user_id' => 'integer',
            'payment_evidence' => 'string', 'status' => 'string', 'notes' => 'string', 'reminder_phones' => 'string',
        ],
        'insurance_updates' => [
            'insurance_id' => 'integer', 'policy_number' => 'string', 'stock_inprov_id' => 'integer', 'building_inprov_id' => 'integer',
            'stock_worth' => 'integer', 'building_worth' => 'integer', 'actual_stock_worth' => 'integer', 'stock_premium' => 'integer',
            'building_premium' => 'integer', 'extension_of_policy' => 'string', 'join_date' => 'dateTime', 'expired_date' => 'dateTime',
            'user_id' => 'integer', 'status' => 'string', 'payment_evidence' => 'string', 'notes' => 'string',
        ],
        'rents' => [
            'rent_code' => 'string', 'rented_address' => 'string', 'rented_detail' => 'string', 'first_party' => 'string', 'second_party' => 'string',
            'rent_per_year' => 'integer', 'cvcs_fund' => 'integer', 'online_fund' => 'integer', 'join_date' => 'dateTime', 'expired_date' => 'dateTime',
            'deduction_evidence' => 'string', 'deduction_evidence_file' => 'string', 'document' => 'string', 'document_file' => 'string',
            'payment_evidence_file' => 'string', 'status' => 'string', 'month_before_reminder' => 'string', 'user_id' => 'integer',
            'notes' => 'string', 'reminder_phones' => 'string',
        ],
        'rent_updates' => [
            'rent_id' => 'string', 'rent_code' => 'string', 'first_party' => 'string', 'second_party' => 'string', 'rent_per_year' => 'integer',
            'cvcs_fund' => 'integer', 'online_fund' => 'integer', 'join_date' => 'dateTime', 'expired_date' => 'dateTime',
            'deduction_evidence' => 'string', 'deduction_evidence_file' => 'string', 'document' => 'string', 'document_file' => 'string',
            'payment_evidence_file' => 'string', 'status' => 'string', 'month_before_reminder' => 'string', 'user_id' => 'integer', 'notes' => 'string',
        ],
        'wa_notification_settings' => [
            'is_enabled' => 'boolean', 'target_phones' => 'string', 'send_time' => 'string', 'remind_rent' => 'boolean', 'rent_days_before' => 'string',
            'remind_insurance' => 'boolean', 'insurance_days_before' => 'string', 'remind_request_cutoff' => 'boolean', 'request_cutoff_days_before' => 'string',
        ],
        'wa_notification_logs' => ['notif_type' => 'string', 'reference_id' => 'integer', 'phone' => 'string', 'sent_date' => 'date', 'status' => 'string', 'message_preview' => 'text', 'response_payload' => 'text'],
    ];

    /**
     * Mengarahkan koneksi `sumo` ke SQLite memori dan membuat semua tabel.
     */
    public static function install(): void
    {
        config([
            'database.connections.'.self::CONNECTION => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
            'sumo-import.connection' => self::CONNECTION,
        ]);
        DB::purge(self::CONNECTION);

        foreach (self::TABLES as $table => $columns) {
            Schema::connection(self::CONNECTION)->create($table, function (Blueprint $blueprint) use ($columns): void {
                $blueprint->id();

                foreach ($columns as $column => $type) {
                    $blueprint->{$type}($column)->nullable();
                }

                $blueprint->timestamps();
                $blueprint->softDeletes();
            });
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function insert(string $table, array $row): int
    {
        return (int) DB::connection(self::CONNECTION)->table($table)->insertGetId($row + ['created_at' => '2025-01-01 08:00:00', 'updated_at' => '2025-01-01 08:00:00']);
    }
}
