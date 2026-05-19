<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { money } from '@/lib/api';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    documents: Object,
    filters: Object,
    documentTypes: Array,
});

const filter = reactive({
    type: props.filters?.type ?? '',
    status: props.filters?.status ?? '',
    search: props.filters?.search ?? '',
});

function applyFilters() {
    router.get(route('documents.index'), filter, {
        preserveState: true,
        replace: true,
    });
}

function statusBadgeClass(status) {
    return {
        draft: 'bg-amber-100 text-amber-800',
        issued: 'bg-emerald-100 text-emerald-800',
        converted: 'bg-indigo-100 text-indigo-800',
        void: 'bg-red-100 text-red-800',
        cancelled: 'bg-gray-200 text-gray-700',
    }[status] || 'bg-gray-100 text-gray-700';
}

function chainTooltip(document) {
    const parts = [];
    if (document.converted_from) {
        parts.push(`From ${document.converted_from.document_type} ${document.converted_from.official_number || '#' + document.converted_from.id}`);
    }
    if (document.converted_to?.length) {
        parts.push(`Converted to: ${document.converted_to.map((c) => c.document_type + ' ' + (c.official_number || '#' + c.id)).join(', ')}`);
    }
    return parts.join(' · ');
}
</script>

<template>
    <Head title="Documents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Documents</h2>
                    <p class="mt-1 text-sm text-gray-500">Drafts, issued documents, PDF versions, and conversions.</p>
                </div>
                <Link :href="route('documents.create')" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">
                    New Draft
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="grid gap-3 md:grid-cols-[1fr_180px_180px_auto]">
                        <input v-model="filter.search" class="rounded-md border-gray-300 text-sm" placeholder="Search number, type, customer">
                        <select v-model="filter.type" class="rounded-md border-gray-300 text-sm">
                            <option value="">All types</option>
                            <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                        <select v-model="filter.status" class="rounded-md border-gray-300 text-sm">
                            <option value="">All statuses</option>
                            <option value="draft">draft</option>
                            <option value="issued">issued</option>
                            <option value="converted">converted</option>
                            <option value="void">void</option>
                            <option value="cancelled">cancelled</option>
                        </select>
                        <button class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700" @click="applyFilters">
                            Filter
                        </button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Number</th>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="document in documents.data" :key="document.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">
                                    {{ document.document_type }}
                                    <span v-if="document.converted_from || document.converted_to?.length"
                                          class="ml-1 inline-block rounded bg-indigo-50 px-1 text-[10px] font-medium text-indigo-700"
                                          :title="chainTooltip(document)">⇌</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ document.official_number || `Draft #${document.id}` }}</td>
                                <td class="px-4 py-3">{{ document.customer?.name || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(document.status)">{{ document.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ document.document_date?.slice(0, 10) || '-' }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ money(document.grand_total, document.currency) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('documents.edit', document.id)" class="font-medium text-indigo-700 hover:underline">Open</Link>
                                </td>
                            </tr>
                            <tr v-if="documents.data.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">No documents found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
