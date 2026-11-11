{{-- Peta tabel per domain untuk halaman Dokumentasi Teknis. Warna diatur kelas .td-erd di technical-docs.blade.php. --}}
<figure class="td-erd">
    <svg viewBox="0 0 760 664" role="img" aria-label="users dan business_entities menjadi poros semua domain" font-size="11.5" font-family="inherit">
        <g class="t-ink">
            <text x="24" y="30" font-size="15" font-weight="600">users dan business_entities menjadi poros semua domain</text>
        </g>
        <g class="t-quiet">
            <text x="24" y="50">Garis = foreign key ke tabel inti. Baris bawah = modul pindahan sumo-web.</text>
        </g>

        <g class="s-edge" fill="none" stroke-width="1.25">
            <path d="M40 176V304"/><path d="M206 256V304"/><path d="M436 160V304"/><path d="M612 208V304"/>
            <path d="M404 144H420"/><path d="M372 344H388"/>
            <path d="M40 384V456"/><path d="M282 384V456"/><path d="M524 384V456"/>
            <rect x="24" y="72" width="150" height="104" rx="8"/>
            <rect x="190" y="72" width="214" height="184" rx="8"/>
            <rect x="420" y="72" width="166" height="88" rx="8"/>
            <rect x="602" y="72" width="134" height="136" rx="8"/>
            <rect x="24" y="456" width="226" height="184" rx="8"/>
            <rect x="266" y="456" width="226" height="72" rx="8"/>
            <rect x="508" y="456" width="226" height="152" rx="8"/>
        </g>
        <rect class="core" x="24" y="304" width="348" height="80" rx="8" stroke-width="2"/>
        <rect class="core" x="388" y="304" width="348" height="80" rx="8" stroke-width="2"/>

        <g class="t-quiet">
            <text x="48" y="276">users.division_id</text>
            <text x="48" y="292">users.job_title_id</text>
            <text x="214" y="276">recipient_id,</text>
            <text x="214" y="292">business_entity_id</text>
            <text x="444" y="276">from_user_id, to_user_id</text>
            <text x="444" y="292">business_entity_id</text>
            <text x="620" y="276">user_id,</text>
            <text x="620" y="292">business_entity_id</text>
            <text x="48" y="424">user_id, business_entity_id</text>
            <text x="290" y="424">user_id, business_entity_id</text>
            <text x="532" y="424">user_id, business_entity_id</text>
            <text x="432" y="144">asset_id ke assets</text>
        </g>

        <g class="t-ink">
            <text x="36" y="96" font-size="13" font-weight="600">Organisasi</text>
            <text x="36" y="112">areas</text>
            <text x="36" y="128">divisions</text>
            <text x="36" y="144">division_approvers</text>
            <text x="36" y="160">job_titles</text>

            <text x="202" y="96" font-size="13" font-weight="600">Aset</text>
            <text x="202" y="112" font-weight="600">assets</text>
            <text x="202" y="128">asset_attributes</text>
            <text x="202" y="144">custom_asset_attributes</text>
            <text x="202" y="160">asset_qrs, asset_qr_scans</text>
            <text x="202" y="176">asset_services</text>
            <text x="202" y="192">asset_reconciliations</text>
            <text x="202" y="208">vehicle_checksheets</text>
            <text x="202" y="224">categories, brands</text>
            <text x="202" y="240">asset_locations</text>

            <text x="432" y="96" font-size="13" font-weight="600">Transfer / BA</text>
            <text x="432" y="112" font-weight="600">asset_transfers</text>
            <text x="432" y="128">asset_transfer_details</text>

            <text x="614" y="96" font-size="13" font-weight="600">OB dan Tugas</text>
            <text x="614" y="112" font-weight="600">ob_checksheets</text>
            <text x="614" y="128">ob_rooms</text>
            <text x="614" y="144">ob_shifts</text>
            <text x="614" y="160">ob_shift_rooms</text>
            <text x="614" y="176">tasks</text>
            <text x="614" y="192">vendors</text>

            <text x="40" y="328" font-size="15" font-weight="600">users</text>
            <text x="40" y="348">pemegang aset, pemohon, pelapor, approver</text>
            <text x="40" y="364">approver_user_id ke users, roles via Spatie</text>

            <text x="404" y="328" font-size="15" font-weight="600">business_entities</text>
            <text x="404" y="348">badan usaha, dasar pembatasan akses data</text>
            <text x="404" y="364">N:M ke users lewat business_entity_user</text>

            <text x="36" y="480" font-size="13" font-weight="600">Pengajuan Barang</text>
            <text x="36" y="496" font-weight="600">item_requests</text>
            <text x="36" y="512">item_request_details</text>
            <text x="36" y="528">item_request_approvals</text>
            <text x="36" y="544">item_request_logs</text>
            <text x="36" y="560">item_request_types</text>
            <text x="36" y="576">item_request_scopes</text>
            <text x="36" y="592">item_request_settings</text>
            <text x="36" y="608">products, product_categories</text>
            <text x="36" y="624">unit_types</text>

            <text x="278" y="480" font-size="13" font-weight="600">Lapor Gangguan</text>
            <text x="278" y="496" font-weight="600">problem_reports</text>
            <text x="278" y="512">problem_report_categories</text>
            <text x="278" y="528">problem_report_scopes</text>

            <text x="520" y="480" font-size="13" font-weight="600">Asuransi dan Sewa</text>
            <text x="520" y="496" font-weight="600">insurances</text>
            <text x="520" y="512">insurance_updates</text>
            <text x="520" y="528">insurance_providers</text>
            <text x="520" y="544">insurance_categories</text>
            <text x="520" y="560">insurance_scopes</text>
            <text x="520" y="576" font-weight="600">rents</text>
            <text x="520" y="592">rent_updates</text>
        </g>
    </svg>
</figure>
