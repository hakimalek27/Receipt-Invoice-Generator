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

            // Super admin has no override yet (e.g. first request after login,
            // or session was cleared). Auto-pick the first active company so
            // every controller that scopes by company keeps working, and
            // persist to the session so the UI switcher stays in sync.
            $firstId = \App\Models\Company::where('is_active', true)
                ->orderBy('id')
                ->value('id');

            if ($firstId && $session) {
                $session->put('active_company_id', (int) $firstId);
            }

            return $firstId ? (int) $firstId : $user->company_id;
        }

        return $user->company_id;
    }
}
