<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    documents: Object,
});
</script>

<template>
    <Head title="Documents" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Documents
                </h2>
                <Link
                    :href="route('documents.create')"
                    class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white"
                >
                    New Draft
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead
                            class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            <tr>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Number</th>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="document in documents.data" :key="document.id">
                                <td class="px-4 py-3 font-medium">{{ document.document_type }}</td>
                                <td class="px-4 py-3">{{ document.official_number || `Draft #${document.id}` }}</td>
                                <td class="px-4 py-3">{{ document.customer?.name || '-' }}</td>
                                <td class="px-4 py-3">{{ document.status }}</td>
                                <td class="px-4 py-3 text-right">
                                    {{ document.currency }} {{ Number(document.grand_total).toFixed(2) }}
                                </td>
                            </tr>
                            <tr v-if="documents.data.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    No documents yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
