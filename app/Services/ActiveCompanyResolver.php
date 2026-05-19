<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class ActiveCompanyResolver
{
    /**
     * Resolve the effective company ID for the current request.
     *
     * For super admins, this honours a session-stored override; otherwise
     * it falls back to the user's own company_id. This is the single
     * source of truth that all controllers should use when scoping data.
     */
    public static function resolve(?User $user, ?Request $request = null): ?int
    {
        if (! $user) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            $session = $request?->session();
            $override = $session?->get('active_company_id');
            if ($override) {
                return (int) $override;
            }
        }

        return $user->company_id;
    }
}
