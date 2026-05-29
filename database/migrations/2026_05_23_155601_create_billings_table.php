<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->string('billing_number')->unique();   // e.g. BILL-2024-000001
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->integer('grade_level');
            $table->string('school_year');
            $table->decimal('tuition_fee', 10, 2)->default(0);
            $table->decimal('misc_fee',    10, 2)->default(0);
            $table->decimal('other_fee',   10, 2)->default(0);
            $table->decimal('total_fee',   10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance',     10, 2)->default(0);
            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->string('enrollment_status')->default('enrolled');
            $table->string('source')->default('enrollease');  // which module triggered it
            $table->timestamps();

            $table->index(['student_id', 'school_year']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};