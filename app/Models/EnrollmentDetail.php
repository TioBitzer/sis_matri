<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'academic_offering_id',
        'status',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function academicOffering(): BelongsTo
    {
        return $this->belongsTo(AcademicOffering::class);
    }
}