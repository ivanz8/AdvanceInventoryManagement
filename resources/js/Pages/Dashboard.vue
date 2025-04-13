<script setup>
import { ref, onMounted, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';

const toast = useToast();
const imagePreview = ref(null);
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const selectedImageUrl = ref(null);
const selectedModule = ref(null);
const selectedProductSubModule = ref(null);
const editingCategory = ref(null);
const showDeleteConfirm = ref(false);
const categoryToDelete = ref(null);
const showProductModal = ref(false);
const showCategoryModal = ref(false);
const isLoadingProducts = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);
const perPage = ref(10);
const totalProducts = ref(0);
const loadedProducts = ref([]);
const showProductDetailsModal = ref(false);
const selectedProduct = ref(null);
const isEditing = ref(false);
const productSearchQuery = ref('');

// Add Google Custom Search API configuration
// To get your API key:
// 1. Go to https://console.cloud.google.com/
// 2. Create a new project or select an existing one
// 3. Enable the Custom Search API for your project
// 4. Create an API key in the Credentials section
const GOOGLE_API_KEY = 'AIzaSyDljNDLnHy0DkTQAjdxwE2OmSp7klWuW-8'; // Replace with your actual API key
const SEARCH_ENGINE_ID = 'f4f824a902314470b'; // Your Custom Search Engine ID

const searchImages = async () => {
    if (!searchQuery.value) return;
    
    isSearching.value = true;
    try {
        const response = await axios.get('https://www.googleapis.com/customsearch/v1', {
            params: {
                key: GOOGLE_API_KEY,
                cx: SEARCH_ENGINE_ID,
                q: searchQuery.value,
                searchType: 'image',
                imgSize: 'medium',
                num: 10
            }
        });
        
        searchResults.value = response.data.items || [];
    } catch (error) {
        toast.error('Failed to search images. Please try again.');
        console.error('Image search error:', error);
    } finally {
        isSearching.value = false;
    }
};

const selectImage = async (imageUrl) => {
    try {
        // Show loading state
        toast.info('Loading image...');
        
        // Create a proxy URL to avoid CORS issues
        const proxyUrl = `/api/proxy-image?url=${encodeURIComponent(imageUrl)}`;
        
        // Fetch the image through our proxy
        const response = await fetch(proxyUrl);
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || `Failed to fetch image: ${response.status} ${response.statusText}`);
        }
        
        const blob = await response.blob();
        
        // Check if the blob is actually an image
        if (!blob.type.startsWith('image/')) {
            throw new Error('The selected file is not an image');
        }
        
        const file = new File([blob], 'product-image.jpg', { type: 'image/jpeg' });
        
        // Update the form and preview
        productForm.image = file;
        imagePreview.value = imageUrl;
        selectedImageUrl.value = imageUrl;
        searchResults.value = []; // Clear search results
        
        toast.success('Image loaded successfully!');
    } catch (error) {
        toast.error(`Failed to load image: ${error.message}`);
        console.error('Image loading error:', error);
        
        // Try to load the image directly for preview (this might fail due to CORS)
        try {
            imagePreview.value = imageUrl;
        } catch (e) {
            console.error('Failed to set image preview:', e);
        }
    }
};

// Fetch products with pagination
const fetchProducts = async (page = 1, isLoadMore = false) => {
    isLoadingProducts.value = true;
    try {
        const response = await axios.get('/api/products', {
            params: {
                page: page,
                per_page: perPage.value
            }
        });
        
        if (isLoadMore) {
            loadedProducts.value = [...loadedProducts.value, ...response.data.data];
        } else {
            loadedProducts.value = response.data.data;
        }
        
        currentPage.value = response.data.current_page;
        totalPages.value = response.data.last_page;
        totalProducts.value = response.data.total;
    } catch (error) {
        toast.error('Failed to load products. Please try again.');
        console.error('Products loading error:', error);
    } finally {
        isLoadingProducts.value = false;
    }
};

// Change page
const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        fetchProducts(page);
    }
};

// Open product modal
const openProductModal = (product = null) => {
    if (product) {
        isEditing.value = true;
        productForm.name = product.name;
        productForm.category_id = product.category_id;
        productForm.branch_id = product.branch_id;
        productForm.barcode = product.barcode;
        productForm.price = product.price;
        productForm.stock_quantity = product.stock_quantity;
        imagePreview.value = product.image ? `/storage/${product.image}` : null;
        productForm.id = product.id;
    } else {
        isEditing.value = false;
        productForm.reset();
        productForm.id = null;
        imagePreview.value = null;
        selectedImageUrl.value = null;
    }
    showProductModal.value = true;
};

// Close product modal
const closeProductModal = () => {
    showProductModal.value = false;
    isEditing.value = false;
    productForm.reset();
    productForm.id = null;
    imagePreview.value = null;
    selectedImageUrl.value = null;
};

onMounted(() => {
    // Check if the 'success' query parameter is in the URL
    const successMessage = usePage().url.split('?success=')[1];
    
    if (successMessage) {
        // Display the success message as a toast
        toast.success(decodeURIComponent(successMessage));
    }
    
    // Fetch products when the component is mounted
    fetchProducts(1);
});

const props = defineProps({
    categories: Array,
    branches: Array
});

// Product form
const productForm = useForm({
    id: null,
    name: '',
    category_id: '',
    branch_id: '',
    barcode: '',
    price: '',
    stock_quantity: '',
    image: null,
});

// Category form
const categoryForm = useForm({
    name: '',
    description: ''
});

const isAdmin = computed(() => {
    return usePage().props.auth.user?.id === 1;
});

const handleImageUpload = (event) => {
    const file = event.target.files[0];
    productForm.image = file;
    
    // Create image preview
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        imagePreview.value = null;
    }
};

const submitProduct = () => {
    const formData = new FormData();
    
    formData.append('name', productForm.name);
    formData.append('category_id', productForm.category_id);
    formData.append('branch_id', productForm.branch_id);
    formData.append('barcode', productForm.barcode);
    formData.append('price', productForm.price);
    formData.append('stock_quantity', productForm.stock_quantity);
    if (productForm.image instanceof File) {
        formData.append('image', productForm.image);
    }

    if (isEditing.value) {
        // Update existing product
        formData.append('_method', 'PUT');
        axios.post(`/products/${productForm.id}`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })
        .then(response => {
            toast.success('Product updated successfully!');
            closeProductModal();
            fetchProducts(currentPage.value);
            if (showProductDetailsModal.value) {
                closeProductDetails();
            }
        })
        .catch(error => {
            productForm.errors = error.response.data.errors;
            if (error.response.data.message) {
                toast.error(error.response.data.message);
            } else {
                toast.error('An error occurred while updating the product.');
            }
        });
    } else {
        // Create new product
        axios.post(route('products.store'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })
        .then(response => {
            toast.success('Product added successfully!');
            closeProductModal();
            fetchProducts(currentPage.value);
        })
        .catch(error => {
            productForm.errors = error.response.data.errors;
            if (error.response.data.message) {
                toast.error(error.response.data.message);
            } else {
                toast.error('An error occurred while adding the product.');
            }
        });
    }
};

const submitCategory = () => {
    categoryForm.post(route('categories.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Category created successfully!');
            categoryForm.reset();
            router.get(route('dashboard'));
        },
        onError: (errors) => {
            toast.error('Failed to create category. Please check the form for errors.');
        }
    });
};

const selectModule = (module) => {
    selectedModule.value = module;
};

const editCategory = (category) => {
    editingCategory.value = category;
    categoryForm.name = category.name;
    categoryForm.description = category.description;
};

const cancelEdit = () => {
    editingCategory.value = null;
    categoryForm.reset();
};

const updateCategory = () => {
    categoryForm.put(route('categories.update', editingCategory.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Category updated successfully!');
            editingCategory.value = null;
            categoryForm.reset();
            router.get(route('dashboard'));
        },
        onError: (errors) => {
            toast.error('Failed to update category. Please check the form for errors.');
        }
    });
};

const confirmDelete = (category) => {
    categoryToDelete.value = category;
    showDeleteConfirm.value = true;
};

const deleteCategory = () => {
    axios.delete(route('categories.destroy', categoryToDelete.value.id), {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
        .then(response => {
            if (response.data.success) {
                toast.success(response.data.message || 'Category deleted successfully!');
                showDeleteConfirm.value = false;
                categoryToDelete.value = null;
                router.get(route('dashboard'));
            } else {
                toast.error(response.data.message || 'Failed to delete category');
            }
        })
        .catch(error => {
            const errorMessage = error.response?.data?.message || 'Failed to delete category. Please try again.';
            toast.error(errorMessage);
            console.error('Delete error:', error);
        });
};

// Add these computed properties
const allProducts = computed(() => loadedProducts.value);

const lowStockProducts = computed(() => {
    return allProducts.value.filter(product => product.stock_quantity <= 10);
});

const topSoldProducts = computed(() => {
    // This will need to be updated once we have sales data
    return allProducts.value.slice(0, 5); // Temporarily return first 5 products
});

// Add loadMore function
const loadMore = () => {
    if (currentPage.value < totalPages.value) {
        fetchProducts(currentPage.value + 1, true);
    }
};

const openProductDetails = (product) => {
    selectedProduct.value = product;
    showProductDetailsModal.value = true;
};

const closeProductDetails = () => {
    showProductDetailsModal.value = false;
    selectedProduct.value = null;
};

// Add updateProduct function
const updateProduct = () => {
    const formData = new FormData();
    
    formData.append('_method', 'PUT'); // Laravel requires this for PUT requests
    formData.append('name', productForm.name);
    formData.append('category_id', productForm.category_id);
    formData.append('branch_id', productForm.branch_id);
    formData.append('barcode', productForm.barcode);
    formData.append('price', productForm.price);
    formData.append('stock_quantity', productForm.stock_quantity);
    if (productForm.image instanceof File) {
        formData.append('image', productForm.image);
    }

    axios.post(`/products/${productForm.id}`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    })
    .then(response => {
        toast.success('Product updated successfully!');
        closeProductModal();
        fetchProducts(currentPage.value); // Refresh the current page
        if (showProductDetailsModal.value) {
            closeProductDetails();
        }
    })
    .catch(error => {
        productForm.errors = error.response.data.errors;
        if (error.response.data.message) {
            toast.error(error.response.data.message);
        } else {
            toast.error('An error occurred while updating the product.');
        }
    });
};

const filteredProducts = computed(() => {
    if (!productSearchQuery.value) return allProducts.value;
    
    const searchTerm = productSearchQuery.value.toLowerCase();
    return allProducts.value.filter(product => 
        product.name.toLowerCase().includes(searchTerm) ||
        product.barcode.toLowerCase().includes(searchTerm) ||
        product.category?.name.toLowerCase().includes(searchTerm) ||
        product.branch?.name.toLowerCase().includes(searchTerm)
    );
});
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Inventory Management
                </h2>
            </div>
        </template>
        
        <div class="py-12 bg-gray-100 dark:bg-gray-900">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="isAdmin">
                    <!-- Module Selection -->
                    <div v-if="!selectedModule" class="mb-8">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Select Module</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Choose a module to manage your inventory
                            </p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Products Module Card -->
                            <div
                                @click="selectModule('products')"
                                class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer border-2 border-transparent hover:border-indigo-500"
                            >
                                <div class="flex items-center">
                                    <div class="p-3 bg-indigo-100 dark:bg-indigo-900 rounded-full">
                                        <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                    <h4 class="ml-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Products</h4>
                                </div>
                                <p class="mt-4 text-gray-600 dark:text-gray-400">
                                    Manage products and inventory
                                </p>
                            </div>

                            <!-- Categories Module Card -->
                            <div
                                @click="selectModule('categories')"
                                class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer border-2 border-transparent hover:border-indigo-500"
                            >
                                <div class="flex items-center">
                                    <div class="p-3 bg-indigo-100 dark:bg-indigo-900 rounded-full">
                                        <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                        </svg>
                                    </div>
                                    <h4 class="ml-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Categories</h4>
                                </div>
                                <p class="mt-4 text-gray-600 dark:text-gray-400">
                                    Manage product categories
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Products Module -->
                    <div v-if="selectedModule === 'products'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Products</h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Manage your product inventory
                                    </p>
                                </div>
                                <div class="flex space-x-2">
                                    <button
                                        @click="openProductModal()"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                                    >
                                        Add Product
                                    </button>
                                    <button
                                        @click="selectedModule = null"
                                        class="text-sm text-gray-600 dark:text-gray-400 hover:text-indigo-500 dark:hover:text-indigo-400"
                                    >
                                        Back to Modules
                                    </button>
                                </div>
                            </div>

                            <!-- Product Sub-Modules Selection -->
                            <div v-if="!selectedProductSubModule" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
                                <!-- Top Products Sold Card -->
                                <div
                                    @click="selectedProductSubModule = 'top-sold'"
                                    class="p-6 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-xl font-semibold text-white">Top Products</h4>
                                            <p class="mt-1 text-purple-100">Most sold products</p>
                                        </div>
                                        <div class="p-3 bg-white bg-opacity-20 rounded-full">
                                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <!-- Low Stock Card -->
                                <div
                                    @click="selectedProductSubModule = 'low-stock'"
                                    class="p-6 bg-gradient-to-br from-red-500 to-pink-600 rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-xl font-semibold text-white">Low Stock</h4>
                                            <p class="mt-1 text-red-100">Products running low</p>
                                        </div>
                                        <div class="p-3 bg-white bg-opacity-20 rounded-full">
                                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <!-- All Products Card -->
                                <div
                                    @click="selectedProductSubModule = 'all-products'"
                                    class="p-6 bg-gradient-to-br from-green-500 to-teal-600 rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-xl font-semibold text-white">Product List</h4>
                                            <p class="mt-1 text-green-100">View all products</p>
                                        </div>
                                        <div class="p-3 bg-white bg-opacity-20 rounded-full">
                                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sub-Module Content -->
                            <div v-else class="mt-6">
                                <!-- Product List Header -->
                                <div class="flex justify-between items-center mb-6">
                                    <h4 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                        {{ 
                                            selectedProductSubModule === 'top-sold' ? 'Top Products Sold' :
                                            selectedProductSubModule === 'low-stock' ? 'Low Stock Products' :
                                            'All Products'
                                        }}
                                    </h4>
                                    <button
                                        @click="selectedProductSubModule = null"
                                        class="text-sm text-gray-600 dark:text-gray-400 hover:text-indigo-500 dark:hover:text-indigo-400"
                                    >
                                        Back to Products
                                    </button>
                                </div>

                                <!-- Loading State -->
                                <div v-if="isLoadingProducts" class="text-center py-8">
                                    <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="mt-2 text-gray-600 dark:text-gray-400">Loading products...</p>
                                </div>

                                <!-- Empty State -->
                                <div v-else-if="
                                    (selectedProductSubModule === 'top-sold' && topSoldProducts.length === 0) ||
                                    (selectedProductSubModule === 'low-stock' && lowStockProducts.length === 0) ||
                                    (selectedProductSubModule === 'all-products' && allProducts.length === 0)
                                " class="text-center py-8">
                                    <svg class="h-12 w-12 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                    <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">No products found</h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ 
                                            selectedProductSubModule === 'top-sold' ? 'No sales data available yet.' :
                                            selectedProductSubModule === 'low-stock' ? 'No products are running low on stock.' :
                                            'Get started by adding a new product.'
                                        }}
                                    </p>
                                </div>

                                <!-- Product Grid -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                    <div
                                        v-for="product in 
                                            selectedProductSubModule === 'top-sold' ? topSoldProducts :
                                            selectedProductSubModule === 'low-stock' ? lowStockProducts :
                                            allProducts
                                        "
                                        :key="product.id"
                                        class="bg-gray-800 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                                        @click="openProductDetails(product)"
                                    >
                                        <img
                                            v-if="product.image"
                                            :src="`/storage/${product.image}`"
                                            :alt="product.name"
                                            class="w-full h-32 object-cover"
                                            loading="lazy"
                                        />
                                        <div v-else class="w-full h-32 bg-gray-700 flex items-center justify-center">
                                            <svg class="h-8 w-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div class="p-3">
                                            <h4 class="text-base font-medium text-white truncate">{{ product.name }}</h4>
                                            <div class="mt-2 flex justify-between items-center text-sm">
                                                <span class="text-indigo-400">${{ parseFloat(product.price).toFixed(2) }}</span>
                                                <span class="text-gray-400">Stock: {{ product.stock_quantity }}</span>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-400">
                                                {{ product.category?.name || 'N/A' }} • {{ product.branch?.name || 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Show More Button (only show for all products) -->
                                <div v-if="selectedProductSubModule === 'all-products' && currentPage < totalPages" class="mt-6 flex justify-center">
                                    <button
                                        @click="loadMore"
                                        :disabled="isLoadingProducts"
                                        class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2"
                                    >
                                        <span>Show More</span>
                                        <svg v-if="isLoadingProducts" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Products Counter -->
                                <div v-if="selectedProductSubModule === 'all-products'" class="mt-3 text-center text-xs text-gray-600 dark:text-gray-400">
                                    Showing {{ loadedProducts.length }} of {{ totalProducts }} products
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Categories Module -->
                    <div v-if="selectedModule === 'categories'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Categories</h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Manage your product categories
                                    </p>
                                </div>
                                <button
                                    @click="selectedModule = null"
                                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-indigo-500 dark:hover:text-indigo-400"
                                >
                                    Back to Modules
                                </button>
                            </div>

                            <!-- Category List -->
                            <div class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">Existing Categories</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div
                                        v-for="category in categories"
                                        :key="category.id"
                                        class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg flex justify-between items-center"
                                    >
                                        <span class="text-gray-800 dark:text-gray-200">{{ category.name }}</span>
                                        <div class="flex space-x-2">
                                            <button
                                                @click="editCategory(category)"
                                                class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="confirmDelete(category)"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Add/Edit Category Form -->
                            <div class="mt-8">
                                <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    {{ editingCategory ? 'Edit Category' : 'Add New Category' }}
                                </h4>
                                <form @submit.prevent="editingCategory ? updateCategory() : submitCategory()" class="space-y-4">
                                    <div>
                                        <InputLabel for="category_name" value="Category Name" />
                                        <TextInput
                                            id="category_name"
                                            v-model="categoryForm.name"
                                            type="text"
                                            class="mt-1 block w-full"
                                            required
                                        />
                                        <InputError class="mt-2" :message="categoryForm.errors.name" />
                                    </div>
                                    <div>
                                        <InputLabel for="category_description" value="Description (Optional)" />
                                        <textarea
                                            id="category_description"
                                            v-model="categoryForm.description"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                            rows="3"
                                        ></textarea>
                                        <InputError class="mt-2" :message="categoryForm.errors.description" />
                                    </div>
                                    <div class="flex justify-end space-x-2">
                                        <button
                                            v-if="editingCategory"
                                            type="button"
                                            @click="cancelEdit"
                                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600"
                                        >
                                            Cancel
                                        </button>
                                        <PrimaryButton type="submit" :disabled="categoryForm.processing">
                                            {{ editingCategory ? 'Update Category' : 'Add Category' }}
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">Access Denied</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            You don't have permission to view this page.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteConfirm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Delete</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Are you sure you want to delete the category "{{ categoryToDelete?.name }}"? This action cannot be undone.
                </p>
                <div class="flex justify-end space-x-2">
                    <button
                        @click="showDeleteConfirm = false"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteCategory"
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Product Modal -->
        <div v-if="showProductModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-4xl w-full my-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        {{ isEditing ? 'Edit Product' : 'Add New Product' }}
                    </h3>
                    <button
                        @click="closeProductModal"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <form @submit.prevent="submitProduct" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Product Name -->
                        <div>
                            <InputLabel for="name" value="Product Name" />
                            <TextInput
                                id="name"
                                v-model="productForm.name"
                                type="text"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                required
                                autofocus
                            />
                            <InputError class="mt-2" :message="productForm.errors.name" />
                        </div>

                        <!-- Barcode -->
                        <div>
                            <InputLabel for="barcode" value="Barcode" />
                            <TextInput
                                id="barcode"
                                v-model="productForm.barcode"
                                type="text"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                required
                            />
                            <InputError class="mt-2" :message="productForm.errors.barcode" />
                        </div>

                        <!-- Category Selection -->
                        <div>
                            <InputLabel for="category_id" value="Category" />
                            <select
                                id="category_id"
                                v-model="productForm.category_id"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                required
                            >
                                <option value="">Select Category</option>
                                <option 
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="productForm.errors.category_id" />
                        </div>

                        <!-- Branch Selection -->
                        <div>
                            <InputLabel for="branch_id" value="Branch" />
                            <select
                                id="branch_id"
                                v-model="productForm.branch_id"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                required
                            >
                                <option value="">Select Branch</option>
                                <option 
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    :value="branch.id"
                                >
                                    {{ branch.name }} - {{ branch.location }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="productForm.errors.branch_id" />
                        </div>

                        <!-- Price -->
                        <div>
                            <InputLabel for="price" value="Price" />
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 dark:text-gray-400 sm:text-sm">$</span>
                                </div>
                                <TextInput
                                    id="price"
                                    v-model="productForm.price"
                                    type="number"
                                    step="0.01"
                                    class="pl-7 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    required
                                />
                            </div>
                            <InputError class="mt-2" :message="productForm.errors.price" />
                        </div>

                        <!-- Stock Quantity -->
                        <div>
                            <InputLabel for="stock_quantity" value="Stock Quantity" />
                            <TextInput
                                id="stock_quantity"
                                v-model="productForm.stock_quantity"
                                type="number"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                required
                            />
                            <InputError class="mt-2" :message="productForm.errors.stock_quantity" />
                        </div>
                    </div>

                    <!-- Image Upload -->
                    <div class="mt-6">
                        <InputLabel for="image" value="Product Image" />
                        
                        <!-- Image Search Section -->
                        <div class="mt-2 mb-4">
                            <div class="flex gap-2">
                                <TextInput
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search for product images..."
                                    class="flex-1"
                                    @keyup.enter="searchImages"
                                />
                                <PrimaryButton
                                    @click="searchImages"
                                    :disabled="isSearching"
                                    class="whitespace-nowrap"
                                >
                                    {{ isSearching ? 'Searching...' : 'Search Images' }}
                                </PrimaryButton>
                            </div>
                            
                            <!-- Search Results -->
                            <div v-if="searchResults.length > 0" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div
                                    v-for="(image, index) in searchResults"
                                    :key="index"
                                    class="relative aspect-square cursor-pointer rounded-lg overflow-hidden border-2"
                                    :class="selectedImageUrl === image.link ? 'border-indigo-500' : 'border-gray-200'"
                                    @click="selectImage(image.link)"
                                >
                                    <img
                                        :src="image.link"
                                        :alt="image.title"
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-700 border-dashed rounded-md">
                            <div class="space-y-1 text-center">
                                <!-- Image Preview -->
                                <div v-if="imagePreview" class="mb-4">
                                    <img :src="imagePreview" alt="Preview" class="mx-auto h-32 w-32 object-cover rounded-md" />
                                </div>
                                
                                <svg v-else class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                    <label for="image" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                        <span>Upload a file</span>
                                        <input 
                                            id="image" 
                                            type="file" 
                                            class="sr-only" 
                                            @change="handleImageUpload"
                                            accept="image/*"
                                        >
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    PNG, JPG, GIF up to 10MB
                                </p>
                            </div>
                        </div>
                        <InputError class="mt-2" :message="productForm.errors.image" />
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <button
                            type="button"
                            @click="closeProductModal"
                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 mr-2"
                        >
                            Cancel
                        </button>
                        <PrimaryButton 
                            class="px-6 py-2"
                            :disabled="productForm.processing"
                        >
                            {{ isEditing ? 'Update Product' : 'Add Product' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Product Details Modal -->
        <div v-if="showProductDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-2xl w-full my-8 mx-4">
                <div class="flex justify-between items-start">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Product Details</h3>
                    <button
                        @click="closeProductDetails()"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="selectedProduct" class="mt-6">
                    <!-- Product Image -->
                    <div class="aspect-w-16 aspect-h-9 mb-6">
                        <img
                            v-if="selectedProduct.image"
                            :src="`/storage/${selectedProduct.image}`"
                            :alt="selectedProduct.name"
                            class="w-full h-64 object-cover rounded-lg"
                        />
                        <div v-else class="w-full h-64 bg-gray-700 flex items-center justify-center rounded-lg">
                            <svg class="h-16 w-16 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Product Information -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <h4 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {{ selectedProduct.name }}
                            </h4>
                            <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                ${{ parseFloat(selectedProduct.price).toFixed(2) }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Barcode:</span>
                                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ selectedProduct.barcode }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Category:</span>
                                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ selectedProduct.category?.name || 'N/A' }}</span>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Stock:</span>
                                    <span :class="{
                                        'text-green-600 dark:text-green-400': selectedProduct.stock_quantity > 10,
                                        'text-yellow-600 dark:text-yellow-400': selectedProduct.stock_quantity <= 10 && selectedProduct.stock_quantity > 0,
                                        'text-red-600 dark:text-red-400': selectedProduct.stock_quantity === 0
                                    }" class="font-medium">
                                        {{ selectedProduct.stock_quantity }} units
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Branch:</span>
                                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ selectedProduct.branch?.name || 'N/A' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <button
                                @click="closeProductDetails"
                                class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600"
                            >
                                Close
                            </button>
                            <button
                                @click="openProductModal(selectedProduct)"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                            >
                                Edit Product
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
