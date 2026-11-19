<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'chat_id',
        "applicant_name",
        'curp',
        'process_type',
        "gender",
        'current_stage_id',
        'current_question_id',
        'assigned_user_id',
        'process_status',
        'rejection_reason',
        'group_id',
        'confirmation_status',
        'reminder_level',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'current_stage_id');
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'chat_id', 'chat_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ApplicantQuestionResponse::class);
    }
}
