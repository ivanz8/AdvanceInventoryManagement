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
        // Fetch the image and convert it to a File object
        const response = await fetch(imageUrl);
        const blob = await response.blob();
        const file = new File([blob], 'product-image.jpg', { type: 'image/jpeg' });
        
        // Update the form and preview
        form.image = file;
        imagePreview.value = imageUrl;
        selectedImageUrl.value = imageUrl;
        searchResults.value = []; // Clear search results
    } catch (error) {
        toast.error('Failed to load image. Please try another one.');
        console.error('Image loading error:', error);
    }
};

onMounted(() => {
    // Check if the 'success' query parameter is in the URL
    const successMessage = usePage().url.split('?success=')[1];
    
    if (successMessage) {
        // Display the success message as a toast
        toast.success(decodeURIComponent(successMessage));
    }
});

const props = defineProps({
    categories: Array,
    branches: Array
});

const form = useForm({
    name: '',
    category_id: '',
    branch_id: '',
    barcode: '',
    price: '',
    stock_quantity: '',
    image: null,
});

const isAdmin = computed(() => {
    return usePage().props.auth.user?.id === 1;
});

const handleImageUpload = (event) => {
    const file = event.target.files[0];
    form.image = file;
    
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

const submit = () => {
    const formData = new FormData();
    
    // Append all form fields
    formData.append('name', form.name);
    formData.append('category_id', form.category_id);
    formData.append('branch_id', form.branch_id);
    formData.append('barcode', form.barcode);
    formData.append('price', form.price);
    formData.append('stock_quantity', form.stock_quantity);
    formData.append('image', form.image);
    
    axios.post(route('products.store'), formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    })
    .then(response => {
        form.reset();
        toast.success('Product added successfully!');
        router.get(route('dashboard'));
    })
    .catch(error => {
        form.errors = error.response.data.errors;
        if (error.response.data.message) {
            toast.error(error.response.data.message);
        } else {
            toast.error('An error occurred while adding the product.');
        }
    });
};
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Product Management
                </h2>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Add new products to your inventory
                </div>
            </div>
        </template>
        
        <div class="py-12 bg-gray-100 dark:bg-gray-900">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="isAdmin" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Add New Product</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Fill in the details below to add a new product to your inventory.
                            </p>
                        </div>
                        
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Product Name -->
                                <div>
                                    <InputLabel for="name" value="Product Name" />
                                    <TextInput
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                        required
                                        autofocus
                                    />
                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>

                                <!-- Barcode -->
                                <div>
                                    <InputLabel for="barcode" value="Barcode" />
                                    <TextInput
                                        id="barcode"
                                        v-model="form.barcode"
                                        type="text"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.barcode" />
                                </div>

                                <!-- Category Selection -->
                                <div>
                                    <InputLabel for="category_id" value="Category" />
                                    <select
                                        id="category_id"
                                        v-model="form.category_id"
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
                                    <InputError class="mt-2" :message="form.errors.category_id" />
                                </div>

                                <!-- Branch Selection -->
                                <div>
                                    <InputLabel for="branch_id" value="Branch" />
                                    <select
                                        id="branch_id"
                                        v-model="form.branch_id"
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
                                    <InputError class="mt-2" :message="form.errors.branch_id" />
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
                                            v-model="form.price"
                                            type="number"
                                            step="0.01"
                                            class="pl-7 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                            required
                                        />
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.price" />
                                </div>

                                <!-- Stock Quantity -->
                                <div>
                                    <InputLabel for="stock_quantity" value="Stock Quantity" />
                                    <TextInput
                                        id="stock_quantity"
                                        v-model="form.stock_quantity"
                                        type="number"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.stock_quantity" />
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
                                <InputError class="mt-2" :message="form.errors.image" />
                            </div>

                            <div class="flex items-center justify-end mt-6">
                                <PrimaryButton 
                                    class="px-6 py-2"
                                    :disabled="form.processing"
                                >
                                    Add Product
                                </PrimaryButton>
                            </div>
                        </form>
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
    </AppLayout>
</template>
