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
         Schema::create('lms_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')
                ->constrained('lms_quizzes')
                ->cascadeOnDelete();
            $table->text('question_title');
            $table->string('question_type');
            $table->string('question_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_quiz_questions');
    }
};
