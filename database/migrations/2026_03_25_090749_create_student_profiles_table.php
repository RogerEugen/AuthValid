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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('registration_number', 50)->unique();
            $table->foreignId('program_id')
                  ->constrained('programs')
                  ->onDelete('restrict');
            $table->tinyInteger('year_of_study');
            $table->tinyInteger('semester');
            $table->string('academic_year', 9);
            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 100)->default('Tanzanian');
            $table->year('admission_year');
            $table->enum('enrollment_status', [
                'active',
                'suspended',
                'deferred',
                'graduated'
            ])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
