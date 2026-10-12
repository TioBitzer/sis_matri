<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_plan_id',
        'name',
        'order',
        'is_active',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function academicPlan(): BelongsTo
    {
        return $this->belongsTo(AcademicPlan::class);
    }

    public function curriculumItems(): HasMany
    {
        return $this->hasMany(AcademicCurriculumItem::class);
    }
}