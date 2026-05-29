<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove legacy tables superseded by the SOA schema
     * (billing_accounts, tuition_records, balances, etc.).
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        $legacyTables = [
            'billings',
            'fee_assessments',
            'assessments',
            'promissory_notes',
            'student_detections',
            'api_keys',
            'tuition_fees',
            'enrollments',
        ];

        foreach ($legacyTables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Legacy schema is not restored; use earlier migrations if needed.
    }
};
