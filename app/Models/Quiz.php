<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{

    protected $table = 'lms_quizzes';

    protected $fillable = [
        'title',
        'passing_score',
        'description',
        'author',
    ];

    // -------------------------
    // Relationships
    // -------------------------
    // Quiz has many questions
    public function questions()
    {
        return $this->hasMany(QuizQuestion::class, 'quiz_id')->orderBy('question_order');
    }
}
