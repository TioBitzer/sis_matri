<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;

class InstitutionUser extends Pivot
{
    protected $table = 'institution_user';

    public $incrementing = true;

    protected $casts = [
        'is_active' => 'boolean',
        'person_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $membership): void {
            $personId = $membership->person_id;

            if ($personId === null) {
                return;
            }

            $validPerson = Person::query()
                ->whereKey($personId)
                ->where('institution_id', $membership->institution_id)
                ->exists();

            if (! $validPerson) {
                throw ValidationException::withMessages([
                    'person_id' => 'La persona debe pertenecer a la misma institución de la membresía.',
                ]);
            }
        });
    }
}
