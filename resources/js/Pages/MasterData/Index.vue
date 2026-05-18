<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { apiFetch } from '@/lib/api';
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    company: Object,
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
    ['customers', 'Customers'],
    ['products', 'Products'],
    ['templates', 'Templates'],
    ['numbering', 'Numbering'],
];

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

                <section v-if="activeTab === 'customers'" class="mt-6 grid gap-6 lg:grid-cols-[420px_1fr]">
                    <form class="bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="createCustomer">
                        <h3 class="text-sm font-semibold text-gray-900">New Customer</h3>
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
                            Add Customer
                        </button>
                    </form>
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b px-5 py-3 text-sm font-semibold text-gray-900">Customers</div>
                        <div class="divide-y">
                            <div v-for="customer in customerRows" :key="customer.id" class="grid gap-2 px-5 py-3 text-sm md:grid-cols-4">
                                <div class="font-medium text-gray-900">{{ customer.name }}</div>
                                <div>{{ customer.phone || '-' }}</div>
                                <div>{{ customer.email || '-' }}</div>
                                <div class="text-gray-500">{{ customer.tax_identifier || customer.brn_registration_number || '-' }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="activeTab === 'products'" class="mt-6 grid gap-6 lg:grid-cols-[420px_1fr]">
                    <form class="bg-white p-6 shadow-sm sm:rounded-lg" @submit.prevent="createProduct">
                        <h3 class="text-sm font-semibold text-gray-900">New Product</h3>
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
                            Add Product
                        </button>
                    </form>
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div class="border-b px-5 py-3 text-sm font-semibold text-gray-900">Products</div>
                        <div class="divide-y">
                            <div v-for="product in productRows" :key="product.id" class="grid gap-2 px-5 py-3 text-sm md:grid-cols-4">
                                <div class="font-medium text-gray-900">{{ product.name }}</div>
                                <div>{{ product.sku || '-' }}</div>
                                <div>{{ product.uom || 'unit' }}</div>
                                <div class="text-right">{{ Number(product.default_price || 0).toFixed(2) }}</div>
                            </div>
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
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
