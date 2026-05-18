<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { apiFetch, money, today } from '@/lib/api';
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    payments: Object,
    receivableDocuments: Array,
});

const form = reactive({
    payment_date: today(),
    amount: 0,
    currency: 'MYR',
    method: 'bank_transfer',
    reference_number: '',
    notes: '',
    create_official_receipt: true,
    document_id: '',
    allocation_amount: 0,
});

const busy = ref(false);
const message = ref('');
const error = ref('');
const localPayments = ref([...props.payments.data]);

const selectedDocument = computed(() => props.receivableDocuments.find((document) => String(document.id) === String(form.document_id)));

function useDocumentTotal() {
    if (!selectedDocument.value) return;
    const outstanding = Number(selectedDocument.value.outstanding_amount || selectedDocument.value.grand_total || 0);
    form.amount = outstanding;
    form.allocation_amount = outstanding;
    form.currency = selectedDocument.value.currency || 'MYR';
}

async function recordPayment() {
    busy.value = true;
    error.value = '';
    message.value = '';
    try {
        const payload = {
            payment_date: form.payment_date,
            amount: form.amount,
            currency: form.currency,
            method: form.method,
            reference_number: form.reference_number || null,
            notes: form.notes || null,
            create_official_receipt: form.create_official_receipt,
            allocations: form.document_id
                ? [{ document_id: form.document_id, amount: form.allocation_amount || form.amount }]
                : [],
        };
        const payment = await apiFetch('/api/payments', { method: 'POST', body: JSON.stringify(payload) });
        localPayments.value.unshift(payment);
        message.value = payment.receipt_document
            ? `Payment recorded and receipt ${payment.receipt_document.official_number} issued.`
            : 'Payment recorded.';
    } catch (exception) {
        error.value = exception.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head title="Payments" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Payments</h2>
                <p class="mt-1 text-sm text-gray-500">Record payments, allocate invoices, and generate official receipts.</p>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 lg:grid-cols-[420px_1fr] lg:px-8">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">New Payment</h3>
                    <div v-if="message" class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ message }}</div>
                    <div v-if="error" class="mt-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ error }}</div>

                    <div class="mt-4 space-y-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Allocate To
                            <select v-model="form.document_id" class="mt-1 w-full rounded-md border-gray-300 text-sm" @change="useDocumentTotal">
                                <option value="">Unallocated payment</option>
                                <option v-for="document in receivableDocuments" :key="document.id" :value="document.id">
                                    {{ document.official_number }} · {{ document.customer?.name || '-' }} · {{ money(document.grand_total, document.currency) }}
                                    · outstanding {{ money(document.outstanding_amount, document.currency) }}
                                </option>
                            </select>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block text-sm font-medium text-gray-700">
                                Date
                                <input v-model="form.payment_date" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">
                                Currency
                                <input v-model="form.currency" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block text-sm font-medium text-gray-700">
                                Payment Amount
                                <input v-model.number="form.amount" type="number" step="0.01" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">
                                Allocation
                                <input v-model.number="form.allocation_amount" type="number" step="0.01" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            </label>
                        </div>
                        <label class="block text-sm font-medium text-gray-700">
                            Method
                            <select v-model="form.method" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-gray-700">
                            Reference
                            <input v-model="form.reference_number" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </label>
                        <label class="block text-sm font-medium text-gray-700">
                            Notes
                            <textarea v-model="form.notes" rows="3" class="mt-1 w-full rounded-md border-gray-300 text-sm"></textarea>
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input v-model="form.create_official_receipt" type="checkbox" class="rounded border-gray-300">
                            Generate official receipt
                        </label>
                        <button class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="recordPayment">
                            Record Payment
                        </button>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h3 class="text-sm font-semibold text-gray-900">Recent Payments</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3">Receipt</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="payment in localPayments" :key="payment.id">
                                <td class="px-4 py-3">{{ payment.payment_date?.slice(0, 10) }}</td>
                                <td class="px-4 py-3">{{ payment.reference_number || '-' }}</td>
                                <td class="px-4 py-3">
                                    <Link v-if="payment.receipt_document" :href="route('documents.edit', payment.receipt_document.id)" class="font-medium text-gray-900">
                                        {{ payment.receipt_document.official_number }}
                                    </Link>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-3 text-right">{{ money(payment.amount, payment.currency) }}</td>
                            </tr>
                            <tr v-if="localPayments.length === 0">
                                <td colspan="4" class="px-4 py-10 text-center text-gray-500">No payments yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
