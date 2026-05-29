<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_fees', function (Blueprint $table) {
            $table->id();
            $table->integer('grade_level');
            $table->string('school_year');          // e.g. "2024-2025"
            $table->decimal('tuition_fee', 10, 2)->default(0);
            $table->decimal('misc_fee',    10, 2)->default(0);
            $table->decimal('other_fee',   10, 2)->default(0);
            $table->decimal('total_fee',   10, 2)->storedAs('tuition_fee + misc_fee + other_fee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['grade_level', 'school_year']);
            $table->index('grade_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_fees');
    }
};