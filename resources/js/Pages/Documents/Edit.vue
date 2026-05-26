<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { apiFetch, money, today } from '@/lib/api';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    document: Object,
    company: Object,
    customers: Array,
    products: Array,
    documentTypes: Array,
    derivationTargetsMap: { type: Object, default: () => ({}) },
    statusHistory: { type: Array, default: () => [] },
});

const page = usePage();
const isAdmin = computed(() => {
    const role = page.props?.auth?.user?.role;
    return role === 'admin' || role === 'super_admin';
});
const isPgg = computed(() => props.company?.code === 'PGG');

const initialItems = props.document?.items?.length
    ? props.document.items
    : [{ description: '', quantity: 1, uom: 'unit', unit_price: 0, discount: 0, tax_type: '', tax_rate: 0, tax_amount: 0 }];

// Pretty labels for the derive buttons. Falls back to the raw type if missing.
const DOC_TYPE_LABELS = {
    quotation: 'Quotation',
    proforma_invoice: 'Proforma Invoice',
    invoice: 'Invoice',
    delivery_order: 'Delivery Order',
    official_receipt: 'Official Receipt',
    cash_bill: 'Cash Bill',
    credit_note: 'Credit Note',
    debit_note: 'Debit Note',
    purchase_order: 'Purchase Order',
    payment_voucher: 'Payment Voucher',
};
function docTypeLabel(t) {
    return DOC_TYPE_LABELS[t] || t.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

const form = reactive({
    id: props.document?.id ?? null,
    status: props.document?.status ?? 'draft',
    official_number: props.document?.official_number ?? null,
    document_type: props.document?.document_type ?? 'invoice',
    customer_id: props.document?.customer_id ?? '',
    customer_name: props.document?.customer?.name ?? '',
    customer_attention_to: props.document?.customer?.attention_to ?? '',
    customer_phone: props.document?.customer?.phone ?? '',
    customer_email: props.document?.customer?.email ?? '',
    customer_address: props.document?.customer?.address ?? '',
    document_date: props.document?.document_date?.slice(0, 10) ?? today(),
    due_date: props.document?.due_date?.slice(0, 10) ?? '',
    currency: props.document?.currency ?? 'MYR',
    fx_rate: props.document?.fx_rate ?? '',
    terms: props.document?.terms ?? '',
    notes: props.document?.notes ?? '',
    product_line: props.document?.product_line ?? '',
    include_arabic_salutation: props.document
        ? Boolean(props.document.include_arabic_salutation)
        : (props.company?.code === 'PGG'),
    // null = inherit company default; true/false = explicit per-document override.
    show_computer_generated_footer: props.document?.show_computer_generated_footer ?? null,
    show_amount_in_words: Boolean(props.document?.show_amount_in_words),
    amount_in_words_locale: props.document?.amount_in_words_locale ?? 'ms_MY',
    amount_in_words_currency: props.document?.amount_in_words_currency ?? 'MYR',
    draft_hash: props.document?.draft_hash ?? null,
    grand_total: Number(props.document?.grand_total ?? 0),
    attachments: props.document?.attachments ?? [],
    pdf_renders: props.document?.pdf_renders ?? [],
    items: initialItems.map((item) => ({
        product_id: item.product_id ?? null,
        product_name: item.product_id
            ? (props.products?.find((p) => p.id === item.product_id)?.name ?? '')
            : '',
        description: item.description ?? '',
        section_header: item.section_header ?? '',
        image_url: item.image_url ?? '',
        quantity: Number(item.quantity ?? 1),
        uom: item.uom ?? 'unit',
        unit_price: Number(item.unit_price ?? 0),
        cost_unit: item.cost_unit != null ? Number(item.cost_unit) : null,
        discount: Number(item.discount ?? 0),
        tax_type: item.tax_type ?? '',
        tax_rate: Number(item.tax_rate ?? 0),
        tax_amount: Number(item.tax_amount ?? 0),
        classification_code: item.classification_code ?? '',
        tax_exemption_reason: item.tax_exemption_reason ?? '',
    })),
});

const totalMargin = computed(() => form.items.reduce((sum, item) => {
    if (item.cost_unit == null) return sum;
    return sum + ((Number(item.unit_price || 0) - Number(item.cost_unit || 0)) * Number(item.quantity || 0));
}, 0));
const marginRate = computed(() => {
    const revenue = form.items.reduce((sum, item) => {
        if (item.cost_unit == null) return sum;
        return sum + (Number(item.unit_price || 0) * Number(item.quantity || 0));
    }, 0);
    return revenue > 0 ? (totalMargin.value / revenue) * 100 : 0;
});

const busy = ref(false);
const message = ref('');
const error = ref('');
const issueOpen = ref(false);
const confirmedTotal = ref('');
const lastPreviewHash = ref(null);
const draggedAttachmentIndex = ref(null);
const artworkFiles = ref([]);
const artworkCaption = ref('');
// "Generate from this" buttons read from the backend-provided map so the
// UI list always matches DocumentWorkflowService::DERIVATION_TARGETS.
const availableDeriveTargets = computed(() => props.derivationTargetsMap?.[form.document_type] || []);
const voidReason = ref('');

const isDraft = computed(() => form.status === 'draft');
// Allow edits on drafts and issued docs so typos / amount fixes can be
// corrected after the fact. Only void / cancelled stay locked since those
// are explicit dead-letter lifecycle states. (The legacy 'converted'
// status has been removed in favour of the derive model.)
const canEdit = computed(() => ['draft', 'issued'].includes(form.status));

// Thermal 60mm is only meaningful for short transactional docs (cash
// bill, official receipt, payment voucher). Hide the 60mm preview /
// download buttons for invoice / quote / DO / PO / CN / DN / proforma —
// those need A4 with full bill-to + signature + bank box.
const THERMAL_ELIGIBLE_DOC_TYPES = ['cash_bill', 'official_receipt', 'payment_voucher'];
const isThermalEligible = computed(() => THERMAL_ELIGIBLE_DOC_TYPES.includes(form.document_type));

// Predefined unit list for the per-item UOM dropdown (mix EN + MS, ordered
// by frequency in invoicing).
const UOM_OPTIONS = [
    'unit', 'pcs', 'pc', 'set', 'pair', 'pasang', 'dozen',
    'box', 'kotak', 'carton', 'ctn', 'karton',
    'pack', 'pek', 'pkt', 'bundle', 'roll', 'gulung',
    'sheet', 'helai', 'keping', 'biji', 'lot', 'l/s',
    'm', 'cm', 'kg', 'g', 'liter', 'ml',
    'hour', 'jam', 'day', 'hari', 'month', 'bulan', 'year', 'tahun',
];
const companyFooterDefaultLabel = computed(() => {
    const value = props.company?.settings?.show_computer_generated_footer ?? true;
    return value ? 'ON' : 'OFF';
});
const canPrice = computed(() => form.document_type !== 'delivery_order');
const subtotal = computed(() => form.items.reduce((sum, item) => sum + (Number(item.quantity || 0) * Number(item.unit_price || 0)), 0));
const discountTotal = computed(() => form.items.reduce((sum, item) => sum + Number(item.discount || 0), 0));
const taxTotal = computed(() => form.items.reduce((sum, item) => sum + Number(item.tax_amount || 0), 0));
const grandTotal = computed(() => subtotal.value - discountTotal.value + taxTotal.value);
const previewIsFresh = computed(() => form.draft_hash && lastPreviewHash.value === form.draft_hash);

function applyDocument(document) {
    form.id = document.id;
    form.status = document.status;
    form.official_number = document.official_number;
    form.draft_hash = document.draft_hash;
    form.grand_total = Number(document.grand_total ?? grandTotal.value);
    form.attachments = document.attachments ?? form.attachments;
    form.pdf_renders = document.pdf_renders ?? form.pdf_renders;
    // Reflect server-truth for the tri-state override so the dropdown
    // matches the actual saved value (null = inherit).
    form.show_computer_generated_footer = document.show_computer_generated_footer ?? null;
}

function addItem() {
    form.items.push({
        product_id: null, product_name: '',
        description: '', section_header: '', image_url: '',
        quantity: 1, uom: 'unit', unit_price: 0, cost_unit: null,
        discount: 0, tax_type: '', tax_rate: 0, tax_amount: 0,
        classification_code: '', tax_exemption_reason: '',
    });
}

function removeItem(index) {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
}

function customerAutofill() {
    const name = form.customer_name?.trim();
    if (!name) {
        form.customer_id = '';
        // Leave the other detail fields untouched so a typed-but-unsaved entry isn't wiped mid-edit.
        return;
    }
    const match = props.customers?.find((c) => c.name === name);
    if (match) {
        form.customer_id = match.id;
        form.customer_attention_to = match.attention_to ?? '';
        form.customer_phone = match.phone ?? '';
        form.customer_email = match.email ?? '';
        form.customer_address = match.address ?? '';
    } else {
        form.customer_id = '';
    }
}

function productAutofill(index) {
    const name = form.items[index].product_name?.trim();
    if (!name) {
        form.items[index].product_id = null;
        return;
    }
    const product = props.products?.find((p) => p.name === name);
    if (!product) {
        form.items[index].product_id = null;
        return;
    }
    Object.assign(form.items[index], {
        product_id: product.id,
        description: product.description || product.name,
        uom: product.uom || 'unit',
        unit_price: Number(product.default_price || 0),
        tax_type: product.tax_type || '',
        tax_rate: Number(product.tax_rate || 0),
        classification_code: product.classification_code || '',
    });
}

function payload() {
    const trimmedCustomerName = form.customer_name?.trim() || '';
    const customerMatch = props.customers?.find((c) => c.name === trimmedCustomerName);
    return {
        document_type: form.document_type,
        customer_id: customerMatch?.id ?? null,
        // Backend find-or-creates a Customer record when no id matches but a name is typed,
        // and updates the customer with the detail fields below (2-way sync with Master Data).
        customer_name: customerMatch ? null : (trimmedCustomerName || null),
        customer_attention_to: form.customer_attention_to?.trim() || null,
        customer_phone: form.customer_phone?.trim() || null,
        customer_email: form.customer_email?.trim() || null,
        customer_address: form.customer_address?.trim() || null,
        document_date: form.document_date,
        due_date: form.due_date || null,
        currency: form.currency,
        fx_rate: form.fx_rate || null,
        terms: form.terms || null,
        notes: form.notes || null,
        product_line: form.product_line || null,
        include_arabic_salutation: form.include_arabic_salutation,
        show_computer_generated_footer: form.show_computer_generated_footer,
        show_amount_in_words: form.show_amount_in_words,
        amount_in_words_locale: form.amount_in_words_locale,
        amount_in_words_currency: form.amount_in_words_currency,
        items: form.items
            .filter((item) => item.description.trim() !== '')
            .map((item, index) => {
                const productMatch = props.products?.find((p) => p.name === item.product_name?.trim());
                return {
                    ...item,
                    product_id: productMatch?.id ?? null,
                    section_header: item.section_header?.trim() || null,
                    image_url: item.image_url?.trim() || null,
                    cost_unit: item.cost_unit === '' || item.cost_unit == null ? null : Number(item.cost_unit),
                    sort_order: index,
                };
            }),
    };
}

async function saveDraft() {
    busy.value = true;
    error.value = '';
    message.value = '';
    try {
        const document = form.id
            ? await apiFetch(`/api/documents/${form.id}`, { method: 'PATCH', body: JSON.stringify(payload()) })
            : await apiFetch('/api/documents', { method: 'POST', body: JSON.stringify(payload()) });
        applyDocument(document);
        lastPreviewHash.value = null;
        message.value = 'Draft saved.';
        if (!props.document && form.id) {
            window.history.replaceState({}, '', `/documents/${form.id}`);
        }
    } catch (exception) {
        error.value = exception.message;
    } finally {
        busy.value = false;
    }
}

const previewModal = ref({ open: false, paper: 'a4', url: '', maximized: false });

async function previewPdf(paper = 'a4') {
    if (!form.id) {
        await saveDraft();
    }
    if (!form.id) return;
    lastPreviewHash.value = form.draft_hash;
    // Cache-buster ensures the iframe re-fetches after edits within the same session.
    const cacheBuster = Date.now();
    previewModal.value = {
        open: true,
        paper,
        url: `/api/documents/${form.id}/pdf?paper=${paper}&_=${cacheBuster}`,
        maximized: false,
    };
}

function closePreview() {
    previewModal.value = { open: false, paper: 'a4', url: '', maximized: false };
}

function toggleMaximize() {
    previewModal.value.maximized = !previewModal.value.maximized;
}

function downloadPdf(paper = 'a4') {
    if (!form.id) return;
    // Anchor with download attr triggers save-as without leaving the page.
    const link = document.createElement('a');
    link.href = `/api/documents/${form.id}/pdf?paper=${paper}&download=1`;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

const UPLOAD_MAX_BYTES = 10 * 1024 * 1024; // Aligned with PHP-FPM + nginx (10 MB)

async function uploadArtwork() {
    if (!form.id) {
        await saveDraft();
    }
    if (!form.id || artworkFiles.value.length === 0) return;

    const tooBig = artworkFiles.value.filter((f) => f.size > UPLOAD_MAX_BYTES);
    if (tooBig.length > 0) {
        const names = tooBig.map((f) => `${f.name} (${(f.size / 1024 / 1024).toFixed(2)} MB)`).join(', ');
        error.value = `These files exceed the 10 MB server upload limit: ${names}. Please compress or split the files.`;
        return;
    }

    busy.value = true;
    error.value = '';
    const captionBase = artworkCaption.value.trim();
    let uploaded = 0;
    let failed = 0;
    try {
        for (let i = 0; i < artworkFiles.value.length; i++) {
            const file = artworkFiles.value[i];
            const body = new FormData();
            body.append('file', file);
            const cap = captionBase
                ? (artworkFiles.value.length > 1 ? `${captionBase} #${i + 1}` : captionBase)
                : file.name;
            body.append('caption', cap);
            body.append('include_in_pdf', '1');
            try {
                const attachment = await apiFetch(`/api/documents/${form.id}/attachments`, { method: 'POST', body });
                form.attachments.push(attachment);
                uploaded++;
            } catch (innerException) {
                failed++;
                error.value = `${file.name}: ${innerException.message}`;
            }
        }
        artworkFiles.value = [];
        artworkCaption.value = '';
        if (failed === 0) {
            message.value = `${uploaded} artwork uploaded.`;
        } else {
            message.value = `${uploaded} uploaded, ${failed} failed.`;
        }
    } finally {
        busy.value = false;
    }
}

async function removeAttachment(attachmentId) {
    if (!form.id || !attachmentId) return;
    if (!confirm('Delete this artwork attachment?')) return;
    busy.value = true;
    error.value = '';
    try {
        await apiFetch(`/api/documents/${form.id}/attachments/${attachmentId}`, { method: 'DELETE' });
        form.attachments = form.attachments.filter((a) => a.id !== attachmentId);
        message.value = 'Artwork removed.';
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}

async function moveAttachment(index, direction) {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= form.attachments.length) return;
    const reordered = [...form.attachments];
    [reordered[index], reordered[newIndex]] = [reordered[newIndex], reordered[index]];
    const payload = reordered.map((a, i) => ({ id: a.id, sort_order: i + 1 }));
    busy.value = true;
    error.value = '';
    try {
        await apiFetch(`/api/documents/${form.id}/attachments/reorder`, {
            method: 'PATCH',
            body: JSON.stringify({ attachments: payload }),
        });
        form.attachments = reordered.map((a, i) => ({ ...a, sort_order: i + 1 }));
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}

function onAttachmentDragStart(index) {
    draggedAttachmentIndex.value = index;
}

function onAttachmentDragEnd() {
    draggedAttachmentIndex.value = null;
}

async function onAttachmentDrop(targetIndex) {
    const fromIndex = draggedAttachmentIndex.value;
    draggedAttachmentIndex.value = null;
    if (fromIndex === null || fromIndex === targetIndex) return;
    const reordered = [...form.attachments];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(targetIndex, 0, moved);
    const payload = reordered.map((a, i) => ({ id: a.id, sort_order: i + 1 }));
    busy.value = true;
    error.value = '';
    try {
        await apiFetch(`/api/documents/${form.id}/attachments/reorder`, {
            method: 'PATCH',
            body: JSON.stringify({ attachments: payload }),
        });
        form.attachments = reordered.map((a, i) => ({ ...a, sort_order: i + 1 }));
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}

async function duplicateThisDocument() {
    if (!form.id) return;
    if (!confirm('Duplicate this document as a new draft?')) return;
    busy.value = true;
    try {
        const fresh = await apiFetch(`/api/documents/${form.id}/duplicate`, { method: 'POST' });
        window.location.href = `/documents/${fresh.id}`;
    } catch (e) {
        error.value = e.message;
        busy.value = false;
    }
}

// Used by the Danger Zone delete button: block when the doc is a source
// of derived children — admin must delete the chain bottom-up first.
const childrenCount = computed(() => props.document?.converted_to?.length ?? 0);

async function deleteThisDocument() {
    if (!form.id) return;
    const isDraft = form.status === 'draft';
    const numStr = form.official_number || `Draft #${form.id}`;
    const msg = isDraft
        ? `Permanently delete this draft? This cannot be undone.`
        : `Delete ${form.document_type} ${numStr}?\n\nThe number will be reusable for new documents. This cannot be undone.`;
    if (!window.confirm(msg)) return;
    busy.value = true;
    try {
        await apiFetch(`/api/documents/${form.id}`, { method: 'DELETE' });
        window.location.href = '/documents';
    } catch (e) {
        error.value = e.message;
        busy.value = false;
    }
}

function openIssue() {
    confirmedTotal.value = grandTotal.value.toFixed(2);
    issueOpen.value = true;
}

async function issueDocument() {
    if (!previewIsFresh.value) {
        error.value = 'Preview the current draft before issuing.';
        return;
    }
    busy.value = true;
    error.value = '';
    try {
        const issued = await apiFetch(`/api/documents/${form.id}/issue`, {
            method: 'POST',
            headers: { 'Idempotency-Key': crypto.randomUUID() },
            body: JSON.stringify({ draft_hash: form.draft_hash, confirmed_total: confirmedTotal.value }),
        });
        form.status = issued.status;
        form.official_number = issued.official_number;
        issueOpen.value = false;
        message.value = `Issued ${issued.official_number}.`;
    } catch (exception) {
        error.value = exception.message;
    } finally {
        busy.value = false;
    }
}

async function voidDocument() {
    if (!voidReason.value.trim()) {
        error.value = 'Void reason is required.';
        return;
    }
    busy.value = true;
    error.value = '';
    try {
        const document = await apiFetch(`/api/documents/${form.id}/void`, { method: 'POST', body: JSON.stringify({ reason: voidReason.value }) });
        form.status = document.status;
        message.value = 'Document voided.';
    } catch (exception) {
        error.value = exception.message;
    } finally {
        busy.value = false;
    }
}

function statusColor(status) {
    return {
        draft: 'text-amber-700',
        issued: 'text-emerald-700',
        converted: 'text-indigo-700',
        void: 'text-red-700',
        cancelled: 'text-gray-600',
    }[status] || 'text-gray-700';
}

async function deriveDocument(targetType) {
    busy.value = true;
    error.value = '';
    try {
        const document = await apiFetch(`/api/documents/${form.id}/convert`, {
            method: 'POST',
            body: JSON.stringify({ target_type: targetType }),
        });
        window.location.href = `/documents/${document.id}`;
    } catch (exception) {
        error.value = exception.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head :title="form.id ? `Document #${form.id}` : 'New Document'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-2xl font-semibold leading-tight tracking-tight text-gray-900">
                        {{ form.official_number || (form.id ? `Draft #${form.id}` : 'New Draft') }}
                    </h2>
                    <p class="mt-1 text-sm capitalize text-gray-500">{{ form.document_type.replace('_', ' ') }} · {{ form.status }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('documents.index')" class="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">Back</Link>
                    <button class="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50" :disabled="busy || !form.id" @click="previewPdf('a4')">Preview A4</button>
                    <button v-if="isThermalEligible" class="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50" :disabled="busy || !form.id" @click="previewPdf('60mm')">60mm</button>
                    <button v-if="form.id" class="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50" :disabled="busy" @click="duplicateThisDocument" title="Clone as new draft">Duplicate</button>
                    <button class="rounded-lg bg-gray-900 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800 disabled:opacity-50" :disabled="busy || !canEdit" @click="saveDraft">{{ isDraft ? 'Save Draft' : 'Save Changes' }}</button>
                    <button class="rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50" :disabled="busy || !isDraft || !form.id" @click="openIssue">Issue</button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:px-8">
                <section class="min-w-0 space-y-5">
                    <div v-if="message" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">{{ message }}</div>
                    <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 shadow-sm">{{ error }}</div>
                    <div v-if="form.status === 'issued'" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm">
                        <span class="font-semibold">Editing an issued document.</span>
                        Any changes you save will update the PDF the customer sees. The original at-issue snapshot is replaced.
                    </div>

                    <div v-if="document?.converted_from || (document?.converted_to?.length)"
                         class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                        <div v-if="document.converted_from" class="flex flex-wrap items-baseline gap-1">
                            <span class="font-medium">Derived from:</span>
                            <Link :href="`/documents/${document.converted_from.id}`" class="font-mono underline">
                                {{ document.converted_from.official_number || `Draft #${document.converted_from.id}` }}
                            </Link>
                            <span class="text-xs text-indigo-700">({{ document.converted_from.document_type }} · {{ document.converted_from.status }})</span>
                        </div>
                        <div v-if="document?.converted_to?.length" class="mt-1">
                            <span class="font-medium">Derived to:</span>
                            <ul class="ml-4 list-disc">
                                <li v-for="child in document.converted_to" :key="child.id">
                                    <Link :href="`/documents/${child.id}`" class="font-mono underline">
                                        {{ child.official_number || `Draft #${child.id}` }}
                                    </Link>
                                    <span class="text-xs text-indigo-700">({{ child.document_type }} · {{ child.status }})</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <datalist id="customer-options">
                        <option v-for="c in customers" :key="c.id" :value="c.name"></option>
                    </datalist>


                    <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm">
                        <div class="grid gap-4 md:grid-cols-4">
                            <label class="text-sm font-medium text-gray-700">
                                Type
                                <select v-model="form.document_type" :disabled="!canEdit" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                Customer
                                <input v-model="form.customer_name" :disabled="!canEdit"
                                       list="customer-options"
                                       @change="customerAutofill"
                                       @blur="customerAutofill"
                                       class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                       placeholder="Type or pick (blank = walk-in)">
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                Date
                                <input v-model="form.document_date" :disabled="!canEdit" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                Due / Valid Until
                                <input v-model="form.due_date" :disabled="!canEdit" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                        </div>
                        <div class="mt-5 rounded-xl border border-gray-100 bg-gray-50/70 p-4" v-if="form.customer_name">
                            <div class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                                Customer Details
                                <span class="ml-1 font-normal normal-case tracking-normal text-gray-400">— saved to Master Data on draft save</span>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="text-xs font-medium text-gray-700">
                                    Attn
                                    <input v-model="form.customer_attention_to" :disabled="!canEdit" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Attention to">
                                </label>
                                <label class="text-xs font-medium text-gray-700">
                                    Tel
                                    <input v-model="form.customer_phone" :disabled="!canEdit" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Phone">
                                </label>
                                <label class="text-xs font-medium text-gray-700 md:col-span-2">
                                    Email
                                    <input v-model="form.customer_email" :disabled="!canEdit" type="email" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Email">
                                </label>
                                <label class="text-xs font-medium text-gray-700 md:col-span-2">
                                    Address
                                    <textarea v-model="form.customer_address" :disabled="!canEdit" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Multi-line address (renders verbatim in PDF)" />
                                </label>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-4 md:grid-cols-4">
                            <label class="text-sm font-medium text-gray-700">
                                Currency
                                <input v-model="form.currency" :disabled="!canEdit" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                FX Rate
                                <input v-model="form.fx_rate" :disabled="!canEdit" type="number" step="0.00000001" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="flex items-center gap-2 pt-6 text-sm font-medium text-gray-700">
                                <input v-model="form.show_amount_in_words" :disabled="!canEdit" type="checkbox" class="rounded border-gray-300">
                                Amount in words
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                Words Locale
                                <select v-model="form.amount_in_words_locale" :disabled="!canEdit" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    <option value="ms_MY">Malay</option>
                                    <option value="en_MY">English</option>
                                </select>
                            </label>
                        </div>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700">
                                "Computer-generated document" footer
                                <select v-model="form.show_computer_generated_footer" :disabled="!canEdit" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    <option :value="null">Inherit (Company default: {{ companyFooterDefaultLabel }})</option>
                                    <option :value="true">Always show</option>
                                    <option :value="false">Always hide</option>
                                </select>
                                <span class="mt-1 block text-xs font-normal text-gray-500">Per-invoice override. Leave on "Inherit" to follow the Master Data → Company setting.</span>
                            </label>
                        </div>
                        <div v-if="isPgg" class="mt-4 grid gap-4 rounded-md border border-indigo-100 bg-indigo-50 p-4 md:grid-cols-2">
                            <label class="text-sm font-medium text-indigo-900">
                                Product Line
                                <select v-model="form.product_line" :disabled="!canEdit" class="mt-1 w-full rounded-md border-indigo-200 text-sm">
                                    <option value="">Standard</option>
                                    <option value="scentury">SCENTURY</option>
                                </select>
                                <span class="mt-1 block text-xs text-indigo-700">SCENTURY: gold accent + sub-line "SCENTURY by Persada".</span>
                            </label>
                            <label class="flex items-start gap-2 pt-6 text-sm font-medium text-indigo-900">
                                <input v-model="form.include_arabic_salutation" :disabled="!canEdit" type="checkbox" class="mt-0.5 rounded border-indigo-300">
                                <span>
                                    Include Arabic Salutation
                                    <span class="mt-0.5 block text-xs font-normal text-indigo-700">Renders Bismillah + Assalamualaikum block atop page 1.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                            <div>
                                <h3 class="text-base font-semibold tracking-tight text-gray-900">Line Items</h3>
                                <p class="mt-0.5 text-xs text-gray-500">{{ form.items.length }} {{ form.items.length === 1 ? 'item' : 'items' }}</p>
                            </div>
                            <button class="rounded-lg border border-gray-200 bg-white px-3.5 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50" :disabled="!canEdit" @click="addItem">+ Add Item</button>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <div v-for="(item, index) in form.items" :key="index" class="px-6 py-5 transition hover:bg-gray-50/40">
                                <div class="flex items-center justify-between text-xs font-semibold tracking-wide text-gray-400">
                                    <span class="uppercase">Item {{ index + 1 }}</span>
                                    <button class="rounded-md px-2 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 disabled:opacity-30" :disabled="!canEdit || form.items.length === 1" @click="removeItem(index)">Remove</button>
                                </div>

                                <input v-model="item.section_header" :disabled="!canEdit"
                                       class="mt-2 w-full rounded-lg border-amber-200 bg-amber-50/60 text-xs placeholder:text-amber-700/60"
                                       placeholder="Section heading (e.g. Bilik Muaazzin) — optional">

                                <div class="mt-3 grid gap-3 lg:grid-cols-[1fr_auto] lg:items-start">
                                    <div class="space-y-2 min-w-0">
                                        <input v-model="item.product_name" :disabled="!canEdit"
                                               :list="`product-options-${index}`"
                                               @change="productAutofill(index)"
                                               class="w-full rounded-lg border-gray-300 text-sm"
                                               placeholder="Product lookup (type or pick)">
                                        <datalist :id="`product-options-${index}`">
                                            <option v-for="product in products" :key="product.id" :value="product.name"></option>
                                        </datalist>
                                        <textarea v-model="item.description" :disabled="!canEdit" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Item description"></textarea>
                                    </div>
                                    <div class="rounded-xl bg-gray-50 px-5 py-3 text-right lg:min-w-[180px]">
                                        <div class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Line Total</div>
                                        <div class="mt-1 text-lg font-semibold tracking-tight text-gray-900">{{ money((Number(item.quantity || 0) * Number(item.unit_price || 0)) - Number(item.discount || 0) + Number(item.tax_amount || 0), form.currency) }}</div>
                                        <div v-if="isAdmin && item.cost_unit != null && item.cost_unit !== ''" class="mt-1 font-mono text-[11px] text-amber-700">
                                            margin {{ money((Number(item.unit_price || 0) - Number(item.cost_unit || 0)) * Number(item.quantity || 0), form.currency) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 grid gap-2 grid-cols-2 sm:grid-cols-3" :class="isAdmin ? 'lg:grid-cols-6' : 'lg:grid-cols-5'">
                                    <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                        Qty
                                        <input v-model.number="item.quantity" :disabled="!canEdit" type="number" step="0.0001" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                    </label>
                                    <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                        UOM
                                        <select v-model="item.uom" :disabled="!canEdit" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                            <option v-if="item.uom && !UOM_OPTIONS.includes(item.uom)" :value="item.uom">{{ item.uom }} (custom)</option>
                                            <option v-for="u in UOM_OPTIONS" :key="u" :value="u">{{ u }}</option>
                                        </select>
                                    </label>
                                    <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                        Price
                                        <input v-model.number="item.unit_price" :disabled="!canEdit || !canPrice" type="number" step="0.01" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                    </label>
                                    <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                        Discount
                                        <input v-model.number="item.discount" :disabled="!canEdit || !canPrice" type="number" step="0.01" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                    </label>
                                    <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500" title="Tax type and amount">
                                        Tax
                                        <div class="mt-1 flex gap-1">
                                            <input v-model="item.tax_type" :disabled="!canEdit || !canPrice" class="w-1/2 min-w-0 rounded-lg border-gray-300 text-sm" placeholder="SST">
                                            <input v-model.number="item.tax_amount" :disabled="!canEdit || !canPrice" type="number" step="0.01" class="w-1/2 min-w-0 rounded-lg border-gray-300 text-sm" placeholder="0.00">
                                        </div>
                                    </label>
                                    <label v-if="isAdmin" class="text-[11px] font-semibold uppercase tracking-wide text-amber-700" title="Internal cost per unit (admin only, not in PDF)">
                                        Cost
                                        <input v-model.number="item.cost_unit" :disabled="!canEdit || !canPrice" type="number" step="0.01" class="mt-1 w-full rounded-lg border-amber-200 bg-amber-50 text-sm" placeholder="optional">
                                    </label>
                                </div>

                                <details class="group mt-3">
                                    <summary class="cursor-pointer select-none text-xs font-medium text-gray-500 transition hover:text-gray-700">
                                        <span class="group-open:hidden">+ Image data URI (optional)</span>
                                        <span class="hidden group-open:inline">− Hide image URI</span>
                                    </summary>
                                    <input v-model="item.image_url" :disabled="!canEdit"
                                           class="mt-2 w-full rounded-lg border-gray-300 font-mono text-xs"
                                           placeholder="data:image/png;base64,...">
                                </details>
                            </div>
                            <div v-if="form.items.length === 0" class="px-6 py-12 text-center text-sm text-gray-500">
                                No line items yet. Click <strong>+ Add Item</strong> above to start.
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm">
                            <label class="text-sm font-semibold tracking-tight text-gray-900">Terms</label>
                            <textarea v-model="form.terms" :disabled="!canEdit" rows="4" class="mt-2 w-full rounded-lg border-gray-300 text-sm"></textarea>
                        </div>
                        <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm">
                            <label class="text-sm font-semibold tracking-tight text-gray-900">Notes</label>
                            <textarea v-model="form.notes" :disabled="!canEdit" rows="4" class="mt-2 w-full rounded-lg border-gray-300 text-sm"></textarea>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold tracking-tight text-gray-900">Artwork Attachments</h3>
                            <span class="text-xs text-gray-500">JPG/PNG/WEBP/PDF · max 10 MB per file</span>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-[1fr_220px_auto]">
                            <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" :disabled="!canEdit" class="rounded-md border border-gray-300 px-3 py-2 text-sm" @change="artworkFiles = Array.from($event.target.files)">
                            <input v-model="artworkCaption" :disabled="!canEdit" class="rounded-md border-gray-300 text-sm" placeholder="Caption (prefix; #N appended for multi-upload)">
                            <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 disabled:opacity-50" :disabled="busy || !canEdit || artworkFiles.length === 0" @click="uploadArtwork">Upload {{ artworkFiles.length > 1 ? `(${artworkFiles.length})` : '' }}</button>
                        </div>
                        <div v-if="artworkFiles.length > 0" class="mt-2 text-xs text-gray-600">
                            Selected: {{ artworkFiles.map((f) => `${f.name} (${(f.size / 1024 / 1024).toFixed(2)} MB)`).join(', ') }}
                        </div>
                        <div class="mt-3 divide-y divide-gray-100 text-sm">
                            <div v-for="(attachment, index) in form.attachments" :key="attachment.id"
                                 class="flex items-center justify-between gap-3 py-2 transition"
                                 :class="{
                                     'cursor-move': canEdit,
                                     'opacity-40': draggedAttachmentIndex === index,
                                     'border-y-2 border-indigo-400 bg-indigo-50': draggedAttachmentIndex !== null && draggedAttachmentIndex !== index,
                                 }"
                                 :draggable="canEdit && !busy"
                                 @dragstart="onAttachmentDragStart(index)"
                                 @dragover.prevent
                                 @drop.prevent="onAttachmentDrop(index)"
                                 @dragend="onAttachmentDragEnd">
                                <div class="flex flex-1 items-center gap-2 truncate">
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-600">{{ index + 1 }}</span>
                                    <span class="truncate">{{ attachment.caption || attachment.original_name }}</span>
                                    <span class="text-xs text-gray-400">·</span>
                                    <span class="text-xs text-gray-500">{{ attachment.mime_type }}</span>
                                    <span v-if="attachment.size_bytes" class="text-xs text-gray-400">· {{ (attachment.size_bytes / 1024).toFixed(0) }} KB</span>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button :disabled="!canEdit || busy || index === 0" @click="moveAttachment(index, -1)" class="rounded border border-gray-200 px-2 py-1 text-xs text-gray-600 disabled:opacity-30" title="Move up">↑</button>
                                    <button :disabled="!canEdit || busy || index === form.attachments.length - 1" @click="moveAttachment(index, 1)" class="rounded border border-gray-200 px-2 py-1 text-xs text-gray-600 disabled:opacity-30" title="Move down">↓</button>
                                    <button :disabled="!canEdit || busy" @click="removeAttachment(attachment.id)" class="rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-700 disabled:opacity-30">Remove</button>
                                </div>
                            </div>
                            <div v-if="form.attachments.length === 0" class="py-4 text-gray-500">No artwork uploaded.</div>
                        </div>
                    </div>
                </section>

                <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                    <div v-if="isAdmin" class="rounded-2xl border border-amber-200/70 bg-amber-50/60 p-5 shadow-sm">
                        <h3 class="text-sm font-semibold tracking-tight text-amber-900">Margin <span class="text-xs font-normal text-amber-700">· admin only</span></h3>
                        <dl class="mt-3 space-y-1.5 text-sm">
                            <div class="flex justify-between"><dt class="text-amber-900/80">Total Margin</dt><dd class="font-mono text-amber-950">{{ money(totalMargin, form.currency) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-amber-900/80">Margin %</dt><dd class="font-mono text-amber-950">{{ marginRate.toFixed(1) }}%</dd></div>
                        </dl>
                        <p class="mt-3 text-xs leading-relaxed text-amber-700/90">Set <code class="rounded bg-amber-100/70 px-1">Cost</code> per row to track margin. Hidden from PDF.</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-semibold tracking-tight text-gray-900">Totals</h3>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between text-gray-600"><dt>Subtotal</dt><dd class="font-medium text-gray-900">{{ money(subtotal, form.currency) }}</dd></div>
                            <div class="flex justify-between text-gray-600"><dt>Discount</dt><dd class="font-medium text-gray-900">{{ money(discountTotal, form.currency) }}</dd></div>
                            <div class="flex justify-between text-gray-600"><dt>Tax</dt><dd class="font-medium text-gray-900">{{ money(taxTotal, form.currency) }}</dd></div>
                            <div class="mt-2 flex justify-between border-t border-gray-100 pt-3 text-base font-semibold tracking-tight"><dt class="text-gray-900">Total</dt><dd class="text-gray-900">{{ money(grandTotal, form.currency) }}</dd></div>
                        </dl>
                        <div class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Draft Hash</div>
                            <span class="break-all font-mono">{{ form.draft_hash || 'Save draft first' }}</span>
                        </div>
                        <div class="mt-3 rounded-lg p-3 text-xs font-medium" :class="previewIsFresh ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'">
                            {{ previewIsFresh ? '✓ Preview is current' : '↻ Preview required before issue' }}
                        </div>
                    </div>

                    <div v-if="form.id" class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-semibold tracking-tight text-gray-900">Document Actions</h3>
                        <div class="mt-4 grid gap-2">
                            <div class="grid grid-cols-2 gap-2">
                                <button class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50" @click="previewPdf('a4')">Preview A4</button>
                                <button class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50" @click="downloadPdf('a4')">Download A4</button>
                            </div>
                            <div v-if="isThermalEligible" class="grid grid-cols-2 gap-2">
                                <button class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50" @click="previewPdf('60mm')">Preview 60mm</button>
                                <button class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50" @click="downloadPdf('60mm')">Download 60mm</button>
                            </div>
                        </div>
                        <div v-if="form.status === 'issued' && availableDeriveTargets.length > 0" class="mt-5 space-y-2 border-t border-gray-100 pt-4">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Generate from this</div>
                            <div class="grid gap-2">
                                <button v-for="target in availableDeriveTargets" :key="target"
                                        :disabled="busy"
                                        @click="deriveDocument(target)"
                                        class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-left text-xs font-medium text-indigo-900 shadow-sm transition hover:bg-indigo-100 disabled:opacity-50">
                                    + {{ docTypeLabel(target) }}
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-500">Source stays issued. New draft opens with customer + items pre-filled.</p>
                        </div>
                        <div v-if="form.status === 'issued'" class="mt-5 space-y-3 border-t border-gray-100 pt-4">
                            <textarea v-model="voidReason" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Void reason"></textarea>
                            <button class="w-full rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800" @click="voidDocument">Void</button>
                        </div>
                    </div>

                    <div v-if="form.id && ['draft', 'issued', 'void', 'cancelled'].includes(form.status)"
                         class="rounded-2xl border border-red-200/70 bg-red-50/40 p-6 shadow-sm">
                        <h3 class="text-sm font-semibold tracking-tight text-red-900">Danger Zone</h3>
                        <p class="mt-1 text-xs leading-relaxed text-red-800/80">
                            Soft-deletes this document. The number
                            <span class="font-mono font-semibold">{{ form.official_number || `Draft #${form.id}` }}</span>
                            will be reusable for new documents.
                        </p>
                        <button
                            type="button"
                            class="mt-3 w-full rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800 disabled:opacity-50"
                            :disabled="busy || childrenCount > 0"
                            :title="childrenCount > 0 ? `Has ${childrenCount} derived doc(s) — delete those first` : 'Delete this document'"
                            @click="deleteThisDocument"
                        >
                            Delete Document
                            <span v-if="childrenCount > 0" class="ml-1 text-[10px] font-normal opacity-80">(blocked: {{ childrenCount }} children)</span>
                        </button>
                    </div>

                    <div v-if="form.id && statusHistory.length > 0" class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-semibold tracking-tight text-gray-900">Status Timeline</h3>
                        <ol class="mt-3 space-y-3 text-xs">
                            <li v-for="event in statusHistory" :key="event.id" class="border-l-2 border-gray-200 pl-3">
                                <div class="flex items-baseline justify-between">
                                    <span class="font-mono font-semibold" :class="statusColor(event.to_status)">
                                        {{ event.from_status ? `${event.from_status} → ${event.to_status}` : event.to_status }}
                                    </span>
                                    <span class="text-gray-400">{{ event.created_at?.slice(0, 16).replace('T', ' ') }}</span>
                                </div>
                                <div v-if="event.changed_by" class="mt-0.5 text-gray-600">by {{ event.changed_by }}</div>
                                <div v-if="event.reason" class="mt-1 rounded bg-red-50 px-2 py-1 text-red-700">
                                    <span class="font-semibold">Reason:</span> {{ event.reason }}
                                </div>
                            </li>
                        </ol>
                    </div>
                </aside>
            </div>
        </div>

        <div v-if="issueOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Confirm Issue</h3>
                <p class="mt-2 text-sm text-gray-600">Official number will be allocated only after this confirmation. Preview must match current draft hash.</p>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="rounded bg-gray-50 p-3">Draft hash: <span class="break-all font-mono">{{ form.draft_hash }}</span></div>
                    <label class="block font-medium text-gray-700">
                        Confirmed total
                        <input v-model="confirmedTotal" type="number" step="0.01" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </label>
                    <div v-if="!previewIsFresh" class="rounded bg-red-50 p-3 text-red-700">Preview the current draft before issuing.</div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700" @click="issueOpen = false">Cancel</button>
                    <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy || !previewIsFresh" @click="issueDocument">Issue Document</button>
                </div>
            </div>
        </div>

        <!-- PDF Preview Modal: inline browser-native renderer, sized to A4 or 60mm thermal. -->
        <div
            v-if="previewModal.open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 sm:p-4"
            @click.self="closePreview"
        >
            <div
                class="flex flex-col rounded-lg bg-white shadow-2xl transition-[width,height,max-width,max-height] duration-150"
                :class="previewModal.maximized
                    ? 'h-[98vh] w-[98vw] max-h-[98vh]'
                    : (previewModal.paper === '60mm'
                        ? 'max-h-[95vh] w-[360px]'
                        : 'max-h-[95vh] w-full max-w-[1100px]')"
            >
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">
                            PDF Preview
                            <span class="ml-1 rounded bg-gray-100 px-2 py-0.5 text-xs font-normal text-gray-600">
                                {{ previewModal.paper === '60mm' ? 'Thermal 60mm' : 'A4 · 210×297mm' }}
                            </span>
                        </h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            @click="toggleMaximize"
                            :title="previewModal.maximized ? 'Restore default size' : 'Maximize'"
                        >
                            {{ previewModal.maximized ? 'Restore' : 'Maximize' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800"
                            @click="downloadPdf(previewModal.paper)"
                        >
                            Download
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            @click="closePreview"
                        >
                            Close
                        </button>
                    </div>
                </div>
                <div
                    class="flex-1 overflow-hidden rounded-b-lg bg-gray-100"
                    :style="previewModal.maximized
                        ? { height: 'calc(98vh - 48px)' }
                        : (previewModal.paper === '60mm' ? { height: '85vh' } : { height: '88vh' })"
                >
                    <iframe
                        :src="previewModal.url"
                        class="h-full w-full border-0"
                        title="PDF preview"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
