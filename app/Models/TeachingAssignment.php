<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingAssignment extends Model
{
    protected $fillable = [
        'teacher_id',
        'academic_offering_id',
        'role',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function academicOffering(): BelongsTo
    {
        return $this->belongsTo(AcademicOffering::class);
    }
}