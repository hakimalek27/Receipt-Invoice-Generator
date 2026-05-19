<?php

use App\Http\Controllers\ProfileController;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\NumberingPolicy;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

if (! function_exists('effective_company_id')) {
    function effective_company_id(): ?int
    {
        return \App\Services\ActiveCompanyResolver::resolve(request()->user(), request());
    }
}

Route::get('/', function () {
    return redirect()->route(request()->user() ? 'dashboard' : 'login');
});

Route::get('/dashboard', function () {
    $user = request()->user();
    $companyId = effective_company_id();
    $currentCompany = $companyId ? \App\Models\Company::find($companyId) : $user->company;

    $payload = [
        'currentCompany' => $currentCompany,
        'onboarding' => $currentCompany?->onboardingChecklist() ?? ['complete' => true, 'missing' => [], 'first_tab' => null],
        'stats' => [
            'documents' => Document::forCompany($companyId)->count(),
            'drafts' => Document::forCompany($companyId)->draft()->count(),
            'issued' => Document::forCompany($companyId)->issued()->count(),
            'customers' => Customer::forCompany($companyId)->count(),
        ],
        'recentDocuments' => Document::with('customer')
            ->forCompany($companyId)
            ->latest()
            ->limit(8)
            ->get(),
    ];

    if ($user->isSuperAdmin()) {
        $payload['allCompanyStats'] = \App\Models\Company::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($company) => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'documents' => Document::forCompany($company->id)->count(),
                'drafts' => Document::forCompany($company->id)->draft()->count(),
                'issued' => Document::forCompany($company->id)->issued()->count(),
            ]);
    }

    return Inertia::render('Dashboard', $payload);
})->middleware(['auth', 'verified', 'company'])->name('dashboard');

Route::middleware(['auth'])->post('/active-company', function () {
    $user = request()->user();
    if (! $user?->isSuperAdmin()) {
        abort(403, 'Only super admins may switch active company.');
    }
    $data = request()->validate([
        'company_id' => 'nullable|integer|exists:companies,id',
    ]);
    if (empty($data['company_id'])) {
        session()->forget('active_company_id');
    } else {
        session()->put('active_company_id', (int) $data['company_id']);
    }
    return back();
})->name('active-company.switch');

Route::middleware(['auth', 'company'])->group(function () {
    Route::get('/documents', function () {
        $user = request()->user();
        $query = Document::with([
            'customer',
            'convertedFrom:id,document_type,official_number,status',
            'convertedTo:id,converted_from_id,document_type,official_number',
        ])->forCompany(effective_company_id())->latest();

        foreach (['type' => 'document_type', 'status' => 'status'] as $param => $column) {
            if ($value = request()->query($param)) {
                $query->where($column, $value);
            }
        }
        if ($search = trim((string) request()->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('official_number', 'like', "%{$search}%")
                    ->orWhere('document_type', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
            });
        }
        if ($dateFrom = request()->query('date_from')) {
            $query->whereDate('document_date', '>=', $dateFrom);
        }
        if ($dateTo = request()->query('date_to')) {
            $query->whereDate('document_date', '<=', $dateTo);
        }

        return Inertia::render('Documents/Index', [
            'documents' => $query->paginate(20)->withQueryString(),
            'filters' => request()->only(['type', 'status', 'search', 'date_from', 'date_to']),
            'documentTypes' => document_type_options(),
        ]);
    })->name('documents.index');

    Route::get('/documents/create', fn () => Inertia::render('Documents/Edit', [
        'document' => null,
        'company' => \App\Models\Company::find(effective_company_id())?->only('id', 'code', 'name'),
        'customers' => Customer::forCompany(effective_company_id())->active()->orderBy('name')->get(),
        'products' => Product::forCompany(effective_company_id())->active()->orderBy('name')->get(),
        'documentTypes' => document_type_options(),
    ]))->name('documents.create');

    Route::get('/documents/{document}', function (Document $document) {
        abort_unless(
            $document->company_id === effective_company_id() || request()->user()->isSuperAdmin(),
            403
        );

        return Inertia::render('Documents/Edit', [
            'document' => $document->load(
                'items', 'customer', 'attachments', 'pdfRenders',
                'convertedFrom:id,document_type,official_number,status',
                'convertedTo:id,converted_from_id,document_type,official_number,status'
            ),
            'company' => \App\Models\Company::find($document->company_id)?->only('id', 'code', 'name'),
            'customers' => Customer::forCompany(effective_company_id())->active()->orderBy('name')->get(),
            'products' => Product::forCompany(effective_company_id())->active()->orderBy('name')->get(),
            'documentTypes' => document_type_options(),
            'statusHistory' => $document->statusHistory()
                ->with('changedBy:id,name')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'from_status' => $h->from_status,
                    'to_status' => $h->to_status,
                    'reason' => $h->reason,
                    'changed_by' => $h->changedBy?->name,
                    'created_at' => $h->created_at?->toIso8601String(),
                ]),
        ]);
    })->name('documents.edit');

    Route::get('/payments', function () {
        $receivableDocuments = Document::with('customer')
            ->withSum('paymentAllocations as allocated_amount', 'amount')
            ->forCompany(effective_company_id())
            ->issued()
            ->whereIn('document_type', ['invoice', 'cash_bill', 'debit_note'])
            ->latest()
            ->limit(200)
            ->get()
            ->map(function (Document $document) {
                $document->outstanding_amount = round((float) $document->grand_total - (float) $document->allocated_amount, 2);

                return $document;
            })
            ->filter(fn (Document $document) => $document->outstanding_amount > 0)
            ->values();

        return Inertia::render('Payments/Index', [
            'payments' => Payment::with('allocations.document', 'receiptDocument')
                ->forCompany(effective_company_id())
                ->latest()
                ->paginate(30),
            'receivableDocuments' => $receivableDocuments,
        ]);
    })->name('payments.index');

    Route::get('/master-data', function () {
        $companyId = effective_company_id();
        $company = $companyId ? \App\Models\Company::find($companyId) : null;

        return Inertia::render('MasterData/Index', [
            'company' => $company?->append(['logo_url', 'stamp_url', 'signature_url']),
            'bankAccounts' => $company?->bankAccounts()->orderBy('sort_order')->get() ?? [],
            'customers' => Customer::forCompany($companyId)->orderBy('name')->paginate(50),
            'products' => Product::forCompany($companyId)->orderBy('name')->paginate(50),
            'templates' => DocumentTemplate::forCompany($companyId)->orderBy('document_type')->get(),
            'numberingPolicies' => NumberingPolicy::forCompany($companyId)->orderBy('document_type')->get(),
            'documentTypes' => document_type_options(),
        ]);
    })->name('master-data.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

if (! function_exists('document_type_options')) {
    function document_type_options(): array
    {
        return [
            'invoice',
            'quotation',
            'official_receipt',
            'delivery_order',
            'cash_bill',
            'credit_note',
            'debit_note',
            'purchase_order',
            'payment_voucher',
            'proforma_invoice',
        ];
    }
}
