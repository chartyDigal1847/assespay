<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove duplicate payments for the same student on the same date with the same amount
        // Keep only the first one (oldest)
        
        DB::statement("
            DELETE FROM payments
            WHERE id NOT IN (
                SELECT MIN(id)
                FROM (
                    SELECT MIN(id) as id
                    FROM payments
                    GROUP BY student_id, DATE(paid_at), amount
                ) as subquery
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration removes data, so we can't easily reverse it
        // No action needed
    }
};
