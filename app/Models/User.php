<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{

    /**
     * Valor por defecto para usuarios recien creados.
     * La columna users.is_platform_admin tambien tiene DEFAULT FALSE.
     * No agregar este campo a $fillable ni aceptarlo desde peticiones.
     */
    protected $attributes = [
        'is_platform_admin' => false,
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'institution_user')
            ->using(InstitutionUser::class)
            ->withPivot([
                'id',
                'person_id',
                'is_active',
                'role',
            ])
            ->withTimestamps();
    }
}
