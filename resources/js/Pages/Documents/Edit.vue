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

// MIRRORS app/Services/DocumentWorkflowService.php::CONVERSION_TARGETS
const CONVERSION_TARGETS = {
    quotation: ['invoice', 'delivery_order'],
    invoice: ['delivery_order'],
};

const form = reactive({
    id: props.document?.id ?? null,
    status: props.document?.status ?? 'draft',
    official_number: props.document?.official_number ?? null,
    document_type: props.document?.document_type ?? 'invoice',
    customer_id: props.document?.customer_id ?? '',
    customer_name: props.document?.customer?.name ?? '',
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
const artworkFile = ref(null);
const artworkCaption = ref('');
const availableConvertTargets = computed(() => CONVERSION_TARGETS[form.document_type] || []);
const convertTarget = ref(availableConvertTargets.value[0] ?? 'invoice');
const voidReason = ref('');

const isDraft = computed(() => form.status === 'draft');
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
    const customerMatch = props.customers?.find((c) => c.name === form.customer_name?.trim());
    return {
        document_type: form.document_type,
        customer_id: customerMatch?.id ?? null,
        document_date: form.document_date,
        due_date: form.due_date || null,
        currency: form.currency,
        fx_rate: form.fx_rate || null,
        terms: form.terms || null,
        notes: form.notes || null,
        product_line: form.product_line || null,
        include_arabic_salutation: form.include_arabic_salutation,
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

async function previewPdf(paper = 'a4') {
    if (!form.id) {
        await saveDraft();
    }
    if (!form.id) return;
    lastPreviewHash.value = form.draft_hash;
    window.open(`/api/documents/${form.id}/pdf?paper=${paper}`, '_blank');
}

async function uploadArtwork() {
    if (!form.id) {
        await saveDraft();
    }
    if (!form.id || !artworkFile.value) return;
    busy.value = true;
    error.value = '';
    const body = new FormData();
    body.append('file', artworkFile.value);
    body.append('caption', artworkCaption.value || artworkFile.value.name);
    body.append('include_in_pdf', '1');
    try {
        const attachment = await apiFetch(`/api/documents/${form.id}/attachments`, { method: 'POST', body });
        form.attachments.push(attachment);
        artworkFile.value = null;
        artworkCaption.value = '';
        message.value = 'Artwork uploaded.';
    } catch (exception) {
        error.value = exception.message;
    } finally {
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

async function convertDocument() {
    busy.value = true;
    error.value = '';
    try {
        const document = await apiFetch(`/api/documents/${form.id}/convert`, { method: 'POST', body: JSON.stringify({ target_type: convertTarget.value }) });
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
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">
                        {{ form.official_number || (form.id ? `Draft #${form.id}` : 'New Draft') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">{{ form.document_type }} · {{ form.status }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('documents.index')" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700">Back</Link>
                    <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700" :disabled="busy || !form.id" @click="previewPdf('a4')">Preview A4</button>
                    <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700" :disabled="busy || !form.id" @click="previewPdf('60mm')">60mm</button>
                    <button class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy || !isDraft" @click="saveDraft">Save Draft</button>
                    <button class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy || !isDraft || !form.id" @click="openIssue">Issue</button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 lg:grid-cols-[1fr_320px] lg:px-8">
                <section class="space-y-5">
                    <div v-if="message" class="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ message }}</div>
                    <div v-if="error" class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ error }}</div>

                    <div v-if="document?.converted_from || (document?.converted_to?.length)"
                         class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                        <div v-if="document.converted_from" class="flex flex-wrap items-baseline gap-1">
                            <span class="font-medium">Source:</span>
                            <Link :href="`/documents/${document.converted_from.id}`" class="font-mono underline">
                                {{ document.converted_from.official_number || `Draft #${document.converted_from.id}` }}
                            </Link>
                            <span class="text-xs text-indigo-700">({{ document.converted_from.document_type }} · {{ document.converted_from.status }})</span>
                        </div>
                        <div v-if="document?.converted_to?.length" class="mt-1">
                            <span class="font-medium">Converted to:</span>
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

                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="grid gap-4 md:grid-cols-4">
                            <label class="text-sm font-medium text-gray-700">
                                Type
                                <select v-model="form.document_type" :disabled="!isDraft" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                Customer
                                <input v-model="form.customer_name" :disabled="!isDraft"
                                       list="customer-options"
                                       class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                       placeholder="Type or pick (blank = walk-in)">
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                Date
                                <input v-model="form.document_date" :disabled="!isDraft" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                Due / Valid Until
                                <input v-model="form.due_date" :disabled="!isDraft" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                        </div>
                        <div class="mt-4 grid gap-4 md:grid-cols-4">
                            <label class="text-sm font-medium text-gray-700">
                                Currency
                                <input v-model="form.currency" :disabled="!isDraft" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                FX Rate
                                <input v-model="form.fx_rate" :disabled="!isDraft" type="number" step="0.00000001" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="flex items-center gap-2 pt-6 text-sm font-medium text-gray-700">
                                <input v-model="form.show_amount_in_words" :disabled="!isDraft" type="checkbox" class="rounded border-gray-300">
                                Amount in words
                            </label>
                            <label class="text-sm font-medium text-gray-700">
                                Words Locale
                                <select v-model="form.amount_in_words_locale" :disabled="!isDraft" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    <option value="ms_MY">Malay</option>
                                    <option value="en_MY">English</option>
                                </select>
                            </label>
                        </div>
                        <div v-if="isPgg" class="mt-4 grid gap-4 rounded-md border border-indigo-100 bg-indigo-50 p-4 md:grid-cols-2">
                            <label class="text-sm font-medium text-indigo-900">
                                Product Line
                                <select v-model="form.product_line" :disabled="!isDraft" class="mt-1 w-full rounded-md border-indigo-200 text-sm">
                                    <option value="">Standard</option>
                                    <option value="scentury">SCENTURY</option>
                                </select>
                                <span class="mt-1 block text-xs text-indigo-700">SCENTURY: gold accent + sub-line "SCENTURY by Persada".</span>
                            </label>
                            <label class="flex items-start gap-2 pt-6 text-sm font-medium text-indigo-900">
                                <input v-model="form.include_arabic_salutation" :disabled="!isDraft" type="checkbox" class="mt-0.5 rounded border-indigo-300">
                                <span>
                                    Include Arabic Salutation
                                    <span class="mt-0.5 block text-xs font-normal text-indigo-700">Renders Bismillah + Assalamualaikum block atop page 1.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                            <h3 class="text-sm font-semibold text-gray-900">Line Items</h3>
                            <button class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700" :disabled="!isDraft" @click="addItem">Add Item</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Item</th>
                                        <th class="px-4 py-3">Qty</th>
                                        <th class="px-4 py-3">UOM</th>
                                        <th class="px-4 py-3">Rate</th>
                                        <th v-if="isAdmin" class="px-4 py-3" title="Internal cost per unit (admin only, not in PDF)">Cost</th>
                                        <th class="px-4 py-3">Disc</th>
                                        <th class="px-4 py-3">Tax</th>
                                        <th v-if="isAdmin" class="px-4 py-3 text-right" title="(Unit price &minus; cost) &times; qty">Margin</th>
                                        <th class="px-4 py-3 text-right">Total</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(item, index) in form.items" :key="index">
                                        <td class="px-4 py-3 align-top">
                                            <input v-model="item.section_header" :disabled="!isDraft"
                                                   class="mb-2 w-full rounded-md border-amber-200 bg-amber-50 text-xs"
                                                   placeholder="Section heading (e.g. Bilik Muaazzin) &mdash; optional">
                                            <input v-model="item.product_name" :disabled="!isDraft"
                                                   :list="`product-options-${index}`"
                                                   @change="productAutofill(index)"
                                                   class="mb-2 w-full rounded-md border-gray-300 text-xs"
                                                   placeholder="Product lookup (type or pick)">
                                            <datalist :id="`product-options-${index}`">
                                                <option v-for="product in products" :key="product.id" :value="product.name"></option>
                                            </datalist>
                                            <textarea v-model="item.description" :disabled="!isDraft" rows="2" class="w-72 rounded-md border-gray-300 text-sm" placeholder="Item description"></textarea>
                                            <input v-model="item.image_url" :disabled="!isDraft"
                                                   class="mt-2 w-72 rounded-md border-gray-300 font-mono text-xs"
                                                   placeholder="Image data URI (data:image/png;base64,...) &mdash; optional">
                                        </td>
                                        <td class="px-4 py-3 align-top"><input v-model.number="item.quantity" :disabled="!isDraft" type="number" step="0.0001" class="w-20 rounded-md border-gray-300 text-sm"></td>
                                        <td class="px-4 py-3 align-top"><input v-model="item.uom" :disabled="!isDraft" class="w-20 rounded-md border-gray-300 text-sm"></td>
                                        <td class="px-4 py-3 align-top"><input v-model.number="item.unit_price" :disabled="!isDraft || !canPrice" type="number" step="0.01" class="w-24 rounded-md border-gray-300 text-sm"></td>
                                        <td v-if="isAdmin" class="px-4 py-3 align-top">
                                            <input v-model.number="item.cost_unit" :disabled="!isDraft || !canPrice" type="number" step="0.01" class="w-24 rounded-md border-amber-200 bg-amber-50 text-sm" placeholder="optional">
                                        </td>
                                        <td class="px-4 py-3 align-top"><input v-model.number="item.discount" :disabled="!isDraft || !canPrice" type="number" step="0.01" class="w-20 rounded-md border-gray-300 text-sm"></td>
                                        <td class="px-4 py-3 align-top">
                                            <input v-model="item.tax_type" :disabled="!isDraft || !canPrice" class="mb-2 w-24 rounded-md border-gray-300 text-sm" placeholder="SST">
                                            <input v-model.number="item.tax_amount" :disabled="!isDraft || !canPrice" type="number" step="0.01" class="w-24 rounded-md border-gray-300 text-sm">
                                        </td>
                                        <td v-if="isAdmin" class="px-4 py-3 align-top text-right font-mono text-xs text-amber-700">
                                            <span v-if="item.cost_unit != null && item.cost_unit !== ''">
                                                {{ money((Number(item.unit_price || 0) - Number(item.cost_unit || 0)) * Number(item.quantity || 0), form.currency) }}
                                            </span>
                                            <span v-else class="text-gray-300">&mdash;</span>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right font-medium">{{ money((Number(item.quantity || 0) * Number(item.unit_price || 0)) - Number(item.discount || 0) + Number(item.tax_amount || 0), form.currency) }}</td>
                                        <td class="px-4 py-3 align-top text-right"><button class="text-sm font-medium text-red-700" :disabled="!isDraft" @click="removeItem(index)">Remove</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                            <label class="text-sm font-medium text-gray-700">Terms</label>
                            <textarea v-model="form.terms" :disabled="!isDraft" rows="4" class="mt-1 w-full rounded-md border-gray-300 text-sm"></textarea>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                            <label class="text-sm font-medium text-gray-700">Notes</label>
                            <textarea v-model="form.notes" :disabled="!isDraft" rows="4" class="mt-1 w-full rounded-md border-gray-300 text-sm"></textarea>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900">Artwork Attachments</h3>
                            <span class="text-xs text-gray-500">Private storage · JPG/PNG/WEBP/PDF only</span>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-[1fr_220px_auto]">
                            <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" :disabled="!isDraft" class="rounded-md border border-gray-300 px-3 py-2 text-sm" @change="artworkFile = $event.target.files[0]">
                            <input v-model="artworkCaption" :disabled="!isDraft" class="rounded-md border-gray-300 text-sm" placeholder="Caption">
                            <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 disabled:opacity-50" :disabled="busy || !isDraft || !artworkFile" @click="uploadArtwork">Upload</button>
                        </div>
                        <div class="mt-3 divide-y divide-gray-100 text-sm">
                            <div v-for="attachment in form.attachments" :key="attachment.id" class="flex items-center justify-between py-2">
                                <span>{{ attachment.caption || attachment.original_name }}</span>
                                <span class="text-xs text-gray-500">{{ attachment.mime_type }}</span>
                            </div>
                            <div v-if="form.attachments.length === 0" class="py-4 text-gray-500">No artwork uploaded.</div>
                        </div>
                    </div>
                </section>

                <aside class="space-y-5">
                    <div v-if="isAdmin" class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-amber-900">Margin <span class="text-xs font-normal text-amber-700">(admin only &middot; not in PDF)</span></h3>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between"><dt>Total Margin</dt><dd class="font-mono">{{ money(totalMargin, form.currency) }}</dd></div>
                            <div class="flex justify-between"><dt>Margin %</dt><dd class="font-mono">{{ marginRate.toFixed(1) }}%</dd></div>
                        </dl>
                        <p class="mt-3 text-xs text-amber-700">Set <code>Cost</code> per row to track margin. Customers do not see this column on the PDF.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900">Totals</h3>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ money(subtotal, form.currency) }}</dd></div>
                            <div class="flex justify-between"><dt>Discount</dt><dd>{{ money(discountTotal, form.currency) }}</dd></div>
                            <div class="flex justify-between"><dt>Tax</dt><dd>{{ money(taxTotal, form.currency) }}</dd></div>
                            <div class="flex justify-between border-t border-gray-100 pt-3 text-base font-semibold"><dt>Total</dt><dd>{{ money(grandTotal, form.currency) }}</dd></div>
                        </dl>
                        <div class="mt-4 rounded bg-gray-50 p-3 text-xs text-gray-600">
                            Draft hash: <span class="break-all font-mono">{{ form.draft_hash || 'Save draft first' }}</span>
                        </div>
                        <div class="mt-3 rounded p-3 text-xs" :class="previewIsFresh ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'">
                            {{ previewIsFresh ? 'Preview is current.' : 'Preview required before issue.' }}
                        </div>
                    </div>

                    <div v-if="form.id" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900">Document Actions</h3>
                        <div class="mt-4 grid gap-2">
                            <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700" @click="previewPdf('a4')">Download / Preview A4</button>
                            <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700" @click="previewPdf('60mm')">Download 60mm</button>
                        </div>
                        <div v-if="form.status === 'issued'" class="mt-5 space-y-3">
                            <div v-if="availableConvertTargets.length > 0" class="space-y-2">
                                <select v-model="convertTarget" class="w-full rounded-md border-gray-300 text-sm">
                                    <option v-for="target in availableConvertTargets" :key="target" :value="target">{{ target }}</option>
                                </select>
                                <button class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700" @click="convertDocument">Convert to {{ convertTarget }}</button>
                            </div>
                            <textarea v-model="voidReason" rows="2" class="w-full rounded-md border-gray-300 text-sm" placeholder="Void reason"></textarea>
                            <button class="w-full rounded-md bg-red-700 px-3 py-2 text-sm font-semibold text-white" @click="voidDocument">Void</button>
                        </div>
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
    </AuthenticatedLayout>
</template>
