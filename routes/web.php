<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route(request()->user() ? 'dashboard' : 'login');
});

Route::get('/dashboard', function () {
    $user = request()->user();

    return Inertia::render('Dashboard', [
        'currentCompany' => $user->company,
        'stats' => [
            'documents' => \App\Models\Document::forCompany($user->company_id)->count(),
            'drafts' => \App\Models\Document::forCompany($user->company_id)->draft()->count(),
            'issued' => \App\Models\Document::forCompany($user->company_id)->issued()->count(),
            'customers' => \App\Models\Customer::forCompany($user->company_id)->count(),
        ],
        'recentDocuments' => \App\Models\Document::with('customer')
            ->forCompany($user->company_id)
            ->latest()
            ->limit(8)
            ->get(),
    ]);
})->middleware(['auth', 'verified', 'company'])->name('dashboard');

Route::middleware(['auth', 'company'])->group(function () {
    Route::get('/documents', function () {
        return Inertia::render('Documents/Index', [
            'documents' => \App\Models\Document::with('customer')
                ->forCompany(request()->user()->company_id)
                ->latest()
                ->paginate(20),
        ]);
    })->name('documents.index');

    Route::get('/documents/create', fn () => Inertia::render('Documents/Edit', [
        'document' => null,
    ]))->name('documents.create');

    Route::get('/master-data', fn () => Inertia::render('MasterData/Index'))->name('master-data.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
