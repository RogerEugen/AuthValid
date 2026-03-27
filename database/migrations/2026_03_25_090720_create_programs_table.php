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
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')
                  ->constrained('departments')
                  ->onDelete('cascade');
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->enum('level', [
                'basic_certificate',
                'certificate',
                'diploma',
                'higher_diploma',
                'postgraduate_diploma',
                'bachelors',
                'masters',
                'phd'
            ]);
            $table->decimal('duration_years', 4, 2);
            $table->string('duration_display', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
