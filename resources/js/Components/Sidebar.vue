<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    isCollapsed: {
        type: Boolean,
        default: false
    }
});

const isMobile = ref(false);

// Check for mobile viewport
const checkMobile = () => {
    isMobile.value = window.innerWidth < 768;
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
});

const { auth } = usePage().props;

// Role-based access control
const userRole = computed(() => auth?.user?.role_id || 0);
const isAdmin = computed(() => userRole.value === 1);
const isWarehouse = computed(() => userRole.value === 2);
const isCashier = computed(() => userRole.value === 3);

const navigation = computed(() => {
    return [
        { 
            name: 'Dashboard', 
            route: 'dashboard',
            icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'
        },
        {
            name: 'POS',
            route: 'pos',
            icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'
        },
        {
            name: 'Branch',
            route: 'about',
            icon: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'
        }
    ];
});

const currentRoute = computed(() => route().current());

// Check if current route matches navigation item
const isCurrentRoute = (routeName) => {
    return route().current(routeName);
};
</script>

<template>
    <aside
        class="fixed top-16 left-0 bottom-0 z-30 transition-all duration-300 transform"
        :class="[
            {
                'w-20': isCollapsed && !isMobile,
                'w-64': !isCollapsed || isMobile,
                '-translate-x-full': isMobile && isCollapsed,
                'translate-x-0': !isMobile || !isCollapsed
            },
            'bg-white dark:bg-gray-800',
            'border-r border-gray-200 dark:border-gray-700'
        ]"
    >
        <div class="flex flex-col h-full bg-white dark:bg-gray-800">
            <!-- Logo -->
            <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <Link :href="route('dashboard')" class="flex items-center">
                    <svg
                        class="h-8 w-8 text-gray-800 dark:text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                        />
                    </svg>
                    <span v-if="!isCollapsed || isMobile" class="ml-2 text-xl font-semibold text-gray-800 dark:text-white">HOYAH</span>
                </Link>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4 bg-white dark:bg-gray-800">
                <div class="px-3 space-y-1">
                    <Link
                        v-for="item in navigation"
                        :key="item.name"
                        :href="route(item.route)"
                        class="group flex items-center px-2 py-2 text-base font-medium rounded-md"
                        :class="[
                            isCurrentRoute(item.route)
                                ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'
                        ]"
                    >
                        <svg
                            class="mr-4 h-6 w-6"
                            :class="[
                                isCurrentRoute(item.route)
                                    ? 'text-gray-500 dark:text-gray-300'
                                    : 'text-gray-400 dark:text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300'
                            ]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                :d="item.icon"
                            />
                        </svg>
                        <span v-if="!isCollapsed || isMobile">{{ item.name }}</span>
                    </Link>
                </div>
            </nav>
        </div>

        <!-- Mobile Overlay -->
        <div
            v-if="isMobile && !isCollapsed"
            class="fixed inset-0 bg-gray-600 bg-opacity-50 transition-opacity md:hidden"
            style="z-index: -1;"
        ></div>
    </aside>
</template> 