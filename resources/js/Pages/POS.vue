<script setup>
import { ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';

const toast = useToast();

// Get initial props from the backend
const { branches } = usePage().props;

// Reactive references
const selectedBranch = ref(null);
const cart = ref([]);
const searchQuery = ref('');
const selectedCategory = ref('all');
const showPaymentModal = ref(false);
const selectedPaymentMethod = ref('cash');
const paymentAmount = ref(0);
const changeAmount = ref(0);
const isProcessing = ref(false);

// Debug function
const debugInfo = () => {
    console.log('Selected Branch:', selectedBranch.value);
    console.log('All Branches:', branches);
    
    if (selectedBranch.value) {
        const branch = branches.find(b => b.id === selectedBranch.value);
        console.log('Selected Branch Details:', branch);
        console.log('Products in Selected Branch:', branch?.products);
    }
    
    console.log('Filtered Products:', filteredProducts.value);
    console.log('Cart:', cart.value);
};

// Compute filtered products for selected branch
const filteredProducts = computed(() => {
    if (!selectedBranch.value) return [];
    
    const branch = branches.find(b => b.id === selectedBranch.value);
    if (!branch) return [];
    
    console.log('Selected branch:', branch);
    console.log('Branch products:', branch.products);

    const products = branch.products || [];
    
    // Ensure all products have numeric price values
    const productsWithNumericPrices = products.map(product => ({
        ...product,
        price: typeof product.price === 'number' ? product.price : parseFloat(product.price) || 0
    }));
    
    return productsWithNumericPrices.filter(product => {
        const matchesSearch = product.name.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesCategory = selectedCategory.value === 'all' || product.category?.name === selectedCategory.value;
        return matchesSearch && matchesCategory;
    });
});

// Get categories from products
const categories = computed(() => {
    if (!selectedBranch.value) return ['all'];
    
    const branch = branches.find(b => b.id === selectedBranch.value);
    if (!branch || !branch.products) return ['all'];
    
    const uniqueCategories = new Set(branch.products.map(p => p.category?.name || 'Uncategorized'));
    return ['all', ...uniqueCategories];
});

const selectBranch = (branchId) => {
    selectedBranch.value = branchId;
    // Clear cart when changing branches
    cart.value = [];
    const branchName = branches.find(b => b.id === branchId)?.name;
    toast.info(`Selected branch: ${branchName}`);
    console.log('Branch selected:', branchId);
    console.log('Branch products count:', branches.find(b => b.id === branchId)?.products?.length || 0);
};

const addToCart = (product) => {
    // Ensure price is a number
    const productWithNumericPrice = {
        ...product,
        price: typeof product.price === 'number' ? product.price : parseFloat(product.price) || 0
    };
    
    const existingItem = cart.value.find(item => item.id === productWithNumericPrice.id);
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.value.push({ ...productWithNumericPrice, quantity: 1 });
    }
    toast.success(`Added ${product.name} to cart`);
};

const removeFromCart = (productId) => {
    const index = cart.value.findIndex(item => item.id === productId);
    if (index > -1) {
        const item = cart.value[index];
        cart.value.splice(index, 1);
        toast.info(`Removed ${item.name} from cart`);
    }
};

const updateQuantity = (productId, newQuantity) => {
    const item = cart.value.find(item => item.id === productId);
    if (item) {
        item.quantity = Math.max(1, newQuantity);
    }
};

const subtotal = computed(() => {
    return cart.value.reduce((sum, item) => {
        const price = typeof item.price === 'number' ? item.price : parseFloat(item.price) || 0;
        return sum + (price * item.quantity);
    }, 0);
});

const tax = computed(() => subtotal.value * 0.1); // 10% tax
const total = computed(() => subtotal.value + tax.value);

const paymentMethods = [
    { id: 'cash', name: 'Cash' },
    { id: 'card', name: 'Card' },
    { id: 'mobile', name: 'Mobile Payment' }
];

const calculateChange = () => {
    if (selectedPaymentMethod.value === 'cash') {
        changeAmount.value = paymentAmount.value - total.value;
    } else {
        changeAmount.value = 0;
    }
};

const processPayment = async () => {
    if (!selectedBranch.value) {
        toast.error('Please select a branch first');
        return;
    }
    
    if (cart.value.length === 0) {
        toast.error('Your cart is empty');
        return;
    }

    // Validate stock quantities
    const insufficientStock = cart.value.some(item => {
        const product = filteredProducts.value.find(p => p.id === item.id);
        return !product || item.quantity > product.stock_quantity;
    });

    if (insufficientStock) {
        toast.error('Some items in your cart exceed available stock');
        return;
    }

    // Show payment modal
    showPaymentModal.value = true;
    paymentAmount.value = total.value;
    calculateChange();
};

const finalizePayment = async () => {
    if (selectedPaymentMethod.value === 'cash' && paymentAmount.value < total.value) {
        toast.error('Insufficient payment amount');
        return;
    }

    isProcessing.value = true;
    
    try {
        const orderData = {
            branch_id: selectedBranch.value,
            items: cart.value.map(item => ({
                product_id: item.id,
                quantity: item.quantity,
                price: item.price,
                stock_quantity: item.stock_quantity
            })),
            subtotal: subtotal.value,
            tax: tax.value,
            total: total.value,
            payment_method: selectedPaymentMethod.value,
            payment_amount: paymentAmount.value,
            change_amount: changeAmount.value,
            status: 'confirmed'
        };
        
        console.log('Sending order data:', orderData);
        
        const response = await axios.post(route('orders.store'), orderData);
        
        if (response.data.success) {
            // Update local product stock quantities
            cart.value.forEach(item => {
                const product = filteredProducts.value.find(p => p.id === item.id);
                if (product) {
                    product.stock_quantity -= item.quantity;
                }
            });

            cart.value = [];
            showPaymentModal.value = false;
            toast.success('Sale confirmed and stock updated successfully!');
            
            // Reset payment form
            selectedPaymentMethod.value = 'cash';
            paymentAmount.value = 0;
            changeAmount.value = 0;
        }
    } catch (error) {
        console.error('Error processing payment:', error);
        const errorMessage = error.response?.data?.message || 'Error processing payment. Please try again.';
        toast.error(errorMessage);
    } finally {
        isProcessing.value = false;
    }
};
</script>

<template>
    <Head title="POS" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Branch Selection -->
                <div v-if="!selectedBranch" class="mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Select a Branch</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Choose a branch to view its details
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="branch in branches"
                            :key="branch.id"
                            @click="selectBranch(branch.id)"
                            class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-all cursor-pointer border-2 border-transparent hover:border-indigo-500 transform hover:scale-105"
                        >
                            <h4 class="text-xl font-semibold text-white">
                                {{ branch.name }}
                            </h4>
                            <p class="text-gray-400 mt-2">
                                {{ branch.location }}
                            </p>
                            <p class="text-gray-500 mt-2 text-sm">
                                {{ branch.products?.length || 0 }} Products
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Products Section -->
                    <div class="lg:col-span-2">
                        <!-- Branch Info and Back Button -->
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

                        <!-- Search and Filter -->
                        <div class="mb-6 flex flex-col sm:flex-row gap-4">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search products..."
                                    class="pl-10 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                />
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                    </svg>
                                </div>
                                <select
                                    v-model="selectedCategory"
                                    class="pl-10 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                >
                                    <option v-for="category in categories" :key="category" :value="category">
                                        {{ category === 'all' ? 'All Categories' : category }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Products Grid -->
                        <div v-if="filteredProducts.length === 0" class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="mt-2 text-gray-500 dark:text-gray-400">No products found</p>
                        </div>
                        
                        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                            <div
                                v-for="product in filteredProducts"
                                :key="product.id"
                                class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-4 cursor-pointer hover:shadow-lg transition-all transform hover:scale-105"
                                @click="addToCart(product)"
                            >
                                <div class="relative">
                                    <img 
                                        :src="product.image ? `/storage/${product.image}` : 'https://via.placeholder.com/150'" 
                                        :alt="product.name" 
                                        class="w-full h-32 object-cover rounded-md mb-2" 
                                    />
                                    <div class="absolute top-2 right-2 bg-indigo-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                                        ${{ typeof product.price === 'number' ? product.price.toFixed(2) : product.price }}
                                    </div>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">{{ product.name }}</h3>
                                <div class="flex justify-between items-center mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Stock: {{ product.stock_quantity }}
                                    </p>
                                    <button 
                                        class="bg-indigo-600 text-white p-1 rounded-full hover:bg-indigo-700 transition-colors"
                                        @click.stop="addToCart(product)"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Section -->
                    <div class="lg:col-span-1">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 sticky top-6">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                <svg class="w-6 h-6 mr-2 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Cart
                            </h2>
                            
                            <!-- Empty Cart Message -->
                            <div v-if="cart.length === 0" class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <p class="mt-2 text-gray-500 dark:text-gray-400">Your cart is empty</p>
                            </div>
                            
                            <!-- Cart Items -->
                            <div v-else class="space-y-4 mb-6 max-h-96 overflow-y-auto">
                                <div v-for="item in cart" :key="item.id" class="flex items-center justify-between bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ item.name }}</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">${{ typeof item.price === 'number' ? item.price.toFixed(2) : item.price }}</p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <div class="flex items-center border border-gray-300 dark:border-gray-600 rounded-md">
                                            <button 
                                                @click.stop="updateQuantity(item.id, item.quantity - 1)"
                                                class="px-2 py-1 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-l-md"
                                                :disabled="item.quantity <= 1"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                                </svg>
                                            </button>
                                            <input
                                                type="number"
                                                v-model="item.quantity"
                                                @change="updateQuantity(item.id, parseInt($event.target.value))"
                                                class="w-12 text-center border-0 focus:ring-0 dark:bg-gray-800 dark:text-white"
                                                min="1"
                                                :max="item.stock_quantity"
                                            />
                                            <button 
                                                @click.stop="updateQuantity(item.id, item.quantity + 1)"
                                                class="px-2 py-1 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-r-md"
                                                :disabled="item.quantity >= item.stock_quantity"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                </svg>
                                            </button>
                                        </div>
                                        <button
                                            @click.stop="removeFromCart(item.id)"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Cart Summary -->
                            <div class="border-t border-gray-200 dark:border-gray-600 pt-4">
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-600 dark:text-gray-300">Subtotal</span>
                                    <span class="text-gray-900 dark:text-white">${{ subtotal.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-600 dark:text-gray-300">Tax (10%)</span>
                                    <span class="text-gray-900 dark:text-white">${{ tax.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between mb-4">
                                    <span class="text-lg font-semibold text-gray-900 dark:text-white">Total</span>
                                    <span class="text-lg font-semibold text-gray-900 dark:text-white">${{ total.toFixed(2) }}</span>
                                </div>
                                <button
                                    @click="processPayment"
                                    class="w-full bg-indigo-600 text-white py-3 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 flex items-center justify-center"
                                    :disabled="cart.length === 0"
                                >
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    Process Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <div v-if="showPaymentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-8 max-w-2xl w-full mx-4">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Payment Details</h3>
                    <button 
                        @click="showPaymentModal = false"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Left Column: Order Summary -->
                    <div class="space-y-6">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Order Summary</h4>
                            <div class="space-y-4">
                                <div v-for="item in cart" :key="item.id" class="flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Qty: {{ item.quantity }}</p>
                                    </div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        ${{ (item.price * item.quantity).toFixed(2) }}
                                    </p>
                                </div>
                                <div class="border-t border-gray-200 dark:border-gray-600 pt-4">
                                    <div class="flex justify-between mb-2">
                                        <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                        <span class="text-gray-900 dark:text-white">${{ subtotal.toFixed(2) }}</span>
                                    </div>
                                    <div class="flex justify-between mb-2">
                                        <span class="text-gray-600 dark:text-gray-400">Tax (10%)</span>
                                        <span class="text-gray-900 dark:text-white">${{ tax.toFixed(2) }}</span>
                                    </div>
                                    <div class="flex justify-between font-bold text-lg">
                                        <span class="text-gray-900 dark:text-white">Total</span>
                                        <span class="text-gray-900 dark:text-white">${{ total.toFixed(2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Payment Method -->
                    <div class="space-y-6">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Method</h4>
                            <div class="space-y-4">
                                <div class="grid grid-cols-3 gap-4">
                                    <button
                                        v-for="method in paymentMethods"
                                        :key="method.id"
                                        @click="selectedPaymentMethod = method.id"
                                        :class="[
                                            'p-4 rounded-lg border-2 text-center transition-all',
                                            selectedPaymentMethod === method.id
                                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-indigo-300 dark:hover:border-indigo-700'
                                        ]"
                                    >
                                        <span class="block text-sm font-medium" :class="selectedPaymentMethod === method.id ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-300'">
                                            {{ method.name }}
                                        </span>
                                    </button>
                                </div>

                                <!-- Cash Payment Input -->
                                <div v-if="selectedPaymentMethod === 'cash'" class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Amount Received
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-3 text-gray-500 dark:text-gray-400">$</span>
                                        <input
                                            type="number"
                                            v-model="paymentAmount"
                                            @input="calculateChange"
                                            class="w-full pl-8 pr-4 py-3 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-indigo-500 focus:border-indigo-500"
                                            min="0"
                                            step="0.01"
                                        />
                                    </div>
                                    
                                    <div class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium text-green-800 dark:text-green-400">Change</span>
                                            <span class="text-lg font-bold text-green-800 dark:text-green-400">
                                                ${{ changeAmount.toFixed(2) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-8 flex justify-end space-x-4">
                    <button
                        @click="showPaymentModal = false"
                        class="px-6 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
                        :disabled="isProcessing"
                    >
                        Cancel
                    </button>
                    <button
                        @click="finalizePayment"
                        class="px-6 py-3 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="isProcessing || (selectedPaymentMethod === 'cash' && paymentAmount < total)"
                    >
                        <span v-if="isProcessing" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                        <span v-else>Confirm Payment</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>