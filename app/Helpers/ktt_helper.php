<?php
/**
 * KTT Helper
 *
 * Provides centralized, database-driven functions for resolving KTT identity.
 * Replaces all hardcoded user ID 7 (KTT MSM) / user ID 8 (KTT TTN) references
 * throughout the codebase.
 *
 * Also supports the KTT Delegation system: if a KTT has delegated their
 * approval authority to another user, that user is treated as the active KTT
 * for the duration of the delegation.
 */

if (!function_exists('getKttInfo')) {

    /**
     * Resolve the KTT context for the currently logged-in user.
     *
     * Returns an array with:
     *  - ktt_type        (string|null)  'msm', 'ttn', or null if not a KTT actor
     *  - is_delegated    (bool)         true if acting via a delegation
     *  - delegator_id    (int|null)     User ID of the original KTT (if delegated)
     *  - delegator_name  (string|null)  Full name of the original KTT (if delegated)
     *  - delegation_end  (string|null)  end_date of the delegation (if delegated)
     *
     * @param int      $user_id  Current user's ID ($_SESSION['user_id'])
     * @param Database $db       Database instance
     * @return array
     */
    function getKttInfo(int $user_id, $db): array {
        $result = [
            'ktt_type'       => null,
            'is_delegated'   => false,
            'delegator_id'   => null,
            'delegator_name' => null,
            'delegation_end' => null,
        ];

        // 1. Check if this user is an active delegatee
        $delegation = $db->query("
            SELECT kd.ktt_type, kd.ktt_user_id, kd.end_date,
                   u.full_name AS delegator_name
            FROM   ktt_delegations kd
            JOIN   users u ON kd.ktt_user_id = u.id
            WHERE  kd.delegate_user_id = ?
              AND  kd.status = 'active'
              AND  kd.start_date <= CURDATE()
              AND  kd.end_date   >= CURDATE()
            ORDER BY kd.start_date DESC
            LIMIT 1
        ", [$user_id]);

        if ($delegation && $row = $delegation->fetch_assoc()) {
            $result['ktt_type']       = $row['ktt_type'];
            $result['is_delegated']   = true;
            $result['delegator_id']   = (int)$row['ktt_user_id'];
            $result['delegator_name'] = $row['delegator_name'];
            $result['delegation_end'] = $row['end_date'];
            return $result;
        }

        // 2. Check if this user is a native KTT
        $ktt = $db->query("
            SELECT ktt_type FROM users
            WHERE id = ? AND ktt_type IS NOT NULL
        ", [$user_id]);

        if ($ktt && $row = $ktt->fetch_assoc()) {
            $result['ktt_type'] = $row['ktt_type'];
        }

        return $result;
    }
}

if (!function_exists('getKttUserIdByType')) {
    /**
     * Get the native (original) user ID of a KTT by type.
     * Used when inserting records that reference the KTT's canonical user ID.
     *
     * @param string   $ktt_type  'msm' or 'ttn'
     * @param Database $db
     * @return int|null
     */
    function getKttUserIdByType(string $ktt_type, $db): ?int {
        $row = $db->query("
            SELECT id FROM users
            WHERE ktt_type = ? AND role = 'ktt'
            LIMIT 1
        ", [$ktt_type])->fetch_assoc();

        return $row ? (int)$row['id'] : null;
    }
}

if (!function_exists('isActiveDelegatee')) {
    /**
     * Check if a given user is currently an active KTT delegatee.
     *
     * @param int      $user_id
     * @param Database $db
     * @return bool
     */
    function isActiveDelegatee(int $user_id, $db): bool {
        $row = $db->query("
            SELECT id FROM ktt_delegations
            WHERE delegate_user_id = ?
              AND status = 'active'
              AND start_date <= CURDATE()
              AND end_date   >= CURDATE()
            LIMIT 1
        ", [$user_id])->fetch_assoc();

        return (bool)$row;
    }
}

if (!function_exists('getKttTypeLabel')) {
    /**
     * Get a human-readable label for a KTT type.
     *
     * @param string|null $ktt_type  'msm' or 'ttn'
     * @return string
     */
    function getKttTypeLabel(?string $ktt_type): string {
        return match ($ktt_type) {
            'msm'  => 'KTT MSM',
            'ttn'  => 'KTT TTN',
            default => 'KTT',
        };
    }
}

if (!function_exists('getActiveKttDelegation')) {
    /**
     * Get the current active delegation for a given KTT type, if any.
     * Useful for the Admin/Superadmin to check who is currently delegated.
     *
     * @param string   $ktt_type 'msm' or 'ttn'
     * @param Database $db
     * @return array|null
     */
    function getActiveKttDelegation(string $ktt_type, $db): ?array {
        $row = $db->query("
            SELECT kd.*, 
                   ktt.full_name AS ktt_user_name,
                   del.full_name AS delegate_user_name,
                   del.role      AS delegate_role
            FROM   ktt_delegations kd
            JOIN   users ktt ON kd.ktt_user_id      = ktt.id
            JOIN   users del ON kd.delegate_user_id = del.id
            WHERE  kd.ktt_type  = ?
              AND  kd.status    = 'active'
              AND  kd.start_date <= CURDATE()
              AND  kd.end_date   >= CURDATE()
            ORDER BY kd.start_date DESC
            LIMIT 1
        ", [$ktt_type])->fetch_assoc();

        return $row ?: null;
    }
}
