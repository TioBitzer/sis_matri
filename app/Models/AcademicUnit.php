<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'name',
        'code',
        'description',
        'unit_type',
        'credits',
        'hours',
        'is_active',
    ];

    protected $casts = [
        'credits' => 'integer',
        'hours' => 'integer',
        'is_active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function curriculumItems(): HasMany
    {
        return $this->hasMany(AcademicCurriculumItem::class);
    }

    public function academicOfferings(): HasMany
    {
        return $this->hasMany(AcademicOffering::class);
    }
}