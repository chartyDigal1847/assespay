<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rooms table first (enrollments references it)
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->tinyInteger('grade_level');
            $table->string('section', 80)->nullable();
            $table->string('adviser', 150)->nullable();
            $table->unsignedSmallInteger('capacity_male')->default(0);
            $table->unsignedSmallInteger('capacity_female')->default(0);
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            
            // Personal
            $table->string('student_name', 200);
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('middle_name', 80)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('nationality', 60)->default('Filipino');
            $table->string('email', 150);
            $table->string('contact_number', 20)->nullable();
            $table->string('address', 300)->nullable();
            
            // Academic
            $table->tinyInteger('grade_level');
            $table->string('school_year', 20)->nullable();
            $table->string('previous_school', 150)->nullable();
            $table->tinyInteger('last_grade_completed')->nullable();
            $table->decimal('average_grade', 5, 2)->nullable();
            $table->string('lrn', 12)->nullable();
            
            // Guardian
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_relationship', 50)->nullable();
            $table->string('guardian_contact', 20)->nullable();
            $table->string('guardian_email', 150)->nullable();
            $table->string('guardian_occupation', 100)->nullable();
            
            // Documents
            $table->string('psa_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('report_card_path')->nullable();
            
            // Status & room
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->enum('status', ['pending', 'verified', 'approved', 'rejected', 'enrolled', 'cancelled'])->default('pending');
            $table->text('remarks')->nullable();
            
            $table->timestamps();
            
            $table->index(['email', 'status']);
            $table->index('grade_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('rooms');
    }
};
