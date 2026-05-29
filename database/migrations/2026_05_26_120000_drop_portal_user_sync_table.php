<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('portal_user_sync');
    }

    public function down(): void
    {
        // Identity mirror is not restored — DEORIS SSO session is canonical.
    }
};
