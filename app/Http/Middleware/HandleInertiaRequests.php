<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeCompanyId = $user ? \App\Services\ActiveCompanyResolver::resolve($user, $request) : null;
        $activeCompany = $activeCompanyId
            ? \App\Models\Company::find($activeCompanyId)?->only('id', 'code', 'name')
            : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'active_company' => $activeCompany,
                'available_companies' => $user?->isSuperAdmin()
                    ? \App\Models\Company::where('is_active', true)
                        ->orderBy('name')
                        ->get(['id', 'code', 'name'])
                    : ($user && $user->company_id
                        ? [\App\Models\Company::find($user->company_id)?->only('id', 'code', 'name')]
                        : []),
            ],
        ];
    }
}
