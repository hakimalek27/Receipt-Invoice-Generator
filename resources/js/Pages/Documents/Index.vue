<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { apiFetch, money } from '@/lib/api';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    documents: Object,
    filters: Object,
    documentTypes: Array,
});

const filter = reactive({
    type: props.filters?.type ?? '',
    status: props.filters?.status ?? '',
    search: props.filters?.search ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
});

const activeRange = ref(detectActiveRange());
const showCustomDate = computed(() => activeRange.value === 'custom');

function detectActiveRange() {
    if (!filter.date_from && !filter.date_to) return '';
    const today = isoDate(new Date());
    if (filter.date_to !== today) return 'custom';
    const from = filter.date_from;
    if (from === today) return 'today';
    if (from === isoDate(startOfWeek())) return 'week';
    if (from === isoDate(startOfMonth())) return 'month';
    if (from === isoDate(daysAgo(30))) return '30d';
    return 'custom';
}

function isoDate(d) {
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function startOfWeek() {
    const d = new Date();
    const day = d.getDay() || 7; // Sunday = 0 → 7 (ISO Monday-first)
    if (day !== 1) d.setDate(d.getDate() - (day - 1));
    return d;
}

function startOfMonth() {
    const d = new Date();
    d.setDate(1);
    return d;
}

function daysAgo(n) {
    const d = new Date();
    d.setDate(d.getDate() - n);
    return d;
}

function setRange(key) {
    activeRange.value = key;
    const today = isoDate(new Date());
    if (key === 'today') {
        filter.date_from = today;
        filter.date_to = today;
    } else if (key === 'week') {
        filter.date_from = isoDate(startOfWeek());
        filter.date_to = today;
    } else if (key === 'month') {
        filter.date_from = isoDate(startOfMonth());
        filter.date_to = today;
    } else if (key === '30d') {
        filter.date_from = isoDate(daysAgo(30));
        filter.date_to = today;
    } else if (key === 'clear') {
        filter.date_from = '';
        filter.date_to = '';
        activeRange.value = '';
    }
    if (key !== 'custom') applyFilters();
}

const selectedIds = ref(new Set());
const busy = ref(false);
const message = ref('');

// All non-terminal-locked rows are selectable for bulk delete. Backend's
// bulkDelete will skip any with derived children and report them in the
// `blocked` array.
const SELECTABLE_STATUSES = ['draft', 'issued', 'void', 'cancelled'];
const selectableRows = computed(() =>
    props.documents?.data?.filter((d) => SELECTABLE_STATUSES.includes(d.status)) || []
);

function toggleSelect(id) {
    selectedIds.value.has(id) ? selectedIds.value.delete(id) : selectedIds.value.add(id);
    selectedIds.value = new Set(selectedIds.value);
}

function selectAllOnPage() {
    if (selectableRows.value.every((d) => selectedIds.value.has(d.id))) {
        selectableRows.value.forEach((d) => selectedIds.value.delete(d.id));
    } else {
        selectableRows.value.forEach((d) => selectedIds.value.add(d.id));
    }
    selectedIds.value = new Set(selectedIds.value);
}

async function bulkDelete() {
    const ids = Array.from(selectedIds.value);
    if (ids.length === 0) return;
    if (!window.confirm(`Permanently delete ${ids.length} document(s)? Any official numbers will be reusable. This cannot be undone.`)) return;
    busy.value = true;
    try {
        const result = await apiFetch('/api/documents/bulk-delete-drafts', {
            method: 'POST',
            body: JSON.stringify({ ids }),
        });
        selectedIds.value = new Set();
        const blocked = result.blocked || [];
        if (blocked.length > 0) {
            const summary = blocked.map((b) => `${b.official_number || `#${b.id}`}: ${b.reason}`).join('\n');
            message.value = `Deleted ${result.deleted_count}. Blocked ${blocked.length}: \n${summary}`;
        } else {
            message.value = `Deleted ${result.deleted_count} document(s).`;
        }
        router.reload({ only: ['documents'] });
    } catch (e) {
        alert(e.message);
    } finally {
        busy.value = false;
    }
}

async function deleteDocument(doc) {
    const isDraft = doc.status === 'draft';
    const numStr = doc.official_number || `Draft #${doc.id}`;
    const msg = isDraft
        ? 'Permanently delete this draft? This cannot be undone.'
        : `Delete ${doc.document_type} ${numStr}?\n\nThe number ${numStr} will be reusable for new documents. This cannot be undone.`;
    if (!window.confirm(msg)) return;
    busy.value = true;
    try {
        const result = await apiFetch(`/api/documents/${doc.id}`, { method: 'DELETE' });
        selectedIds.value.delete(doc.id);
        selectedIds.value = new Set(selectedIds.value);
        message.value = result.recycled_number
            ? `Deleted. Number ${result.recycled_number} is now available for reuse.`
            : 'Document deleted.';
        router.reload({ only: ['documents'] });
    } catch (e) {
        alert(e.message);
    } finally {
        busy.value = false;
    }
}

function applyFilters() {
    if (searchTimer) clearTimeout(searchTimer);
    router.get(route('documents.index'), filter, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

let searchTimer = null;
watch(() => filter.search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

async function duplicateDocument(docId) {
    if (!confirm('Duplicate this document as a new draft?')) return;
    try {
        const fresh = await apiFetch(`/api/documents/${docId}/duplicate`, { method: 'POST' });
        window.location.href = `/documents/${fresh.id}`;
    } catch (e) {
        alert(e.message);
    }
}

function statusBadgeClass(status) {
    return {
        draft: 'bg-amber-100 text-amber-800',
        issued: 'bg-emerald-100 text-emerald-800',
        void: 'bg-red-100 text-red-800',
        cancelled: 'bg-gray-200 text-gray-700',
    }[status] || 'bg-gray-100 text-gray-700';
}

function chainTooltip(document) {
    const parts = [];
    if (document.converted_from) {
        parts.push(`Derived from ${document.converted_from.document_type} ${document.converted_from.official_number || '#' + document.converted_from.id}`);
    }
    if (document.converted_to?.length) {
        parts.push(`Derived to: ${document.converted_to.map((c) => c.document_type + ' ' + (c.official_number || '#' + c.id)).join(', ')}`);
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
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase text-gray-500">Date:</span>
                        <button v-for="[key, label] in [['today','Today'],['week','This week'],['month','This month'],['30d','Last 30 days'],['custom','Custom']]"
                                :key="key"
                                @click="setRange(key)"
                                type="button"
                                class="rounded-full border px-3 py-1 text-xs"
                                :class="activeRange === key ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'">
                            {{ label }}
                        </button>
                        <button v-if="filter.date_from || filter.date_to" @click="setRange('clear')" type="button"
                                class="rounded-full border border-gray-300 bg-white px-3 py-1 text-xs text-gray-500 hover:text-red-700">
                            Clear
                        </button>
                    </div>
                    <div v-if="showCustomDate" class="flex flex-wrap items-center gap-2">
                        <span class="text-xs text-gray-500">From</span>
                        <input v-model="filter.date_from" type="date" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
                        <span class="text-xs text-gray-500">To</span>
                        <input v-model="filter.date_to" type="date" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
                    </div>
                    <div class="grid gap-3 md:grid-cols-[1fr_180px_180px_auto]">
                        <input v-model="filter.search" class="rounded-md border-gray-300 text-sm" placeholder="Search number, type, customer (auto)">
                        <select v-model="filter.type" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
                            <option value="">All types</option>
                            <option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                        <select v-model="filter.status" class="rounded-md border-gray-300 text-sm" @change="applyFilters">
                            <option value="">All statuses</option>
                            <option value="draft">draft</option>
                            <option value="issued">issued</option>
                            <option value="void">void</option>
                            <option value="cancelled">cancelled</option>
                        </select>
                        <button class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700" @click="applyFilters" title="Apply filters now (search auto-applies after 300ms)">
                            Apply now
                        </button>
                    </div>
                </div>

                <div v-if="selectedIds.size > 0" class="flex items-center justify-between rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm text-indigo-900">
                    <span>{{ selectedIds.size }} document(s) selected</span>
                    <div class="flex gap-2">
                        <button @click="selectedIds = new Set()" class="rounded-md border border-indigo-300 bg-white px-3 py-1 text-xs">Clear</button>
                        <button :disabled="busy" @click="bulkDelete" class="rounded-md bg-red-700 px-3 py-1 text-xs font-semibold text-white">Delete selected</button>
                    </div>
                </div>
                <div v-if="message" class="rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">{{ message }}</div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-2 py-3">
                                    <input type="checkbox"
                                           :disabled="selectableRows.length === 0"
                                           :checked="selectableRows.length > 0 && selectableRows.every((d) => selectedIds.has(d.id))"
                                           @change="selectAllOnPage"
                                           title="Select all (draft/issued/void/cancelled) on this page"
                                           class="rounded border-gray-300">
                                </th>
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
                            <tr v-for="document in documents.data" :key="document.id" class="hover:bg-gray-50" :class="{ 'bg-indigo-50/40': selectedIds.has(document.id) }">
                                <td class="px-2 py-3">
                                    <input v-if="SELECTABLE_STATUSES.includes(document.status)"
                                           type="checkbox"
                                           :checked="selectedIds.has(document.id)"
                                           @change="toggleSelect(document.id)"
                                           class="rounded border-gray-300">
                                </td>
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
                                    <div class="flex justify-end gap-2 text-xs">
                                        <Link :href="route('documents.edit', document.id)" class="font-medium text-indigo-700 hover:underline">Open</Link>
                                        <button type="button" class="font-medium text-gray-600 hover:text-indigo-700 hover:underline" @click="duplicateDocument(document.id)">Duplicate</button>
                                        <button
                                            v-if="['draft', 'issued', 'void', 'cancelled'].includes(document.status)"
                                            type="button"
                                            class="font-medium text-red-700 hover:underline disabled:opacity-50"
                                            :disabled="busy || (document.converted_to?.length > 0)"
                                            :title="document.converted_to?.length > 0
                                                ? `Has ${document.converted_to.length} derived doc(s) — delete those first`
                                                : 'Delete this document (number will be reusable)'"
                                            @click="deleteDocument(document)"
                                        >Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="documents.data.length === 0">
                                <td colspan="8" class="px-4 py-10 text-center text-gray-500">No documents found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
