<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchCompanyController extends Controller
{
    /**
     * Set the active company id in the session for super-admin users.
     * Pass company_id = 0 (or null) to clear and operate in "all companies" mode.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403);

        $data = $request->validate([
            'company_id' => 'nullable|integer',
        ]);

        $id = $data['company_id'] ?? null;
        if ($id && ! Company::whereKey($id)->exists()) {
            abort(422, 'Invalid company');
        }

        if ($id) {
            $request->session()->put('active_company_id', (int) $id);
        } else {
            $request->session()->forget('active_company_id');
        }

        return back();
    }
}
