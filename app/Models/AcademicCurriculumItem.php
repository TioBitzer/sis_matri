<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicCurriculumItem extends Model
{
    protected $fillable = [
        'academic_plan_id',
        'academic_level_id',
        'academic_unit_id',
        'credits',
        'hours',
        'is_required',
        'position',
    ];

    protected $casts = [
        'credits' => 'integer',
        'hours' => 'integer',
        'is_required' => 'boolean',
        'position' => 'integer',
    ];

    public function academicPlan(): BelongsTo
    {
        return $this->belongsTo(AcademicPlan::class);
    }

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }

    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }
}