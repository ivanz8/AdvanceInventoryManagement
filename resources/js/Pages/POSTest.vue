<script setup>
import { ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'vue-toastification';

const toast = useToast();

// Get initial props from the backend
const { branches } = usePage().props;

// Reactive references
const selectedBranch = ref(null);

// Debug function
const debugInfo = () => {
    console.log('Selected Branch:', selectedBranch.value);
    console.log('All Branches:', branches);
    
    if (selectedBranch.value) {
        const branch = branches.find(b => b.id === selectedBranch.value);
        console.log('Selected Branch Details:', branch);
        console.log('Products in Selected Branch:', branch?.products);
    }
};

const selectBranch = (branchId) => {
    selectedBranch.value = branchId;
    const branchName = branches.find(b => b.id === branchId)?.name;
    toast.info(`Selected branch: ${branchName}`);
    console.log('Branch selected:', branchId);
    console.log('Branch products count:', branches.find(b => b.id === branchId)?.products?.length || 0);
};
</script>

<template>
    <Head title="POS Test" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Debug Button -->
                        <div class="mb-4">
                            <button 
                                @click="debugInfo" 
                                class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-sm"
                            >
                                Debug Info
                            </button>
                        </div>
                        
                        <!-- Branch Selection -->
                        <div v-if="!selectedBranch" class="mb-8">
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Select a Branch</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Choose a branch to start selling products.
                                </p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    @click="selectBranch(branch.id)"
                                    class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer border-2 border-transparent hover:border-indigo-500"
                                >
                                    <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                                        {{ branch.name }}
                                    </h4>
                                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                                        {{ branch.location }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-2">
                                        {{ branch.products?.length || 0 }} Products
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Branch Details -->
                        <div v-else>
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    {{ branches.find(b => b.id === selectedBranch)?.name }}
                                </h3>
                                <button
                                    @click="selectedBranch = null"
                                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-indigo-500 dark:hover:text-indigo-400"
                                >
                                    Change Branch
                                </button>
                            </div>
                            
                            <!-- Products List -->
                            <div class="mt-6">
                                <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">Products</h4>
                                
                                <div v-if="branches.find(b => b.id === selectedBranch)?.products?.length === 0" class="text-center py-8">
                                    <p class="text-gray-500 dark:text-gray-400">No products available in this branch</p>
                                </div>
                                
                                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    <div
                                        v-for="product in branches.find(b => b.id === selectedBranch)?.products"
                                        :key="product.id"
                                        class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-4"
                                    >
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ product.name }}</h3>
                                        <p class="text-gray-600 dark:text-gray-300">${{ product.price?.toFixed(2) }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Stock: {{ product.stock_quantity }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template> 