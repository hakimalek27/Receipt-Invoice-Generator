<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Master Data" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Master Data
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-4 px-4 sm:px-6 lg:grid-cols-4 lg:px-8">
                <div
                    v-for="item in ['Company', 'Customers', 'Products', 'Templates', 'Numbering']"
                    :key="item"
                    class="bg-white p-5 shadow-sm sm:rounded-lg"
                >
                    <div class="text-sm font-semibold text-gray-900">{{ item }}</div>
                    <div class="mt-2 text-sm text-gray-500">
                        Managed through the secured API surface.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
