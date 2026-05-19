<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    currentCompany: Object,
    stats: Object,
    recentDocuments: Array,
    allCompanyStats: { type: Array, default: () => [] },
    onboarding: { type: Object, default: () => ({ complete: true, missing: [], first_tab: null }) },
});

function switchToCompany(companyId) {
    router.post(route('active-company.switch'), { company_id: companyId }, {
        preserveScroll: true,
    });
}
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
                <div v-if="!onboarding.complete" class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">⚠</span>
                                <h3 class="text-sm font-semibold text-amber-900">Profil syarikat anda belum lengkap</h3>
                            </div>
                            <p class="mt-1 text-xs text-amber-800">
                                PDF anda akan kelihatan tidak professional sehingga semua field disempurnakan.
                            </p>
                            <ul class="mt-2 grid grid-cols-1 gap-1 text-xs text-amber-900 sm:grid-cols-2">
                                <li v-for="(item, idx) in onboarding.missing" :key="idx" class="flex items-center gap-1">
                                    <span class="text-amber-700">•</span> {{ item.label }}
                                </li>
                            </ul>
                        </div>
                        <Link :href="route('master-data.index') + (onboarding.first_tab ? `?tab=${onboarding.first_tab}` : '')"
                              class="shrink-0 rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">
                            Lengkapkan sekarang →
                        </Link>
                    </div>
                </div>

                <div class="mb-6 grid gap-3 md:grid-cols-4">
                    <Link
                        :href="route('documents.create')"
                        class="rounded-lg bg-gray-900 px-4 py-3 text-sm font-semibold text-white shadow-sm"
                    >
                        New Document
                    </Link>
                    <Link
                        :href="route('payments.index')"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-800 shadow-sm"
                    >
                        Record Payment
                    </Link>
                    <Link
                        :href="route('documents.index', { status: 'draft' })"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-800 shadow-sm"
                    >
                        Review Drafts
                    </Link>
                    <Link
                        :href="route('master-data.index')"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-800 shadow-sm"
                    >
                        Master Data
                    </Link>
                </div>

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

                <div v-if="allCompanyStats.length > 0" class="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h3 class="text-sm font-semibold text-gray-900">All Companies (super admin view)</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <button
                            v-for="company in allCompanyStats"
                            :key="company.id"
                            type="button"
                            class="grid w-full grid-cols-[1fr_auto_auto_auto] items-baseline gap-4 px-6 py-3 text-left text-sm hover:bg-indigo-50"
                            :class="{ 'bg-indigo-50/60': company.id === currentCompany?.id }"
                            @click="switchToCompany(company.id)"
                        >
                            <div>
                                <span class="font-mono text-xs text-gray-500">{{ company.code }}</span>
                                <span class="ml-2 font-medium text-gray-900">{{ company.name }}</span>
                                <span v-if="company.id === currentCompany?.id" class="ml-2 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-800">ACTIVE</span>
                            </div>
                            <div class="text-xs text-gray-500"><span class="font-semibold text-gray-700">{{ company.documents }}</span> docs</div>
                            <div class="text-xs text-amber-700">{{ company.drafts }} drafts</div>
                            <div class="text-xs text-emerald-700">{{ company.issued }} issued</div>
                        </button>
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
                            <div class="font-medium">
                                <Link :href="route('documents.edit', document.id)" class="text-gray-900 underline-offset-2 hover:underline">
                                    {{ document.document_type }}
                                </Link>
                            </div>
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
