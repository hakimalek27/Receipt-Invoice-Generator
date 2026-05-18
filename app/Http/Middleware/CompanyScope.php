<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Super admin: pick active company from session for this request.
        // First visit (no session yet) auto-selects the first active company so
        // existing code paths that read $user->company_id keep working.
        if ($user->isSuperAdmin()) {
            $activeId = $request->session()->get('active_company_id');
            if (! $activeId) {
                $first = Company::where('is_active', true)->orderBy('id')->first();
                if ($first) {
                    $activeId = $first->id;
                    $request->session()->put('active_company_id', $activeId);
                }
            }
            if ($activeId) {
                // In-memory override only — never persisted to the users row.
                $user->company_id = $activeId;
                $user->setRelation('company', Company::find($activeId));
            }

            return $next($request);
        }

        // Regular users must belong to a company.
        if (! $user->company_id) {
            abort(403, 'No company assigned. Contact administrator.');
        }

        if ($user->company && ! $user->company->is_active) {
            abort(403, 'Company is inactive.');
        }

        return $next($request);
    }
}
