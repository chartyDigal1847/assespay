<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_id')->constrained()->cascadeOnDelete();
    $table->decimal('tuition', 10, 2);
    $table->decimal('misc', 10, 2);
    $table->decimal('lab', 10, 2);
    $table->decimal('total', 10, 2);
    $table->decimal('paid', 10, 2)->default(0);
    $table->decimal('balance', 10, 2);
    $table->enum('status', ['paid', 'pending', 'overdue'])->default('pending');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
