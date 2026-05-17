<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    document: Object,
});
</script>

<template>
    <Head title="Document Editor" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Document Editor
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Company</label>
                            <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                Current company
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Document Type</label>
                            <select class="mt-1 w-full rounded border-gray-300 text-sm">
                                <option>invoice</option>
                                <option>quotation</option>
                                <option>official_receipt</option>
                                <option>delivery_order</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Status</label>
                            <div class="mt-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                draft
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 rounded border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                        Draft line editor, artwork upload, preview, and issue confirmation controls will bind to the secured API surface.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
