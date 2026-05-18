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

Route::get('/', function () {
    return redirect()->route(request()->user() ? 'dashboard' : 'login');
});

Route::get('/dashboard', function () {
    $user = request()->user();

    return Inertia::render('Dashboard', [
        'currentCompany' => $user->company,
        'stats' => [
            'documents' => Document::forCompany($user->company_id)->count(),
            'drafts' => Document::forCompany($user->company_id)->draft()->count(),
            'issued' => Document::forCompany($user->company_id)->issued()->count(),
            'customers' => Customer::forCompany($user->company_id)->count(),
        ],
        'recentDocuments' => Document::with('customer')
            ->forCompany($user->company_id)
            ->latest()
            ->limit(8)
            ->get(),
    ]);
})->middleware(['auth', 'verified', 'company'])->name('dashboard');

Route::middleware(['auth', 'company'])->group(function () {
    Route::get('/documents', function () {
        $user = request()->user();
        $query = Document::with('customer')->forCompany($user->company_id)->latest();

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

        return Inertia::render('Documents/Index', [
            'documents' => $query->paginate(20)->withQueryString(),
            'filters' => request()->only(['type', 'status', 'search']),
            'documentTypes' => document_type_options(),
        ]);
    })->name('documents.index');

    Route::get('/documents/create', fn () => Inertia::render('Documents/Edit', [
        'document' => null,
        'company' => \App\Models\Company::find(request()->user()->company_id)?->only('id', 'code', 'name'),
        'customers' => Customer::forCompany(request()->user()->company_id)->active()->orderBy('name')->get(),
        'products' => Product::forCompany(request()->user()->company_id)->active()->orderBy('name')->get(),
        'documentTypes' => document_type_options(),
    ]))->name('documents.create');

    Route::get('/documents/{document}', function (Document $document) {
        abort_unless(
            $document->company_id === request()->user()->company_id || request()->user()->isSuperAdmin(),
            403
        );

        return Inertia::render('Documents/Edit', [
            'document' => $document->load(
                'items', 'customer', 'attachments', 'pdfRenders',
                'convertedFrom:id,document_type,official_number,status',
                'convertedTo:id,converted_from_id,document_type,official_number,status'
            ),
            'company' => \App\Models\Company::find($document->company_id)?->only('id', 'code', 'name'),
            'customers' => Customer::forCompany(request()->user()->company_id)->active()->orderBy('name')->get(),
            'products' => Product::forCompany(request()->user()->company_id)->active()->orderBy('name')->get(),
            'documentTypes' => document_type_options(),
        ]);
    })->name('documents.edit');

    Route::get('/payments', function () {
        $receivableDocuments = Document::with('customer')
            ->withSum('paymentAllocations as allocated_amount', 'amount')
            ->forCompany(request()->user()->company_id)
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
                ->forCompany(request()->user()->company_id)
                ->latest()
                ->paginate(30),
            'receivableDocuments' => $receivableDocuments,
        ]);
    })->name('payments.index');

    Route::get('/master-data', fn () => Inertia::render('MasterData/Index', [
        'company' => request()->user()->company?->append(['logo_url', 'stamp_url', 'signature_url']),
        'bankAccounts' => request()->user()->company?->bankAccounts()->orderBy('sort_order')->get() ?? [],
        'customers' => Customer::forCompany(request()->user()->company_id)->orderBy('name')->paginate(50),
        'products' => Product::forCompany(request()->user()->company_id)->orderBy('name')->paginate(50),
        'templates' => DocumentTemplate::forCompany(request()->user()->company_id)->orderBy('document_type')->get(),
        'numberingPolicies' => NumberingPolicy::forCompany(request()->user()->company_id)->orderBy('document_type')->get(),
        'documentTypes' => document_type_options(),
    ]))->name('master-data.index');

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
