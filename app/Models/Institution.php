<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;




class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'institution_user'
        )
            ->withPivot([
                'id',
                'person_id',
                'is_active',
            ])
            ->withTimestamps();
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function academicPeriods(): HasMany
    {
        return $this->hasMany(AcademicPeriod::class);
    }

    public function academicPrograms(): HasMany
    {
        return $this->hasMany(AcademicProgram::class);
    }

    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class);
    }
}
