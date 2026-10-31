<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// BEGIN INSTITUTION_BOOTSTRAP_ADMIN_3B
\Illuminate\Support\Facades\Artisan::command(
    'institution:bootstrap-admin
        {institution_id : ID de la institucion existente}
        {user_id : ID del usuario existente}
        {--confirm : Confirma la asignacion inicial de privilegios}',
    function (string $institution_id, string $user_id): int {
        if (! $this->option('confirm')) {
            $this->error('Operacion rechazada: usa --confirm despues de verificar los IDs.');
            return 1;
        }

        $validId = static fn (string $value) => filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $institutionId = $validId($institution_id);
        $userId = $validId($user_id);

        if ($institutionId === false || $userId === false) {
            $this->error('Los dos identificadores deben ser enteros positivos validos.');
            return 1;
        }

        try {
            // Lock de la institucion: serializa esta operacion con la gestion de
            // membresias de InstitutionMembershipController.
            $result = \Illuminate\Support\Facades\DB::transaction(
                function () use ($institutionId, $userId): array {
                    $institution = \Illuminate\Support\Facades\DB::table('institutions')
                        ->where('id', $institutionId)
                        ->lockForUpdate()
                        ->first(['id', 'is_active']);

                    if ($institution === null || ! (bool) $institution->is_active) {
                        return ['error' => 'La institucion no existe o esta inactiva.'];
                    }

                    if (! \Illuminate\Support\Facades\DB::table('users')->where('id', $userId)->exists()) {
                        return ['error' => 'El usuario no existe. Debe crearse antes por un procedimiento autorizado.'];
                    }

                    $hasActiveAdmin = \Illuminate\Support\Facades\DB::table('institution_user')
                        ->where('institution_id', $institutionId)
                        ->where('is_active', true)
                        ->where('role', 'institution_admin')
                        ->exists();

                    if ($hasActiveAdmin) {
                        return ['error' => 'Ya existe un administrador institucional activo. Usa la API autorizada de membresias.'];
                    }

                    $membership = \Illuminate\Support\Facades\DB::table('institution_user')
                        ->where('institution_id', $institutionId)
                        ->where('user_id', $userId)
                        ->lockForUpdate()
                        ->first(['id']);

                    $now = now();

                    if ($membership !== null) {
                        \Illuminate\Support\Facades\DB::table('institution_user')
                            ->where('id', $membership->id)
                            ->update([
                                'role' => 'institution_admin',
                                'is_active' => true,
                                'updated_at' => $now,
                            ]);
                        return ['membership_id' => (int) $membership->id, 'action' => 'reactivada/promovida'];
                    }

                    $membershipId = \Illuminate\Support\Facades\DB::table('institution_user')->insertGetId([
                        'institution_id' => $institutionId,
                        'user_id' => $userId,
                        'person_id' => null,
                        'role' => 'institution_admin',
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    return ['membership_id' => (int) $membershipId, 'action' => 'creada'];
                },
                3
            );
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('Fallo al inicializar administrador institucional', [
                'institution_id' => $institutionId,
                'user_id' => $userId,
                'exception' => $exception,
            ]);
            $this->error('No se pudo completar la operacion. Revisa los logs del servidor.');
            return 1;
        }

        if (isset($result['error'])) {
            \Illuminate\Support\Facades\Log::warning('Bootstrap institucional rechazado', [
                'institution_id' => $institutionId,
                'user_id' => $userId,
                'reason' => $result['error'],
            ]);
            $this->error($result['error']);
            return 1;
        }

        \Illuminate\Support\Facades\Log::notice('Bootstrap de administrador institucional realizado', [
            'institution_id' => $institutionId,
            'user_id' => $userId,
            'membership_id' => $result['membership_id'],
            'action' => $result['action'],
        ]);

        $this->info('OK: membresia '.$result['action'].' con rol institution_admin. ID: '.$result['membership_id']);
        return 0;
    }
)->purpose('Asigna el primer administrador institucional desde una consola de confianza');
// END INSTITUTION_BOOTSTRAP_ADMIN_3B