<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);

const page = usePage();
const isSuperAdmin = computed(() => page.props?.auth?.user?.role === 'super_admin');
const activeCompany = computed(() => page.props?.auth?.active_company || null);
const availableCompanies = computed(() => page.props?.auth?.available_companies || []);

function switchCompany(companyId) {
    router.post(route('active-company.switch'), { company_id: companyId }, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav
                class="border-b border-gray-100 bg-white"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    :href="route('documents.index')"
                                    :active="route().current('documents.*')"
                                >
                                    Documents
                                </NavLink>
                                <NavLink
                                    :href="route('payments.index')"
                                    :active="route().current('payments.*')"
                                >
                                    Payments
                                </NavLink>
                                <NavLink
                                    :href="route('master-data.index')"
                                    :active="route().current('master-data.*')"
                                >
                                    Master Data
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Company switcher (super admin) or read-only badge -->
                            <div v-if="activeCompany" class="relative me-3">
                                <Dropdown v-if="isSuperAdmin && availableCompanies.length > 1" align="right" width="56">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-900 transition hover:bg-indigo-100 focus:outline-none"
                                        >
                                            <span class="me-1 text-[10px] uppercase tracking-wide text-indigo-500">Company</span>
                                            {{ activeCompany.code }} &middot; {{ activeCompany.name }}
                                            <svg class="-me-0.5 ms-2 h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="border-b border-gray-100 px-3 py-2 text-xs font-semibold text-gray-500">
                                            Switch active company
                                        </div>
                                        <button
                                            v-for="company in availableCompanies"
                                            :key="company.id"
                                            @click="switchCompany(company.id)"
                                            class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50"
                                            :class="{ 'bg-indigo-50 font-semibold text-indigo-900': company.id === activeCompany.id }"
                                        >
                                            <div class="font-mono text-xs text-gray-500">{{ company.code }}</div>
                                            <div>{{ company.name }}</div>
                                        </button>
                                    </template>
                                </Dropdown>
                                <span v-else class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700">
                                    <span class="me-1 text-[10px] uppercase tracking-wide text-gray-400">Company</span>
                                    {{ activeCompany.code }} &middot; {{ activeCompany.name }}
                                </span>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('documents.index')"
                            :active="route().current('documents.*')"
                        >
                            Documents
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('payments.index')"
                            :active="route().current('payments.*')"
                        >
                            Payments
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('master-data.index')"
                            :active="route().current('master-data.*')"
                        >
                            Master Data
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-white shadow"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
