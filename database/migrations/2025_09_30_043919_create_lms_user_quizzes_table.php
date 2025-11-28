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
        Schema::create('lms_user_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')
                ->constrained('lms_lesson_widgets')
                ->cascadeOnDelete();
            $table->foreignId('quiz_id')
                ->constrained('lms_quizzes')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->integer('time_taken')->default(0)->comment('time taken in seconds');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_user_quizzes');
    }
};
