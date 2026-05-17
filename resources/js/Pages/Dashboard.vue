<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    currentCompany: Object,
    stats: Object,
    recentDocuments: Array,
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
                <p class="mt-1 text-sm text-gray-500">{{ currentCompany?.name }}</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-4">
                    <div
                        v-for="(value, label) in stats"
                        :key="label"
                        class="bg-white p-5 shadow-sm sm:rounded-lg"
                    >
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ label }}</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900">{{ value }}</div>
                    </div>
                </div>

                <div class="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h3 class="text-sm font-semibold text-gray-900">Recent Documents</h3>
                        <Link :href="route('documents.index')" class="text-sm font-medium text-gray-700">
                            View all
                        </Link>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="document in recentDocuments"
                            :key="document.id"
                            class="grid grid-cols-5 gap-4 px-6 py-3 text-sm"
                        >
                            <div class="font-medium">{{ document.document_type }}</div>
                            <div>{{ document.official_number || `Draft #${document.id}` }}</div>
                            <div>{{ document.customer?.name || '-' }}</div>
                            <div>{{ document.status }}</div>
                            <div class="text-right">
                                {{ document.currency }} {{ Number(document.grand_total).toFixed(2) }}
                            </div>
                        </div>
                        <div
                            v-if="recentDocuments.length === 0"
                            class="px-6 py-8 text-center text-sm text-gray-500"
                        >
                            No documents yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
