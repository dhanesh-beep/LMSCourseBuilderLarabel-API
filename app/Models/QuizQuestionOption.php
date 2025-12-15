<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuizQuestionOption extends Model
{

    protected $table = 'lms_quiz_question_options';

    protected $fillable = [
        'question_id',
        'option_text',
        'is_correct',
    ];

    // -------------------------
    // Relationships
    // -------------------------

    // Option belongs to a question
    public function question()
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}
