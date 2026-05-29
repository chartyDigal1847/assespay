<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_tokens', function (Blueprint $table) {
            $table->string('token')->primary()->comment('Single-use SSO token from portal');

            $table->string('sso_id')->comment('User ID from DEORIS portal');
            $table->string('sso_role')->comment('User role (admin, cashier, student)');
            $table->string('sso_name')->comment('User full name from portal');
            $table->string('sso_email')->comment('User email from portal');

            $table->text('portal_signature')->comment('Portal-signed signature of user identity');
            $table->dateTime('portal_issued_at')->comment('When portal issued the token');

            $table->dateTime('exchanged_at')->nullable()->comment('When token was exchanged (null = not yet used)');

            $table->timestamps();

            $table->index('sso_id');
            $table->index('portal_issued_at');
            $table->index('exchanged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_tokens');
    }
};