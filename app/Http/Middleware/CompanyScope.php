<?php

namespace App\Http\Middleware;

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

        // Super admins can access all companies
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Users must belong to a company
        if (! $user->company_id) {
            abort(403, 'No company assigned. Contact administrator.');
        }

        // Verify the company is active
        if ($user->company && ! $user->company->is_active) {
            abort(403, 'Company is inactive.');
        }

        return $next($request);
    }
}
