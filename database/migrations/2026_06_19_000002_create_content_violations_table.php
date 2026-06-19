<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('user_role', 30);
            $table->string('content_fingerprint', 64);
            $table->unsignedTinyInteger('sequence');
            $table->boolean('student_affairs_review')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('student_affairs_review');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_violations');
    }
};
