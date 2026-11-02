<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Auditoria de cambios de membresia. Se invoca solo dentro de la transaccion
 * que realiza la operacion; un fallo de escritura revierte tambien el cambio.
 */
final class InstitutionMembershipAudit
{
    public static function record(
        int $institutionId,
        int $membershipId,
        int $targetUserId,
        ?int $actorUserId,
        string $action,
        ?object $before,
        array $after,
        string $actorType = 'user'
    ): void {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('La auditoria debe registrarse dentro de una transaccion.');
        }

        DB::table('institution_membership_audits')->insert([
            'institution_id' => $institutionId,
            'membership_id' => $membershipId,
            'target_user_id' => $targetUserId,
            'actor_user_id' => $actorUserId,
            'actor_type' => $actorType,
            'action' => $action,
            'old_role' => $before?->role,
            'new_role' => $after['role'],
            'old_is_active' => $before === null ? null : (bool) $before->is_active,
            'new_is_active' => (bool) $after['is_active'],
            'old_person_id' => $before?->person_id,
            'new_person_id' => $after['person_id'] ?? null,
            'created_at' => now(),
        ]);
    }
}
