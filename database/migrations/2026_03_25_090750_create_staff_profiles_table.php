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
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('staff_number', 50)->unique();
            $table->enum('title', ['Mr', 'Mrs', 'Ms', 'Dr', 'Prof']);
            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 100)->default('Tanzanian');
            $table->string('specialization')->nullable();
            $table->enum('employment_type', ['fulltime', 'parttime', 'contract']);
            $table->string('office_location', 100)->nullable();
            $table->date('joined_date')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
