<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';

const toast = useToast();

// Get initial props
const { branches, categories, auth } = usePage().props;

// Reactive references
const loading = ref(false);
const visibleCount = ref(5);
const selectedCategory = ref('all');
const selectedBranch = ref(null);
const selectedProduct = ref(null);
const isModalOpen = ref(false);
const isEditModalOpen = ref(false);
const imagePreview = ref(null);
const isDragging = ref(false);
const selectedModule = ref(null);
const salesData = ref([]);
const salesLoading = ref(false);
const salesDateRange = ref({
  start: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
  end: new Date().toISOString().split('T')[0]
});
const isEditBranchModalOpen = ref(false);
const isNewBranchModalOpen = ref(false);
const isConfirmDeleteModalOpen = ref(false);

// Edit form
const editForm = useForm({
  name: '',
  category_id: '',
  branch_id: '',
  barcode: '',
  price: '',
  stock_quantity: '',
  image: null,
});

const editBranchForm = useForm({
    name: '',
    location: '',
});

const newBranchForm = useForm({
    name: '',
    location: '',
    contact_number: '',
});

// Compute filtered products for selected branch
const filteredProducts = computed(() => {
  if (!selectedBranch.value) return [];
  
  const branch = branches.find(b => b.id === selectedBranch.value);
  if (!branch) return [];

  return selectedCategory.value === 'all' 
    ? branch.products
    : branch.products.filter(p => p.category_id == selectedCategory.value);
});

// Visible products based on current visibleCount
const visibleProducts = computed(() => {
  return filteredProducts.value.slice(0, visibleCount.value);
});

// Get current branch name
const currentBranchName = computed(() => {
  if (!selectedBranch.value) return '';
  const branch = branches.find(b => b.id === selectedBranch.value);
  return branch ? `${branch.name} - ${branch.location}` : '';
});

// Show more products (increment visibleCount by 5)
const showMore = () => {
  visibleCount.value += 5;
};

// Open modal with product details
const openModal = (product) => {
  selectedProduct.value = product;
  isModalOpen.value = true;
};

// Close modal
const closeModal = () => {
  isModalOpen.value = false;
  selectedProduct.value = null;
};

// Open edit modal
const openEditModal = (product) => {
  selectedProduct.value = product;
  editForm.name = product.name;
  editForm.category_id = product.category_id;
  editForm.branch_id = product.branch_id;
  editForm.barcode = product.barcode;
  editForm.price = product.price;
  editForm.stock_quantity = product.stock_quantity;
  imagePreview.value = `/storage/${product.image}`;
  isEditModalOpen.value = true;
};

// Close edit modal
const closeEditModal = () => {
  isEditModalOpen.value = false;
  selectedProduct.value = null;
  imagePreview.value = null;
  editForm.reset();
};

// Handle image upload for edit
const handleImageUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    editForm.image = file;
    createImagePreview(file);
  }
};

// Create image preview
const createImagePreview = (file) => {
  if (!file) return;
  
  const reader = new FileReader();
  reader.onload = (e) => {
    imagePreview.value = e.target.result;
  };
  reader.readAsDataURL(file);
};

// Handle drag and drop for edit
const handleDragOver = (event) => {
  event.preventDefault();
  isDragging.value = true;
};

const handleDragLeave = () => {
  isDragging.value = false;
};

const handleDrop = (event) => {
  event.preventDefault();
  isDragging.value = false;
  
  if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
    const file = event.dataTransfer.files[0];
    if (file.type.startsWith('image/')) {
      editForm.image = file;
      createImagePreview(file);
    } else {
      toast.error('Please upload an image file');
    }
  }
};

// Remove image
const removeImage = () => {
  editForm.image = null;
  imagePreview.value = null;
};

// Select branch and reset filters
const selectBranch = (branchId) => {
  console.log('selectBranch called with branchId:', branchId);
  console.log('Available branches:', branches);
  
  const branch = branches.find(b => b.id === branchId);
  console.log('Found branch:', branch);
  
  if (!branch) {
    toast.error('Branch not found');
    return;
  }
  selectedBranch.value = branchId;
  selectedModule.value = null;
  console.log('Selected branch set to:', selectedBranch.value);
};

// Select module (Products, Sales, etc.)
const selectModule = (module) => {
  selectedModule.value = module;
  if (module === 'products') {
    selectedCategory.value = 'all';
    visibleCount.value = 5;
  } else if (module === 'sales') {
    loadSalesData();
  }
};

// Compute sales summary
const salesSummary = computed(() => {
  if (!salesData.value.length) return {
    totalSales: 0,
    totalItems: 0,
    averageOrderValue: 0
  };

  const totalSales = Number(salesData.value.reduce((sum, sale) => sum + Number(sale.total), 0));
  const totalItems = Number(salesData.value.reduce((sum, sale) => sum + sale.items.reduce((itemSum, item) => itemSum + Number(item.quantity), 0), 0));
  
  return {
    totalSales,
    totalItems,
    averageOrderValue: totalSales / salesData.value.length
  };
});

// Load sales data
const loadSalesData = async () => {
  if (!selectedBranch.value) return;
  
  salesLoading.value = true;
  try {
    console.log('Fetching sales with params:', {
      branch_id: selectedBranch.value,
      start_date: salesDateRange.value.start,
      end_date: salesDateRange.value.end
    });
    
    const response = await axios.get(route('sales.index'), {
      params: {
        branch_id: selectedBranch.value,
        start_date: salesDateRange.value.start,
        end_date: salesDateRange.value.end
      }
    });
    
    console.log('Sales API response:', response.data);
    
    if (response.data && response.data.success && response.data.sales) {
      // Convert numeric values to numbers
      salesData.value = response.data.sales.map(sale => ({
        ...sale,
        total: Number(sale.total),
        items: sale.items.map(item => ({
          ...item,
          quantity: Number(item.quantity),
          price: Number(item.price)
        }))
      }));
      toast.success(`Loaded ${response.data.sales.length} sales records`);
    } else {
      console.error('Invalid response format:', response.data);
      toast.error(response.data?.message || 'Invalid response format from server');
    }
  } catch (error) {
    console.error('Error fetching sales:', error);
    console.error('Error response:', error.response);
    toast.error(error.response?.data?.message || 'Failed to load sales data');
  } finally {
    salesLoading.value = false;
  }
};

// Update product
const updateProduct = () => {
  if (!selectedBranch.value) {
    toast.error('Branch not selected');
    return;
  }
  
  const formData = new FormData();
  
  // Add _method field for PUT request
  formData.append('_method', 'PUT');
  
  // Append all form fields
  formData.append('name', editForm.name);
  formData.append('category_id', editForm.category_id);
  formData.append('branch_id', editForm.branch_id);
  formData.append('barcode', editForm.barcode);
  formData.append('price', editForm.price);
  formData.append('stock_quantity', editForm.stock_quantity);
  if (editForm.image) {
    formData.append('image', editForm.image);
  }
  
  axios.post(route('products.update', selectedProduct.value.id), formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
  .then(response => {
    toast.success('Product updated successfully!');
    closeEditModal();
    // Refresh the page to show updated data
    window.location.reload();
  })
  .catch(error => {
    editForm.errors = error.response.data.errors;
    if (error.response.data.message) {
      toast.error(error.response.data.message);
    } else {
      toast.error('An error occurred while updating the product.');
    }
  });
};

// Check if user has access to a branch
const hasBranchAccess = (branchId) => {
  console.log('hasBranchAccess called with branchId:', branchId);
  const branch = branches.find(b => b.id === branchId);
  console.log('Found branch for access check:', branch);
  if (!branch) return false;
  return true;
};

const openEditBranchModal = (branch) => {
    const selectedBranchData = branches.find(b => b.id === selectedBranch.value);
    if (selectedBranchData) {
        editBranchForm.name = selectedBranchData.name;
        editBranchForm.location = selectedBranchData.location;
        isEditBranchModalOpen.value = true;
    }
};

const closeEditBranchModal = () => {
    isEditBranchModalOpen.value = false;
    editBranchForm.reset();
};

const updateBranch = () => {
    editBranchForm.put(route('branches.update', selectedBranch.value), {
        onSuccess: () => {
            closeEditBranchModal();
            toast.success('Branch updated successfully');
        },
        onError: () => {
            toast.error('Failed to update branch');
        }
    });
};

const openNewBranchModal = () => {
    isNewBranchModalOpen.value = true;
};

const closeNewBranchModal = () => {
    isNewBranchModalOpen.value = false;
    newBranchForm.reset();
};

const createBranch = () => {
    newBranchForm.post(route('branches.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeNewBranchModal();
            // Refresh the page to get updated data
            window.location.reload();
            toast.success('Branch created successfully');
        },
        onError: () => {
            toast.error('Failed to create branch');
        }
    });
};

// Add the confirm delete function
const confirmDelete = () => {
    router.delete(route('branches.destroy', selectedBranch.value), {
        onSuccess: () => {
            isConfirmDeleteModalOpen.value = false;
            selectedBranch.value = null;
            toast.success('Branch deleted successfully');
        },
        onError: (error) => {
            toast.error(error.message || 'Failed to delete branch');
        }
    });
};
</script>

<template>
  <AppLayout title="ABOUT">
    <template #header>
      <div class="flex justify-between items-center">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Branch Management
      </h2>
        <div class="text-sm text-gray-500 dark:text-gray-400">
          Manage your branch operations
        </div>
      </div>
    </template>

    <div class="py-12 bg-gray-100 dark:bg-gray-900">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Module Selection -->
        <div v-if="selectedBranch && !selectedModule" class="mb-8">
          <div class="mb-6 flex justify-between items-center">
            <div>
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Select Module</h3>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Choose a module to manage branch operations.
              </p>
            </div>
            <div class="flex items-center space-x-2">
                <button
                    @click="openEditBranchModal"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200 flex items-center space-x-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span>Edit Branch</span>
                </button>
                <button
                    @click="isConfirmDeleteModalOpen = true"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 flex items-center space-x-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span>Delete Branch</span>
                </button>
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Products Module Card - Only visible to Admin and Warehouse -->
            <div
              v-if="selectedBranch"
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

            <!-- Sales Module Card - Only visible to Admin and Cashier -->
            <div
              v-if="selectedBranch"
              @click="selectModule('sales')"
              class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer border-2 border-transparent hover:border-indigo-500"
            >
              <div class="flex items-center">
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                  <svg class="h-6 w-6 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <h4 class="ml-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Sales</h4>
              </div>
              <p class="mt-4 text-gray-600 dark:text-gray-400">
                View sales reports and analytics
              </p>
            </div>
          </div>
        </div>

        <!-- Branch Selection - Only show branches user has access to -->
        <div v-if="!selectedBranch" class="mb-8">
          
          <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Select a Branch</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Choose a branch to view its details
                </p>
            </div>
            <button
                @click="openNewBranchModal"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200 flex items-center space-x-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add New Branch</span>
            </button>
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

        <!-- Products Module - Only visible to Admin and Warehouse -->
        <div v-if="selectedModule === 'products' && (selectedBranch)" class="space-y-6">
          <!-- Module Header -->
          <div class="flex justify-between items-center">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
              Products - {{ currentBranchName }}
            </h3>
            <div class="flex space-x-2">
              <button
                @click="selectedModule = null"
                class="px-4 py-2 text-sm bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition"
              >
                Back to Modules
              </button>
              <button
                @click="selectedBranch = null"
                class="px-4 py-2 text-sm bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition"
              >
                Change Branch
              </button>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6">
          <!-- Filter Dropdown -->
            <div class="mb-6 flex items-center">
              <label for="category" class="mr-2 font-medium text-gray-700 dark:text-gray-300">Filter by Category:</label>
              <select
                v-model="selectedCategory"
                id="category"
                class="bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 text-sm rounded-lg focus:ring-indigo-500 dark:focus:ring-indigo-600 focus:border-indigo-500 dark:focus:border-indigo-600 block w-48 p-2.5"
              >
                <option value="all" class="bg-gray-50 dark:bg-gray-800">All Categories</option>
                <option 
                  v-for="(name, id) in categories" 
                  :key="id" 
                  :value="id"
                  class="bg-gray-50 dark:bg-gray-800"
                >
                  {{ name }}
                </option>
              </select>
            </div>

          <!-- Loading Skeleton -->
          <div v-if="loading" class="space-y-4">
              <div v-for="n in 5" :key="n" class="animate-pulse flex space-x-4 p-4 border dark:border-gray-700 rounded-lg">
                <div class="rounded bg-gray-200 dark:bg-gray-700 h-24 w-24"></div>
                <div class="flex-1 space-y-3 py-1">
                  <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                  <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                  <div class="space-x-3">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/6 inline-block"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/6 inline-block"></div>
                  </div>
                </div>
                <div class="w-24 flex items-center">
                  <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded w-full"></div>
                </div>
            </div>
          </div>

            <!-- Products List -->
          <div v-else>
              <div v-if="visibleProducts.length > 0" class="space-y-4">
              <div
                v-for="product in visibleProducts"
                :key="product.id"
                  class="flex space-x-4 p-4 border dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-750 transition cursor-pointer"
                @click="openModal(product)"
              >
                  <div class="w-24 h-24 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden">
                  <img
                    :src="`/storage/${product.image}`"
                    alt="Product Image"
                      class="w-full h-full object-cover"
                  />
                </div>
                  <div class="flex-1">
                  <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ product.name }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">ID: {{ product.id }}</p>
                    <div class="mt-2 space-x-4">
                      <span class="text-sm text-gray-600 dark:text-gray-400">
                        Stock: {{ product.stock_quantity }}
                      </span>
                      <span class="text-sm text-gray-600 dark:text-gray-400">
                        Barcode: {{ product.barcode }}
                      </span>
                      <span class="text-sm text-gray-600 dark:text-gray-400">
                        Category: {{ product.category?.name }}
                      </span>
                    </div>
                  </div>
                  <div class="flex items-center">
                    <span class="text-green-600 dark:text-green-400 font-medium text-lg">${{ product.price }}</span>
                  </div>
                </div>
              </div>
              <div v-else class="text-center text-gray-500 dark:text-gray-400 mt-6">
                No products found for selected category.
            </div>

            <div class="mt-6 text-center">
              <button
                v-if="visibleProducts.length < filteredProducts.length"
                @click="showMore"
                  class="px-4 py-2 bg-indigo-600 dark:bg-indigo-700 text-white rounded hover:bg-indigo-700 dark:hover:bg-indigo-800"
              >
                Show More
              </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Sales Module - Only visible to Admin and Cashier -->
        <div v-if="selectedModule === 'sales' && (selectedBranch)" class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <div class="mb-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Sales Report</h3>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ currentBranchName }}
              </p>
            </div>

            <!-- Date Range Selection -->
            <div class="mb-6">
              <div class="flex gap-4 mb-4">
                <div class="flex-1">
                  <InputLabel value="Start Date" />
                  <TextInput
                    type="date"
                    v-model="salesDateRange.start"
                    class="mt-1 block w-full"
                  />
                </div>
                <div class="flex-1">
                  <InputLabel value="End Date" />
                  <TextInput
                    type="date"
                    v-model="salesDateRange.end"
                    class="mt-1 block w-full"
                  />
                </div>
              </div>
              <div class="flex justify-end">
                <button
                  @click="loadSalesData"
                  class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  :disabled="salesLoading"
                >
                  <span v-if="salesLoading">Loading...</span>
                  <span v-else>Fetch Sales</span>
                </button>
              </div>
            </div>

            <!-- Sales Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sales</h4>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                  ₱{{ salesSummary.totalSales.toFixed(2) }}
                </p>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Items Sold</h4>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                  {{ salesSummary.totalItems }}
                </p>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Average Order Value</h4>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                  ₱{{ salesSummary.averageOrderValue.toFixed(2) }}
                </p>
              </div>
            </div>

            <!-- Sales Table -->
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Products</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-if="salesLoading">
                    <td colspan="3" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                      Loading sales data...
                    </td>
                  </tr>
                  <tr v-else-if="!salesData.length">
                    <td colspan="3" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                      No sales data available for the selected period
                    </td>
                  </tr>
                  <tr v-for="sale in salesData" :key="sale.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                      {{ new Date(sale.created_at).toLocaleDateString() }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                      <div class="space-y-1">
                        <div v-for="item in sale.items" :key="item.id" class="flex justify-between">
                          <span>{{ item.product.name }}</span>
                          <span class="text-gray-500 dark:text-gray-400">x{{ item.quantity }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                      ₱{{ Number(sale.total).toFixed(2) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Reports View (when Reports module is selected) -->
        <div v-else-if="selectedModule === 'reports'" class="space-y-6">
          <!-- Module Header -->
          <div class="flex justify-between items-center">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
              Reports - {{ currentBranchName }}
            </h3>
            <div class="flex space-x-2">
              <button
                @click="selectedModule = null"
                class="px-4 py-2 text-sm bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition"
              >
                Back to Modules
              </button>
              <button
                @click="selectedBranch = null"
                class="px-4 py-2 text-sm bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition"
              >
                Change Branch
              </button>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6">
            <div class="text-center py-12">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">Reports Module</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                This module is currently under development.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- View Modal -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-80 flex items-center justify-center z-50"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-11/12 sm:w-3/4 lg:w-1/2 p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ selectedProduct?.name }}</h3>
          <div class="flex space-x-2">
            <button 
              @click="openEditModal(selectedProduct)" 
              class="px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm"
            >
              Edit
            </button>
          <button @click="closeModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">&times;</button>
          </div>
        </div>
        <div class="flex flex-col sm:flex-row">
          <img
            :src="`/storage/${selectedProduct?.image}`"
            alt="Product Image"
            class="w-full sm:w-1/2 h-64 object-contain bg-gray-100 dark:bg-gray-700 rounded"
          />
          <div class="sm:ml-6 mt-4 sm:mt-0 space-y-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="font-medium text-gray-700 dark:text-gray-300">Barcode:</p>
                <p class="text-gray-600 dark:text-gray-400">{{ selectedProduct?.barcode }}</p>
              </div>
              <div>
                <p class="font-medium text-gray-700 dark:text-gray-300">Stock Quantity:</p>
                <p class="text-gray-600 dark:text-gray-400">{{ selectedProduct?.stock_quantity }}</p>
              </div>
              <div>
                <p class="font-medium text-gray-700 dark:text-gray-300">Category:</p>
                <p class="text-gray-600 dark:text-gray-400">{{ selectedProduct?.category?.name }}</p>
              </div>
            </div>
            <p class="text-green-600 dark:text-green-400 font-medium text-lg">
              ${{ selectedProduct?.price }}
            </p>
          </div>
        </div>
        <div class="mt-6 text-right">
          <button @click="closeModal" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-700 text-white rounded hover:bg-indigo-700 dark:hover:bg-indigo-800">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Modal - Only visible to Admin and Warehouse -->
    <div
      v-if="isEditModalOpen && (selectedBranch)"
      class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-80 flex items-center justify-center z-50"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-11/12 sm:w-3/4 lg:w-1/2 p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Edit Product</h3>
          <button @click="closeEditModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">&times;</button>
        </div>
        
        <form @submit.prevent="updateProduct" class="space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Product Name -->
            <div>
              <InputLabel for="edit_name" value="Product Name" />
              <TextInput
                id="edit_name"
                v-model="editForm.name"
                type="text"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                required
              />
              <InputError class="mt-2" :message="editForm.errors.name" />
            </div>

            <!-- Barcode -->
            <div>
              <InputLabel for="edit_barcode" value="Barcode" />
              <TextInput
                id="edit_barcode"
                v-model="editForm.barcode"
                type="text"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                required
              />
              <InputError class="mt-2" :message="editForm.errors.barcode" />
            </div>

            <!-- Category Selection -->
            <div>
              <InputLabel for="edit_category_id" value="Category" />
              <select
                id="edit_category_id"
                v-model="editForm.category_id"
                class="mt-1 block w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-indigo-500 dark:focus:ring-indigo-600 focus:border-indigo-500 dark:focus:border-indigo-600 p-2.5"
                required
              >
                <option value="" class="bg-gray-50 dark:bg-gray-800">Select Category</option>
                <option 
                  v-for="(name, id) in categories"
                  :key="id"
                  :value="id"
                  class="bg-gray-50 dark:bg-gray-800"
                >
                  {{ name }}
                </option>
              </select>
              <InputError class="mt-2" :message="editForm.errors.category_id" />
            </div>

            <!-- Branch Selection -->
            <div>
              <InputLabel for="edit_branch_id" value="Branch" />
              <select
                id="edit_branch_id"
                v-model="editForm.branch_id"
                class="mt-1 block w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-indigo-500 dark:focus:ring-indigo-600 focus:border-indigo-500 dark:focus:border-indigo-600 p-2.5"
                required
              >
                <option value="" class="bg-gray-50 dark:bg-gray-800">Select Branch</option>
                <option 
                  v-for="branch in branches"
                  :key="branch.id"
                  :value="branch.id"
                  class="bg-gray-50 dark:bg-gray-800"
                >
                  {{ branch.name }} - {{ branch.location }}
                </option>
              </select>
              <InputError class="mt-2" :message="editForm.errors.branch_id" />
            </div>

            <!-- Price -->
            <div>
              <InputLabel for="edit_price" value="Price" />
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-gray-500 dark:text-gray-400 sm:text-sm">$</span>
                </div>
                <TextInput
                  id="edit_price"
                  v-model="editForm.price"
                  type="number"
                  step="0.01"
                  class="pl-7 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                  required
                />
              </div>
              <InputError class="mt-2" :message="editForm.errors.price" />
            </div>

            <!-- Stock Quantity -->
            <div>
              <InputLabel for="edit_stock_quantity" value="Stock Quantity" />
              <TextInput
                id="edit_stock_quantity"
                v-model="editForm.stock_quantity"
                type="number"
                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                required
              />
              <InputError class="mt-2" :message="editForm.errors.stock_quantity" />
            </div>
          </div>

          <!-- Image Upload -->
          <div class="mt-6">
            <InputLabel for="edit_image" value="Product Image" />
            <div 
              class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-700 border-dashed rounded-md"
              :class="{ 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20': isDragging }"
              @dragover="handleDragOver"
              @dragleave="handleDragLeave"
              @drop="handleDrop"
            >
              <div v-if="!imagePreview" class="space-y-1 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                  <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <div class="flex text-sm text-gray-600 dark:text-gray-400">
                  <label for="edit_image" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                    <span>Upload a file</span>
                    <input 
                      id="edit_image" 
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
              <div v-else class="relative w-full">
                <img :src="imagePreview" class="mx-auto h-48 w-auto object-contain rounded-md" alt="Product preview" />
                <button 
                  type="button" 
                  @click="removeImage"
                  class="absolute top-0 right-0 -mt-2 -mr-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 focus:outline-none"
                >
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
            <InputError class="mt-2" :message="editForm.errors.image" />
          </div>

          <div class="flex items-center justify-end mt-6">
            <button 
              type="button" 
              @click="closeEditModal"
              class="mr-3 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600"
            >
              Cancel
            </button>
            <PrimaryButton 
              class="px-6 py-2"
              :disabled="editForm.processing"
            >
              Update Product
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Branch Modal -->
    <div
      v-if="isEditBranchModalOpen"
      class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-80 flex items-center justify-center z-50"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-11/12 sm:w-3/4 lg:w-1/2 p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Edit Branch</h3>
          <button @click="closeEditBranchModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">&times;</button>
        </div>
        
        <form @submit.prevent="updateBranch" class="space-y-6">
          <div>
            <InputLabel for="branch_name" value="Branch Name" />
            <TextInput
              id="branch_name"
              v-model="editBranchForm.name"
              type="text"
              class="mt-1 block w-full"
              required
            />
            <InputError class="mt-2" :message="editBranchForm.errors.name" />
          </div>

          <div>
            <InputLabel for="branch_location" value="Location" />
            <TextInput
              id="branch_location"
              v-model="editBranchForm.location"
              type="text"
              class="mt-1 block w-full"
              required
            />
            <InputError class="mt-2" :message="editBranchForm.errors.location" />
          </div>

          <div class="flex items-center justify-end mt-6">
            <button 
              type="button" 
              @click="closeEditBranchModal"
              class="mr-3 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600"
            >
              Cancel
            </button>
            <PrimaryButton 
              class="px-6 py-2"
              :disabled="editBranchForm.processing"
            >
              Update Branch
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>

    <!-- New Branch Modal -->
    <div
        v-if="isNewBranchModalOpen"
        class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-80 flex items-center justify-center z-50"
    >
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-11/12 sm:w-3/4 lg:w-1/2 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Add New Branch</h3>
                <button @click="closeNewBranchModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">&times;</button>
            </div>
            
            <form @submit.prevent="createBranch" class="space-y-6">
                <div>
                    <InputLabel for="branch_name" value="Branch Name" />
                    <TextInput
                        id="branch_name"
                        v-model="newBranchForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="newBranchForm.errors.name" />
                </div>

                <div>
                    <InputLabel for="branch_location" value="Location" />
                    <TextInput
                        id="branch_location"
                        v-model="newBranchForm.location"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="newBranchForm.errors.location" />
                </div>

                <div>
                    <InputLabel for="contact_number" value="Contact Number" />
                    <TextInput
                        id="contact_number"
                        v-model="newBranchForm.contact_number"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="newBranchForm.errors.contact_number" />
                </div>

                <div class="flex items-center justify-end mt-6">
                    <button 
                        type="button" 
                        @click="closeNewBranchModal"
                        class="mr-3 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>
                    <PrimaryButton 
                        class="px-6 py-2"
                        :disabled="newBranchForm.processing"
                    >
                        Create Branch
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
        v-if="isConfirmDeleteModalOpen"
        class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-80 flex items-center justify-center z-50"
    >
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-11/12 sm:w-96 p-6">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">Delete Branch</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete this branch? This action cannot be undone.
                </p>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button
                    @click="isConfirmDeleteModalOpen = false"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600"
                >
                    Cancel
                </button>
                <button
                    @click="confirmDelete"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                >
                    Delete
                </button>
            </div>
        </div>
    </div>
  </AppLayout>
</template>