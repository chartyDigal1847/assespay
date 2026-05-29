<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            try {
                Schema::table('sessions', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (\Throwable) {
                // FK may not exist on all installs
            }
        }

        Schema::dropIfExists('sso_tokens');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');

        if (Schema::hasTable('students') && Schema::hasColumn('students', 'portal_user_id')) {
            try {
                Schema::table('students', function (Blueprint $table) {
                    $table->unique('portal_user_id');
                });
            } catch (\Throwable) {
                // Skip if duplicates or index already exists
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // DEORIS SSO is the sole auth source; local users table is not restored.
    }
};
