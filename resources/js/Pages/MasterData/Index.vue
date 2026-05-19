<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { apiFetch } from '@/lib/api';
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    company: Object,
    bankAccounts: Array,
    customers: Object,
    products: Object,
    templates: Array,
    numberingPolicies: Array,
    documentTypes: Array,
});

const activeTab = ref('company');
const busy = ref(false);
const notice = ref('');
const error = ref('');

const companyForm = reactive({
    name: props.company?.name || '',
    code: props.company?.code || '',
    registration_number: props.company?.registration_number || '',
    tin: props.company?.tin || '',
    sst_registration_number: props.company?.sst_registration_number || '',
    msic_code: props.company?.msic_code || '',
    business_activity_description: props.company?.business_activity_description || '',
    address: props.company?.address || '',
    address_line_2: props.company?.address_line_2 || '',
    postcode: props.company?.postcode || '',
    city: props.company?.city || '',
    state: props.company?.state || '',
    country: props.company?.country || 'MY',
    phone: props.company?.phone || '',
    email: props.company?.email || '',
});

const brandForm = reactive({
    brand_primary: props.company?.brand_primary || '#1a3a5c',
    brand_secondary: props.company?.brand_secondary || '#f0f4f8',
    brand_accent: props.company?.brand_accent || '#16427a',
});

const brandingAssets = reactive({
    logo: { url: props.company?.logo_url || null, path: props.company?.logo_path || null },
    stamp: { url: props.company?.stamp_url || null, path: props.company?.stamp_path || null },
    signature: { url: props.company?.signature_url || null, path: props.company?.signature_path || null },
});

const bankRows = ref([...(props.bankAccounts || [])]);

const bankForm = reactive({
    bank_name: '',
    account_number: '',
    account_holder: '',
    swift_code: '',
    is_primary: false,
    sort_order: 0,
    is_active: true,
});

const customerRows = ref([...(props.customers?.data || props.customers || [])]);
const productRows = ref([...(props.products?.data || props.products || [])]);
const templateRows = ref([...(props.templates || [])]);
const numberingRows = ref([...(props.numberingPolicies || [])]);

const customerForm = reactive({
    name: '',
    attention_to: '',
    email: '',
    phone: '',
    address: '',
    postcode: '',
    city: '',
    state: '',
    country: 'MY',
    tax_identifier: '',
    brn_registration_number: '',
    sst_registration_number: '',
    msic_code: '',
});

const productForm = reactive({
    name: '',
    sku: '',
    description: '',
    default_price: 0,
    uom: 'unit',
    tax_type: '',
    tax_rate: 0,
    classification_code: '',
});

const templateForm = reactive({
    name: '',
    document_type: 'invoice',
    paper_size: 'A4',
    is_default: false,
    show_amount_in_words: false,
    amount_in_words_locale: 'ms_MY',
    amount_in_words_currency: 'MYR',
    amount_in_words_zero_sen_style: 'SAHAJA',
    amount_in_words_label: 'RM',
    amount_in_words_position: 'final_totals',
});

const numberingForm = reactive({
    document_type: 'invoice',
    prefix: '',
    suffix: '',
    separator: '-',
    year_token: '{YYYY}',
    sequence_padding: 5,
    reset_policy: 'yearly',
    is_active: true,
});

const tabs = [
    ['company', 'Company'],
    ['branding', 'Branding'],
    ['bank-accounts', 'Bank Accounts'],
    ['boilerplate', 'PDF Boilerplate'],
    ['customers', 'Customers'],
    ['products', 'Products'],
    ['templates', 'Templates'],
    ['numbering', 'Numbering'],
];

const BOILERPLATE_DEFAULTS = {
    invoice: {
        intro: '',
        footer_terms: 'Goods sold are not returnable and payment made is not refundable.\nAll cheques should be crossed and made payable to {company_name}.',
        signature_left_intro: 'Yours faithfully,',
        signature_left_label: 'Authorised Signature',
        signature_right_intro: 'Goods received in right and good condition',
        signature_right_label: 'Company Sign & Chop',
    },
    quotation: {
        intro: 'Thank you for your inquiry. We are pleased to submit our quote as follows:',
        footer_terms: 'We hope that our quotation is favourable to you and we look forward to receiving your valued order.\nIf you require further clarification, please do not hesitate to contact us.',
        signature_left_intro: 'Yours faithfully,',
        signature_left_label: 'Authorised Signature',
        signature_right_intro: 'We confirm the order by accepting the terms',
        signature_right_label: 'Signature & Company Stamp',
    },
    delivery_order: {
        intro: '',
        footer_terms: '',
        signature_left_intro: 'Delivered by,',
        signature_left_label: 'Authorised Signature',
        signature_right_intro: 'Goods received in right and good condition',
        signature_right_label: 'Customer Sign & Chop',
    },
    official_receipt: {
        intro: 'Received with thanks the sum of:',
        footer_terms: '',
        signature_left_intro: '',
        signature_left_label: '',
        signature_right_intro: 'For {company_name}',
        signature_right_label: 'Authorised Signature',
    },
};

function blankBoilerplate() {
    return JSON.parse(JSON.stringify(BOILERPLATE_DEFAULTS));
}

function mergeBoilerplate(saved) {
    const base = blankBoilerplate();
    if (! saved || typeof saved !== 'object') return base;
    for (const docType of Object.keys(base)) {
        if (saved[docType] && typeof saved[docType] === 'object') {
            for (const key of Object.keys(base[docType])) {
                if (saved[docType][key] !== undefined && saved[docType][key] !== null) {
                    base[docType][key] = saved[docType][key];
                }
            }
        }
    }
    return base;
}

const boilerplateForm = reactive(mergeBoilerplate(props.company?.pdf_boilerplate));
const boilerplateDocType = ref('invoice');
const boilerplateMsg = ref('');
const boilerplateBusy = ref(false);

const boilerplateDocTypes = [
    ['invoice', 'Invoice'],
    ['quotation', 'Quotation'],
    ['delivery_order', 'Delivery Order'],
    ['official_receipt', 'Official Receipt'],
];

const boilerplateFields = [
    ['intro', 'Intro text (above items table)', 'textarea'],
    ['footer_terms', 'Footer terms / disclaimer', 'textarea'],
    ['signature_left_intro', 'Signature - Left column intro', 'input'],
    ['signature_left_label', 'Signature - Left column label', 'input'],
    ['signature_right_intro', 'Signature - Right column intro', 'input'],
    ['signature_right_label', 'Signature - Right column label', 'input'],
];

function resetBoilerplateDocType(docType) {
    Object.assign(boilerplateForm[docType], BOILERPLATE_DEFAULTS[docType]);
}

async function saveBoilerplate() {
    if (! props.company?.id) return;
    boilerplateBusy.value = true;
    boilerplateMsg.value = '';
    try {
        const sanitized = JSON.parse(JSON.stringify(boilerplateForm));
        const updated = await apiFetch(`/api/companies/${props.company.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ pdf_boilerplate: sanitized }),
        });
        if (updated?.pdf_boilerplate) {
            Object.assign(boilerplateForm, mergeBoilerplate(updated.pdf_boilerplate));
        }
        boilerplateMsg.value = 'PDF boilerplate saved.';
    } catch (e) {
        boilerplateMsg.value = e.message || 'Save failed.';
    } finally {
        boilerplateBusy.value = false;
    }
}

const numberingPreview = computed(() => {
    const year = new Date().getFullYear().toString();
    const token = (numberingForm.year_token || '{YYYY}').replace('{YYYY}', year);
    const sequence = '#'.repeat(Number(numberingForm.sequence_padding || 5));

    return [
        numberingForm.prefix,
        token,
        sequence,
        numberingForm.suffix,
    ].filter(Boolean).join(numberingForm.separator ?? '-');
});

async function saveCompany() {
    await run(async () => {
        await apiFetch(`/api/companies/${props.company.id}`, {
            method: 'PATCH',
            body: companyForm,
        });
        notice.value = 'Company profile saved.';
    });
}

async function createCustomer() {
    await run(async () => {
        const customer = await apiFetch('/api/customers', {
            method: 'POST',
            body: customerForm,
        });
        customerRows.value.unshift(customer);
        reset(customerForm, {
            name: '',
            attention_to: '',
            email: '',
            phone: '',
            address: '',
            postcode: '',
            city: '',
            state: '',
            country: 'MY',
            tax_identifier: '',
            brn_registration_number: '',
            sst_registration_number: '',
            msic_code: '',
        });
        notice.value = 'Customer created.';
    });
}

async function createProduct() {
    await run(async () => {
        const product = await apiFetch('/api/products', {
            method: 'POST',
            body: productForm,
        });
        productRows.value.unshift(product);
        reset(productForm, {
            name: '',
            sku: '',
            description: '',
            default_price: 0,
            uom: 'unit',
            tax_type: '',
            tax_rate: 0,
            classification_code: '',
        });
        notice.value = 'Product created.';
    });
}

const editingCustomerId = ref(null);
const editingProductId = ref(null);
const customerImport = reactive({ file: null, result: null, busy: false });
const productImport = reactive({ file: null, result: null, busy: false });

function openCustomerEdit(c) {
    editingCustomerId.value = c.id;
    Object.assign(customerForm, {
        name: c.name ?? '',
        attention_to: c.attention_to ?? '',
        email: c.email ?? '',
        phone: c.phone ?? '',
        address: c.address ?? '',
        postcode: c.postcode ?? '',
        city: c.city ?? '',
        state: c.state ?? '',
        country: c.country ?? 'MY',
        tax_identifier: c.tax_identifier ?? '',
        brn_registration_number: c.brn_registration_number ?? '',
        sst_registration_number: c.sst_registration_number ?? '',
        msic_code: c.msic_code ?? '',
    });
}

function cancelCustomerEdit() {
    editingCustomerId.value = null;
    reset(customerForm, {
        name: '', attention_to: '', email: '', phone: '', address: '',
        postcode: '', city: '', state: '', country: 'MY',
        tax_identifier: '', brn_registration_number: '', sst_registration_number: '', msic_code: '',
    });
}

async function saveCustomer() {
    if (editingCustomerId.value) {
        await run(async () => {
            const updated = await apiFetch(`/api/customers/${editingCustomerId.value}`, {
                method: 'PATCH',
                body: customerForm,
            });
            const idx = customerRows.value.findIndex((c) => c.id === updated.id);
            if (idx >= 0) customerRows.value[idx] = updated;
            cancelCustomerEdit();
            notice.value = 'Customer updated.';
        });
    } else {
        await createCustomer();
    }
}

async function deleteCustomer(c) {
    if (!window.confirm(`Delete customer "${c.name}"?\n\nIssued documents that reference this customer keep their snapshot intact.`)) return;
    await run(async () => {
        await apiFetch(`/api/customers/${c.id}`, { method: 'DELETE' });
        customerRows.value = customerRows.value.filter((x) => x.id !== c.id);
        if (editingCustomerId.value === c.id) cancelCustomerEdit();
        notice.value = 'Customer deleted.';
    });
}

function openProductEdit(p) {
    editingProductId.value = p.id;
    Object.assign(productForm, {
        name: p.name ?? '',
        sku: p.sku ?? '',
        description: p.description ?? '',
        default_price: p.default_price ?? 0,
        uom: p.uom ?? 'unit',
        tax_type: p.tax_type ?? '',
        tax_rate: p.tax_rate ?? 0,
        classification_code: p.classification_code ?? '',
    });
}

function cancelProductEdit() {
    editingProductId.value = null;
    reset(productForm, {
        name: '', sku: '', description: '',
        default_price: 0, uom: 'unit',
        tax_type: '', tax_rate: 0, classification_code: '',
    });
}

async function saveProduct() {
    if (editingProductId.value) {
        await run(async () => {
            const updated = await apiFetch(`/api/products/${editingProductId.value}`, {
                method: 'PATCH',
                body: productForm,
            });
            const idx = productRows.value.findIndex((p) => p.id === updated.id);
            if (idx >= 0) productRows.value[idx] = updated;
            cancelProductEdit();
            notice.value = 'Product updated.';
        });
    } else {
        await createProduct();
    }
}

async function deleteProduct(p) {
    if (!window.confirm(`Delete product "${p.name}"?`)) return;
    await run(async () => {
        await apiFetch(`/api/products/${p.id}`, { method: 'DELETE' });
        productRows.value = productRows.value.filter((x) => x.id !== p.id);
        if (editingProductId.value === p.id) cancelProductEdit();
        notice.value = 'Product deleted.';
    });
}

async function importMasterData(kind) {
    const slot = kind === 'customers' ? customerImport : productImport;
    if (!slot.file) return;
    slot.busy = true;
    slot.result = null;
    try {
        const body = new FormData();
        body.append('file', slot.file);
        const result = await apiFetch(`/api/${kind}/import`, { method: 'POST', body });
        slot.result = result;
        slot.file = null;
        // Reload list by re-requesting Inertia data
        if (kind === 'customers') {
            const fresh = await apiFetch('/api/customers');
            customerRows.value = fresh.data || customerRows.value;
        } else {
            const fresh = await apiFetch('/api/products');
            productRows.value = fresh.data || productRows.value;
        }
    } catch (e) {
        slot.result = { error: e.message };
    } finally {
        slot.busy = false;
    }
}

async function saveTemplate(row) {
    await run(async () => {
        const updated = await apiFetch(`/api/templates/${row.id}`, {
            method: 'PATCH',
            body: row,
        });
        Object.assign(row, updated);
        notice.value = 'Template saved.';
    });
}

async function deleteTemplate(row) {
    if (!window.confirm(`Delete template "${row.name}" (${row.document_type})?`)) return;
    await run(async () => {
        await apiFetch(`/api/templates/${row.id}`, { method: 'DELETE' });
        templateRows.value = templateRows.value.filter((t) => t.id !== row.id);
        notice.value = 'Template deleted.';
    });
}

async function createTemplate() {
    await run(async () => {
        const template = await apiFetch('/api/templates', {
            method: 'POST',
            body: templateForm,
        });
        templateRows.value.push(template);
        notice.value = 'Template created.';
    });
}

async function saveNumbering(row) {
    await run(async () => {
        const updated = await apiFetch(`/api/numbering-policies/${row.id}`, {
            method: 'PATCH',
            body: row,
        });
        Object.assign(row, updated);
        notice.value = 'Numbering policy saved.';
    });
}

async function deleteNumbering(row) {
    if (!window.confirm(`Delete numbering policy for ${row.document_type} (prefix ${row.prefix})? Existing documents keep their numbers, but new ones will fail until you re-create.`)) return;
    await run(async () => {
        await apiFetch(`/api/numbering-policies/${row.id}`, { method: 'DELETE' });
        numberingRows.value = numberingRows.value.filter((n) => n.id !== row.id);
        notice.value = 'Numbering policy deleted.';
    });
}

async function createNumbering() {
    await run(async () => {
        const policy = await apiFetch('/api/numbering-policies', {
            method: 'POST',
            body: numberingForm,
        });
        numberingRows.value.push(policy);
        notice.value = 'Numbering policy created.';
    });
}

async function saveBrandColors() {
    await run(async () => {
        const updated = await apiFetch(`/api/companies/${props.company.id}`, {
            method: 'PATCH',
            body: brandForm,
        });
        Object.assign(brandForm, {
            brand_primary: updated.brand_primary,
            brand_secondary: updated.brand_secondary,
            brand_accent: updated.brand_accent,
        });
        notice.value = 'Brand colors saved.';
    });
}

async function uploadBranding(kind, fileEvent) {
    const file = fileEvent.target.files?.[0];
    if (!file) return;
    await run(async () => {
        const form = new FormData();
        form.append('file', file);
        const result = await apiFetch(`/api/companies/${props.company.id}/branding/${kind}`, {
            method: 'POST',
            body: form,
        });
        brandingAssets[kind].url = result.url;
        brandingAssets[kind].path = result.path;
        notice.value = `${kind.charAt(0).toUpperCase() + kind.slice(1)} uploaded.`;
        fileEvent.target.value = '';
    });
}

async function removeBranding(kind) {
    if (!brandingAssets[kind].path) return;
    if (!window.confirm(`Remove company ${kind}?`)) return;
    await run(async () => {
        await apiFetch(`/api/companies/${props.company.id}/branding/${kind}`, {
            method: 'DELETE',
        });
        brandingAssets[kind].url = null;
        brandingAssets[kind].path = null;
        notice.value = `${kind.charAt(0).toUpperCase() + kind.slice(1)} removed.`;
    });
}

async function createBankAccount() {
    await run(async () => {
        const account = await apiFetch(`/api/companies/${props.company.id}/bank-accounts`, {
            method: 'POST',
            body: bankForm,
        });
        bankRows.value.push(account);
        bankRows.value.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
        Object.assign(bankForm, {
            bank_name: '',
            account_number: '',
            account_holder: '',
            swift_code: '',
            is_primary: false,
            sort_order: 0,
            is_active: true,
        });
        notice.value = 'Bank account added.';
    });
}

async function saveBankAccount(row) {
    await run(async () => {
        const updated = await apiFetch(`/api/companies/${props.company.id}/bank-accounts/${row.id}`, {
            method: 'PATCH',
            body: row,
        });
        Object.assign(row, updated);
        if (updated.is_primary) {
            bankRows.value.forEach((r) => {
                if (r.id !== updated.id) r.is_primary = false;
            });
        }
        notice.value = 'Bank account saved.';
    });
}

async function deleteBankAccount(row) {
    if (!window.confirm(`Delete bank ${row.bank_name}?`)) return;
    await run(async () => {
        await apiFetch(`/api/companies/${props.company.id}/bank-accounts/${row.id}`, {
            method: 'DELETE',
        });
        bankRows.value = bankRows.value.filter((r) => r.id !== row.id);
        notice.value = 'Bank account removed.';
    });
}

async function run(callback) {
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
        await callback();
    } catch (err) {
        error.value = err.message || 'Request failed.';
    } finally {
        busy.value = false;
    }
}

function reset(target, values) {
    Object.keys(target).forEach((key) => {
        target[key] = values[key] ?? '';
    });
}
</script>

<template>
    <Head title="Master Data" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Master Data
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Company profile, customers, products, templates, and per-company numbering.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="[key, label] in tabs"
                        :key="key"
                        type="button"
                        class="rounded-md border px-4 py-2 text-sm font-medium"
                        :class="activeTab === key ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 bg-white text-gray-700'"
                        @click="activeTab = key"
                    >
                        {{ label }}
                    </button>
                </div>

                <div v-if="notice" class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ notice }}
                </div>
                <div v-if="error" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ error }}
                </div>

                <section v-if="activeTab === 'company'" class="mt-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-medium text-gray-700">
                            Company name
                            <input v-model="companyForm.name" class="mt-1 w-full rounded-md border-gray-300" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Company code
                            <input v-model="companyForm.code" class="mt-1 w-full rounded-md border-gray-300" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Registration number
                            <input v-model="companyForm.registration_number" class="mt-1 w-full rounded-md border-gray-300" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            TIN
                            <input v-model="companyForm.tin" class="mt-1 w-full rounded-md border-gray-300" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            SST registration
                            <input v-model="companyForm.sst_registration_number" class="mt-1 w-full rounded-md border-gray-300" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            MSIC code
                            <input v-model="companyForm.msic_code" class="mt-1 w-full rounded-md border-gray-300" />
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">
                            Business activity
                            <input v-model="companyForm.business_activity_description" class="mt-1 w-full rounded-md border-gray-300" />
                        </label>
                        <label class="text-sm font-medium text-gray-700 md:col-span-2">
                            Address
                            <textarea v-model="companyForm.address" rows="3" class="mt-1 w-full rounded-md border-gray-300" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Phone
                            <input v-model="companyForm.phone" class="mt-1 w-full rounded-md border-gray-300" />
                        </label>
                        <label class="text-sm font-medium text-gray-700">
                            Email
                            <input v-model="companyForm.email" type="email" class="mt-1 w-full rounded-md border-gray-300" />
                        </label>
                    </div>
                    <button type="button" class="mt-5 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="saveCompany">
                        Save Company
                    </button>
                </section>

                <section v-if="activeTab === 'branding'" class="mt-6 grid gap-6 lg:grid-cols-[1fr_1fr]">
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-sm font-semibold text-gray-900">Brand Colors</h3>
                        <p class="mt-1 text-xs text-gray-500">Primary tints PDF headers, totals, and accents. Use hex like #1a3a5c.</p>
                        <div class="mt-4 grid gap-3">
                            <label class="flex items-center justify-between gap-3 text-sm font-medium text-gray-700">
                                <span>Primary</span>
                                <span class="inline-flex items-center gap-2">
                                    <input v-model="brandForm.brand_primary" type="color" class="h-9 w-12 rounded border-gray-300" />
                                    <input v-model="brandForm.brand_primary" class="w-28 rounded-md border-gray-300 text-xs" />
                                </span>
                            </label>
                            <label class="flex items-center justify-between gap-3 text-sm font-medium text-gray-700">
                                <span>Secondary</span>
                                <span class="inline-flex items-center gap-2">
                                    <input v-model="brandForm.brand_secondary" type="color" class="h-9 w-12 rounded border-gray-300" />
                                    <input v-model="brandForm.brand_secondary" class="w-28 rounded-md border-gray-300 text-xs" />
                                </span>
                            </label>
                            <label class="flex items-center justify-between gap-3 text-sm font-medium text-gray-700">
                                <span>Accent</span>
                                <span class="inline-flex items-center gap-2">
                                    <input v-model="brandForm.brand_accent" type="color" class="h-9 w-12 rounded border-gray-300" />
                                    <input v-model="brandForm.brand_accent" class="w-28 rounded-md border-gray-300 text-xs" />
                                </span>
                            </label>
                        </div>
                        <button type="button" class="mt-5 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="saveBrandColors">
                            Save Colors
                        </button>
                    </div>
                    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-sm font-semibold text-gray-900">Branding Assets</h3>
                        <p class="mt-1 text-xs text-gray-500">PNG/JPG/WEBP up to 2 MB. Logo renders top-left; signature + stamp render at signature block.</p>
                        <div class="mt-4 grid gap-5">
                            <div v-for="kind in ['logo', 'stamp', 'signature']" :key="kind" class="rounded-md border border-gray-200 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium capitalize text-gray-700">{{ kind }}</span>
                                    <button v-if="brandingAssets[kind].url" type="button" class="text-xs text-red-600" :disabled="busy" @click="removeBranding(kind)">
                                        Remove
                                    </button>
                                </div>
                                <div class="mt-2 flex items-center gap-3">
                                    <div class="flex h-16 w-24 items-center justify-center rounded border border-dashed border-gray-300 bg-gray-50">
                                        <img v-if="brandingAssets[kind].url" :src="brandingAssets[kind].url" class="max-h-14 max-w-full" />
                                        <span v-else class="text-xs text-gray-400">No file</span>
                                    </div>
                                    <input type="file" accept="image/png,image/jpeg,image/webp" class="text-xs" :disabled="busy" @change="(e) => uploadBranding(kind, e)" />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="activeTab === 'bank-accounts'" class="mt-6 grid gap-6 lg:grid-cols-[420px_1fr]">
                    <form class="bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="createBankAccount">
                        <h3 class="text-sm font-semibold text-gray-900">New Bank Account</h3>
                        <p class="mt-1 text-xs text-gray-500">Used in invoice and receipt footer.</p>
                        <div class="mt-4 grid gap-3">
                            <input v-model="bankForm.bank_name" required placeholder="Bank name" class="rounded-md border-gray-300" />
                            <input v-model="bankForm.account_number" required placeholder="Account number" class="rounded-md border-gray-300" />
                            <input v-model="bankForm.account_holder" placeholder="Account holder" class="rounded-md border-gray-300" />
                            <input v-model="bankForm.swift_code" placeholder="SWIFT code (optional)" class="rounded-md border-gray-300" />
                            <div class="grid grid-cols-2 gap-3">
                                <input v-model.number="bankForm.sort_order" type="number" min="0" max="999" placeholder="Sort order" class="rounded-md border-gray-300" />
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="bankForm.is_primary" type="checkbox" class="rounded border-gray-300" />
                                    Primary
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="mt-4 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy">
                            Add Bank
                        </button>
                    </form>
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b px-5 py-3 text-sm font-semibold text-gray-900">Bank Accounts</div>
                        <div v-if="bankRows.length === 0" class="px-5 py-6 text-center text-sm text-gray-500">No bank accounts yet.</div>
                        <div v-for="row in bankRows" :key="row.id" class="border-b px-5 py-3 last:border-b-0">
                            <div class="grid gap-2 md:grid-cols-3">
                                <input v-model="row.bank_name" class="rounded-md border-gray-300 text-sm" />
                                <input v-model="row.account_number" class="rounded-md border-gray-300 text-sm" />
                                <input v-model="row.account_holder" placeholder="Account holder" class="rounded-md border-gray-300 text-sm" />
                                <input v-model="row.swift_code" placeholder="SWIFT" class="rounded-md border-gray-300 text-sm" />
                                <input v-model.number="row.sort_order" type="number" min="0" max="999" class="rounded-md border-gray-300 text-sm" />
                            </div>
                            <div class="mt-3 flex items-center gap-4 text-xs text-gray-700">
                                <label class="inline-flex items-center gap-2">
                                    <input v-model="row.is_primary" type="checkbox" class="rounded border-gray-300" />
                                    Primary
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input v-model="row.is_active" type="checkbox" class="rounded border-gray-300" />
                                    Active
                                </label>
                                <button type="button" class="ml-auto rounded-md border border-gray-300 px-3 py-1.5 font-medium" :disabled="busy" @click="saveBankAccount(row)">
                                    Save
                                </button>
                                <button type="button" class="rounded-md border border-red-300 px-3 py-1.5 font-medium text-red-600" :disabled="busy" @click="deleteBankAccount(row)">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="activeTab === 'boilerplate'" class="mt-6 space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">PDF Boilerplate</h3>
                                <p class="text-sm text-gray-500">
                                    Customise the intro, footer terms, and signature labels that appear on every generated PDF.
                                    Leave a field blank to fall back to the system default. Token <code class="rounded bg-gray-100 px-1">{company_name}</code> is replaced at render time.
                                </p>
                            </div>
                            <div v-if="boilerplateMsg" class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ boilerplateMsg }}</div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                            <button v-for="[key, label] in boilerplateDocTypes" :key="key"
                                    @click="boilerplateDocType = key"
                                    class="rounded-md border px-3 py-1.5 text-sm"
                                    :class="boilerplateDocType === key
                                        ? 'border-indigo-300 bg-indigo-50 font-semibold text-indigo-900'
                                        : 'border-gray-300 bg-white text-gray-700'">
                                {{ label }}
                            </button>
                        </div>

                        <div class="mt-5 space-y-4">
                            <div v-for="[fieldKey, fieldLabel, fieldType] in boilerplateFields" :key="`${boilerplateDocType}-${fieldKey}`">
                                <label class="block text-sm font-medium text-gray-700">
                                    {{ fieldLabel }}
                                    <span v-if="!boilerplateForm[boilerplateDocType][fieldKey] && BOILERPLATE_DEFAULTS[boilerplateDocType][fieldKey]" class="ml-1 text-xs font-normal text-gray-400">(currently default)</span>
                                </label>
                                <textarea v-if="fieldType === 'textarea'"
                                          v-model="boilerplateForm[boilerplateDocType][fieldKey]"
                                          rows="2"
                                          class="mt-1 w-full rounded-md border-gray-300 text-sm font-mono"
                                          :placeholder="BOILERPLATE_DEFAULTS[boilerplateDocType][fieldKey] || '(no default)'"></textarea>
                                <input v-else
                                       v-model="boilerplateForm[boilerplateDocType][fieldKey]"
                                       class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                       :placeholder="BOILERPLATE_DEFAULTS[boilerplateDocType][fieldKey] || '(no default)'">
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-4">
                            <button @click="resetBoilerplateDocType(boilerplateDocType)"
                                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700">
                                Reset this doc type to defaults
                            </button>
                            <button @click="saveBoilerplate"
                                    :disabled="boilerplateBusy"
                                    class="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                                {{ boilerplateBusy ? 'Saving…' : 'Save All PDF Boilerplate' }}
                            </button>
                        </div>
                    </div>
                </section>

                <section v-if="activeTab === 'customers'" class="mt-6 grid gap-6 lg:grid-cols-[420px_1fr]">
                    <div class="space-y-4">
                        <form class="bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="saveCustomer">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    {{ editingCustomerId ? `Editing customer #${editingCustomerId}` : 'New Customer' }}
                                </h3>
                                <button v-if="editingCustomerId" type="button" class="text-xs text-gray-500 hover:text-gray-900" @click="cancelCustomerEdit">Cancel</button>
                            </div>
                            <div class="mt-4 grid gap-3">
                                <input v-model="customerForm.name" required placeholder="Customer name" class="rounded-md border-gray-300" />
                                <input v-model="customerForm.attention_to" placeholder="Attention to" class="rounded-md border-gray-300" />
                                <input v-model="customerForm.email" type="email" placeholder="Email" class="rounded-md border-gray-300" />
                                <input v-model="customerForm.phone" placeholder="Phone" class="rounded-md border-gray-300" />
                                <textarea v-model="customerForm.address" rows="3" placeholder="Address" class="rounded-md border-gray-300" />
                                <div class="grid grid-cols-2 gap-3">
                                    <input v-model="customerForm.tax_identifier" placeholder="TIN" class="rounded-md border-gray-300" />
                                    <input v-model="customerForm.brn_registration_number" placeholder="BRN" class="rounded-md border-gray-300" />
                                </div>
                            </div>
                            <button type="submit" class="mt-4 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy">
                                {{ editingCustomerId ? 'Update Customer' : 'Add Customer' }}
                            </button>
                        </form>

                        <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Import CSV</h4>
                            <div class="mt-2 flex flex-col gap-2">
                                <input type="file" accept=".csv,.txt" @change="customerImport.file = $event.target.files[0]" class="text-xs">
                                <div class="flex items-center gap-2">
                                    <button type="button" :disabled="!customerImport.file || customerImport.busy"
                                            class="rounded-md bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-40"
                                            @click="importMasterData('customers')">
                                        {{ customerImport.busy ? 'Uploading...' : 'Upload CSV' }}
                                    </button>
                                    <a href="/api/customers/import/template" class="text-xs text-indigo-700 hover:underline">Download template</a>
                                </div>
                                <div v-if="customerImport.result" class="rounded-md bg-gray-50 p-2 text-xs">
                                    <div v-if="customerImport.result.error" class="text-red-700">{{ customerImport.result.error }}</div>
                                    <template v-else>
                                        <div class="font-semibold">{{ customerImport.result.inserted }} inserted, {{ customerImport.result.skipped }} skipped</div>
                                        <div v-if="customerImport.result.errors?.length" class="mt-1 space-y-0.5 text-red-700">
                                            <div v-for="(err, i) in customerImport.result.errors" :key="i">Row {{ err.row }}: {{ err.message }}</div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b px-5 py-3 text-sm font-semibold text-gray-900">Customers ({{ customerRows.length }})</div>
                        <div class="divide-y">
                            <div v-for="customer in customerRows" :key="customer.id"
                                 class="grid items-center gap-2 px-5 py-3 text-sm md:grid-cols-[1fr_120px_180px_160px_auto]"
                                 :class="{ 'bg-indigo-50/60': editingCustomerId === customer.id }">
                                <div class="font-medium text-gray-900 cursor-pointer hover:text-indigo-700" @click="openCustomerEdit(customer)">{{ customer.name }}</div>
                                <div>{{ customer.phone || '-' }}</div>
                                <div class="truncate">{{ customer.email || '-' }}</div>
                                <div class="truncate text-gray-500">{{ customer.tax_identifier || customer.brn_registration_number || '-' }}</div>
                                <div class="flex shrink-0 gap-1">
                                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs" @click="openCustomerEdit(customer)">Edit</button>
                                    <button type="button" class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-700" @click="deleteCustomer(customer)">Delete</button>
                                </div>
                            </div>
                            <div v-if="customerRows.length === 0" class="px-5 py-6 text-center text-sm text-gray-500">No customers yet.</div>
                        </div>
                    </div>
                </section>

                <section v-if="activeTab === 'products'" class="mt-6 grid gap-6 lg:grid-cols-[420px_1fr]">
                    <div class="space-y-4">
                        <form class="bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="saveProduct">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    {{ editingProductId ? `Editing product #${editingProductId}` : 'New Product' }}
                                </h3>
                                <button v-if="editingProductId" type="button" class="text-xs text-gray-500 hover:text-gray-900" @click="cancelProductEdit">Cancel</button>
                            </div>
                            <div class="mt-4 grid gap-3">
                                <input v-model="productForm.name" required placeholder="Product name" class="rounded-md border-gray-300" />
                                <input v-model="productForm.sku" placeholder="SKU" class="rounded-md border-gray-300" />
                                <textarea v-model="productForm.description" rows="3" placeholder="Description" class="rounded-md border-gray-300" />
                                <div class="grid grid-cols-2 gap-3">
                                    <input v-model.number="productForm.default_price" type="number" step="0.01" min="0" placeholder="Default price" class="rounded-md border-gray-300" />
                                    <input v-model="productForm.uom" placeholder="UOM" class="rounded-md border-gray-300" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <input v-model="productForm.tax_type" placeholder="Tax type" class="rounded-md border-gray-300" />
                                    <input v-model.number="productForm.tax_rate" type="number" step="0.01" min="0" placeholder="Tax rate" class="rounded-md border-gray-300" />
                                </div>
                                <input v-model="productForm.classification_code" placeholder="Classification code" class="rounded-md border-gray-300" />
                            </div>
                            <button type="submit" class="mt-4 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy">
                                {{ editingProductId ? 'Update Product' : 'Add Product' }}
                            </button>
                        </form>

                        <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Import CSV</h4>
                            <div class="mt-2 flex flex-col gap-2">
                                <input type="file" accept=".csv,.txt" @change="productImport.file = $event.target.files[0]" class="text-xs">
                                <div class="flex items-center gap-2">
                                    <button type="button" :disabled="!productImport.file || productImport.busy"
                                            class="rounded-md bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-40"
                                            @click="importMasterData('products')">
                                        {{ productImport.busy ? 'Uploading...' : 'Upload CSV' }}
                                    </button>
                                    <a href="/api/products/import/template" class="text-xs text-indigo-700 hover:underline">Download template</a>
                                </div>
                                <div v-if="productImport.result" class="rounded-md bg-gray-50 p-2 text-xs">
                                    <div v-if="productImport.result.error" class="text-red-700">{{ productImport.result.error }}</div>
                                    <template v-else>
                                        <div class="font-semibold">{{ productImport.result.inserted }} inserted, {{ productImport.result.skipped }} skipped</div>
                                        <div v-if="productImport.result.errors?.length" class="mt-1 space-y-0.5 text-red-700">
                                            <div v-for="(err, i) in productImport.result.errors" :key="i">Row {{ err.row }}: {{ err.message }}</div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b px-5 py-3 text-sm font-semibold text-gray-900">Products ({{ productRows.length }})</div>
                        <div class="divide-y">
                            <div v-for="product in productRows" :key="product.id"
                                 class="grid items-center gap-2 px-5 py-3 text-sm md:grid-cols-[1fr_100px_80px_100px_auto]"
                                 :class="{ 'bg-indigo-50/60': editingProductId === product.id }">
                                <div class="font-medium text-gray-900 cursor-pointer hover:text-indigo-700" @click="openProductEdit(product)">{{ product.name }}</div>
                                <div class="font-mono text-xs">{{ product.sku || '-' }}</div>
                                <div>{{ product.uom || 'unit' }}</div>
                                <div class="text-right font-mono">{{ Number(product.default_price || 0).toFixed(2) }}</div>
                                <div class="flex shrink-0 gap-1">
                                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs" @click="openProductEdit(product)">Edit</button>
                                    <button type="button" class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs text-red-700" @click="deleteProduct(product)">Delete</button>
                                </div>
                            </div>
                            <div v-if="productRows.length === 0" class="px-5 py-6 text-center text-sm text-gray-500">No products yet.</div>
                        </div>
                    </div>
                </section>

                <section v-if="activeTab === 'templates'" class="mt-6 space-y-6">
                    <form class="grid gap-3 bg-white p-6 shadow-sm sm:rounded-lg md:grid-cols-4" @submit.prevent="createTemplate">
                        <input v-model="templateForm.name" required placeholder="Template name" class="rounded-md border-gray-300" />
                        <select v-model="templateForm.document_type" class="rounded-md border-gray-300">
                            <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                        <select v-model="templateForm.paper_size" class="rounded-md border-gray-300">
                            <option>A4</option>
                            <option>60mm</option>
                        </select>
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy">
                            Add Template
                        </button>
                    </form>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div v-for="template in templateRows" :key="template.id" class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="grid gap-3 md:grid-cols-2">
                                <input v-model="template.name" class="rounded-md border-gray-300 text-sm" />
                                <select v-model="template.document_type" class="rounded-md border-gray-300 text-sm">
                                    <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                                <select v-model="template.paper_size" class="rounded-md border-gray-300 text-sm">
                                    <option>A4</option>
                                    <option>60mm</option>
                                </select>
                                <input v-model="template.amount_in_words_label" placeholder="Amount words label" class="rounded-md border-gray-300 text-sm" />
                                <input v-model="template.amount_in_words_position" placeholder="Amount words position" class="rounded-md border-gray-300 text-sm" />
                                <select v-model="template.amount_in_words_zero_sen_style" class="rounded-md border-gray-300 text-sm">
                                    <option>SAHAJA</option>
                                    <option>DAN SIFAR SEN</option>
                                </select>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-700">
                                <label class="inline-flex items-center gap-2">
                                    <input v-model="template.is_default" type="checkbox" class="rounded border-gray-300" />
                                    Default
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input v-model="template.show_amount_in_words" type="checkbox" class="rounded border-gray-300" />
                                    Amount in words
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input v-model="template.is_active" type="checkbox" class="rounded border-gray-300" />
                                    Active
                                </label>
                                <button type="button" class="ml-auto rounded-md border border-gray-300 px-3 py-1.5 font-medium" :disabled="busy" @click="saveTemplate(template)">
                                    Save
                                </button>
                                <button type="button" class="rounded-md border border-red-300 bg-red-50 px-3 py-1.5 font-medium text-red-700" :disabled="busy" @click="deleteTemplate(template)">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="activeTab === 'numbering'" class="mt-6 space-y-6">
                    <form class="bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="createNumbering">
                        <div class="grid gap-3 md:grid-cols-7">
                            <select v-model="numberingForm.document_type" class="rounded-md border-gray-300 text-sm">
                                <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                            </select>
                            <input v-model="numberingForm.prefix" placeholder="Prefix" class="rounded-md border-gray-300 text-sm" />
                            <input v-model="numberingForm.year_token" placeholder="{YYYY}" class="rounded-md border-gray-300 text-sm" />
                            <input v-model.number="numberingForm.sequence_padding" type="number" min="1" max="12" class="rounded-md border-gray-300 text-sm" />
                            <input v-model="numberingForm.separator" placeholder="-" class="rounded-md border-gray-300 text-sm" />
                            <input v-model="numberingForm.suffix" placeholder="Suffix" class="rounded-md border-gray-300 text-sm" />
                            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy">
                                Add
                            </button>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">
                            Preview only, never reserved: {{ numberingPreview }}
                        </p>
                    </form>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div v-for="policy in numberingRows" :key="policy.id" class="bg-white p-5 shadow-sm sm:rounded-lg">
                            <div class="grid gap-3 md:grid-cols-3">
                                <select v-model="policy.document_type" class="rounded-md border-gray-300 text-sm">
                                    <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                                </select>
                                <input v-model="policy.prefix" placeholder="Prefix" class="rounded-md border-gray-300 text-sm" />
                                <input v-model="policy.suffix" placeholder="Suffix" class="rounded-md border-gray-300 text-sm" />
                                <input v-model="policy.separator" placeholder="Separator" class="rounded-md border-gray-300 text-sm" />
                                <input v-model="policy.year_token" placeholder="{YYYY}" class="rounded-md border-gray-300 text-sm" />
                                <input v-model.number="policy.sequence_padding" type="number" min="1" max="12" class="rounded-md border-gray-300 text-sm" />
                            </div>
                            <div class="mt-4 flex items-center gap-4 text-sm text-gray-700">
                                <label class="inline-flex items-center gap-2">
                                    <input v-model="policy.is_active" type="checkbox" class="rounded border-gray-300" />
                                    Active
                                </label>
                                <span class="text-xs text-gray-500">Preview only; official number is issued-time only.</span>
                                <button type="button" class="ml-auto rounded-md border border-gray-300 px-3 py-1.5 font-medium" :disabled="busy" @click="saveNumbering(policy)">
                                    Save
                                </button>
                                <button type="button" class="rounded-md border border-red-300 bg-red-50 px-3 py-1.5 font-medium text-red-700" :disabled="busy" @click="deleteNumbering(policy)">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
