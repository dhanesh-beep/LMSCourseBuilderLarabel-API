<?php

namespace App\Models\lms;


use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{

    protected $table = 'lms_quiz_questions';

    protected $fillable = [
        'quiz_id',
        'question_title',
        'question_type',
        'question_order'
    ];

    // -------------------------
    // Relationships
    // -------------------------

    // Question belongs to a quiz
    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    // Question has many options
    public function options()
    {
        return $this->hasMany(QuizQuestionOption::class, 'question_id');
    }

    // Helper to get only correct options
    public function correctOptions()
    {
        return $this->options()->where('is_correct', true);
    }
}
