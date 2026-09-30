import React, { useState, useEffect, useMemo } from 'react';
import { useAdminAuth } from '../../context/AuthContext.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';
import Select from 'react-select';
import AsyncSelect from 'react-select/async';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const customSelectStyles = {
  control: (base, state) => ({
    ...base,
    borderColor: state.isFocused ? 'var(--primary-color, #A049A3)' : '#d1d5db',
    boxShadow: state.isFocused ? '0 0 0 1px var(--primary-color, #A049A3)' : 'none',
    borderRadius: '6px',
    fontSize: '0.88rem',
    minHeight: '38px',
    backgroundColor: '#fff',
    '&:hover': {
      borderColor: state.isFocused ? 'var(--primary-color, #A049A3)' : '#9ca3af'
    }
  }),
  clearIndicator: (base) => ({
    ...base,
    cursor: 'pointer',
    color: '#9ca3af',
    padding: '4px',
    '&:hover': { color: '#be123c' }
  }),
  menu: (base) => ({
    ...base,
    zIndex: 99999,
    boxShadow: '0 4px 14px rgba(0,0,0,0.12)',
    borderRadius: '8px',
    fontSize: '0.88rem'
  }),
  menuPortal: (base) => ({
    ...base,
    zIndex: 99999
  }),
  option: (base, state) => ({
    ...base,
    fontSize: '0.86rem',
    backgroundColor: state.isSelected
      ? 'var(--primary-color, #A049A3)'
      : state.isFocused
        ? '#f3e8ff'
        : 'transparent',
    color: state.isSelected ? '#fff' : '#1f2937',
    cursor: 'pointer'
  })
};

const getImageUrl = (imagePath) => {
  if (!imagePath) return '';
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://') || imagePath.startsWith('data:')) {
    return imagePath;
  }
  const cleanPath = imagePath.startsWith('/') ? imagePath.slice(1) : imagePath;
  const backendRoot = API_BASE_URL.replace(/\/api\/?$/, '');
  return `${backendRoot}/${cleanPath}`;
};

const AdminVariants = () => {
  const { token } = useAdminAuth();

  // Data States
  const [variants, setVariants] = useState([]);
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [sizes, setSizes] = useState([]);
  const [colours, setColours] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

  // Filter & Search States
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedSearchOption, setSelectedSearchOption] = useState(null);
  const [productFilter, setProductFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [colorFilter, setColorFilter] = useState('');
  const [sizeFilter, setSizeFilter] = useState('');
  const [stockStatusFilter, setStockStatusFilter] = useState('');
  const [discountTypeFilter, setDiscountTypeFilter] = useState('');
  const [collectionFilter, setCollectionFilter] = useState('');
  const [sortBy, setSortBy] = useState('created_at');
  const [sortOrder, setSortOrder] = useState('desc');

  // Dedicated Collection Maps (New Arrival & Top Selling)
  const [newArrivalMap, setNewArrivalMap] = useState({});
  const [topSellingMap, setTopSellingMap] = useState({});
  const [collectionActionLoading, setCollectionActionLoading] = useState({});

  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);
  const perPage = 10;

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState('add'); // 'add', 'edit', 'view', 'images'
  const [modalLoading, setModalLoading] = useState(false);
  const [modalError, setModalError] = useState('');
  const [isSaving, setIsSaving] = useState(false);
  const [settingPrimaryId, setSettingPrimaryId] = useState(null);

  // Selected Variant Details for View / Quick Image Modal
  const [activeVariantDetail, setActiveVariantDetail] = useState(null);

  // Form State for Create & Edit
  const [formData, setFormData] = useState({
    id: '',
    product_id: '',
    size_id: '',
    color_id: '',
    variant_name: '',
    original_price: '0.00',
    gst_rate: '0.00',
    discount_type: 'none',
    discount_value: '0',
    stock_quantity: '0',
    reserved_quantity: '0',
    low_stock_limit: '5',
    is_available: true
  });

  // Images for Create / Edit
  const [newUploads, setNewUploads] = useState([]);
  const [existingImages, setExistingImages] = useState([]);

  // Fetch Dropdown Dependencies & Collections Metadata
  const fetchCollectionsData = async () => {
    try {
      const headers = { 'Authorization': `Bearer ${token}` };
      const [naRes, tsRes] = await Promise.all([
        fetch(`${API_BASE_URL}/new_arrival/list.php?status=active&limit=100`, { headers }),
        fetch(`${API_BASE_URL}/top_selling/list.php?status=active&limit=100`, { headers })
      ]);
      const naData = await naRes.json();
      const tsData = await tsRes.json();

      let naList = (naData.status || naData.success) && Array.isArray(naData.data?.new_arrivals)
        ? [...naData.data.new_arrivals]
        : [];
      let tsList = (tsData.status || tsData.success) && Array.isArray(tsData.data?.top_selling)
        ? [...tsData.data.top_selling]
        : [];

      // If more than 100 items exist, fetch remaining pages
      const naTotalPages = naData.data?.pagination?.total_pages || 1;
      if (naTotalPages > 1) {
        const extraNa = [];
        for (let p = 2; p <= naTotalPages; p++) {
          extraNa.push(
            fetch(`${API_BASE_URL}/new_arrival/list.php?status=active&limit=100&page=${p}`, { headers })
              .then(r => r.json())
              .then(d => (d.status || d.success) && d.data?.new_arrivals ? d.data.new_arrivals : [])
              .catch(() => [])
          );
        }
        const extraNaResults = await Promise.all(extraNa);
        extraNaResults.forEach(items => { naList.push(...items); });
      }

      const tsTotalPages = tsData.data?.pagination?.total_pages || 1;
      if (tsTotalPages > 1) {
        const extraTs = [];
        for (let p = 2; p <= tsTotalPages; p++) {
          extraTs.push(
            fetch(`${API_BASE_URL}/top_selling/list.php?status=active&limit=100&page=${p}`, { headers })
              .then(r => r.json())
              .then(d => (d.status || d.success) && d.data?.top_selling ? d.data.top_selling : [])
              .catch(() => [])
          );
        }
        const extraTsResults = await Promise.all(extraTs);
        extraTsResults.forEach(items => { tsList.push(...items); });
      }

      const naMap = {};
      naList.forEach(item => {
        if (item.product_variant_id) {
          naMap[item.product_variant_id] = item;
        }
      });
      setNewArrivalMap(naMap);

      const tsMap = {};
      tsList.forEach(item => {
        if (item.product_variant_id) {
          tsMap[item.product_variant_id] = item;
        }
      });
      setTopSellingMap(tsMap);
    } catch (err) {
      console.error('Failed to load collections metadata:', err);
    }
  };

  const fetchDependencies = async () => {
    try {
      const headers = { 'Authorization': `Bearer ${token}` };
      const [prodRes, catRes, sizeRes, colRes] = await Promise.all([
        fetch(`${API_BASE_URL}/product/list.php?status=active&limit=100`, { headers }),
        fetch(`${API_BASE_URL}/category/list.php?limit=100`, { headers }),
        fetch(`${API_BASE_URL}/size/list.php?status=active&limit=100`, { headers }),
        fetch(`${API_BASE_URL}/colour/list.php?status=active&limit=100`, { headers })
      ]);

      const prods = await prodRes.json();
      const cats = await catRes.json();
      const szs = await sizeRes.json();
      const cols = await colRes.json();

      if (prods.status && prods.data) setProducts(prods.data.products || []);
      if (cats.status && cats.data) setCategories(cats.data.categories || []);
      if (szs.status && szs.data) setSizes(szs.data.sizes || []);
      if (cols.status && cols.data) setColours(cols.data.colors || []);
    } catch (err) {
      console.error('Failed to load variant master dependencies:', err);
    }
  };

  // Fetch Variants List with all supported query parameters
  const fetchVariants = async (page = 1, searchOverride = null) => {
    setIsLoading(true);
    setError('');
    try {
      const queryParams = new URLSearchParams({
        page: page.toString(),
        limit: perPage.toString(),
        sort_by: sortBy,
        sort_order: sortOrder
      });

      const qVal = searchOverride !== null ? searchOverride : searchQuery;
      if (qVal.trim()) queryParams.append('q', qVal.trim());
      if (productFilter) queryParams.append('product_id', productFilter);
      if (categoryFilter) queryParams.append('category_id', categoryFilter);
      if (colorFilter) queryParams.append('color_id', colorFilter);
      if (sizeFilter) queryParams.append('size_id', sizeFilter);
      if (stockStatusFilter) queryParams.append('stock_status', stockStatusFilter);
      if (discountTypeFilter) queryParams.append('discount_type', discountTypeFilter);
      if (collectionFilter === 'is_new_arrival') queryParams.append('is_new_arrival', '1');
      if (collectionFilter === 'is_featured') queryParams.append('is_featured', '1');
      if (collectionFilter === 'is_best_seller') queryParams.append('is_best_seller', '1');

      const response = await fetch(`${API_BASE_URL}/varient/list.php?${queryParams.toString()}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();

      if (result.status && result.data) {
        setVariants(result.data.variants || []);
        if (result.data.pagination) {
          setCurrentPage(result.data.pagination.page || result.data.pagination.current_page || 1);
          setTotalPages(result.data.pagination.total_pages || 1);
          setTotalRecords(result.data.pagination.total_records || 0);
        }
      } else {
        setVariants([]);
        setError(result.message || 'Failed to fetch variants.');
      }
    } catch (err) {
      console.error('Error fetching variants:', err);
      setError('Network error: Unable to load variants from server.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (token) {
      fetchDependencies();
      fetchCollectionsData();
    }
  }, [token]);

  useEffect(() => {
    if (token) {
      fetchVariants(1);
    }
  }, [token, productFilter, categoryFilter, colorFilter, sizeFilter, stockStatusFilter, discountTypeFilter, collectionFilter, sortBy, sortOrder]);

  // Variant-Level Toggle Handlers
  const handleToggleNewArrival = async (variant) => {
    const variantId = variant.id;
    const existing = newArrivalMap[variantId];
    setCollectionActionLoading(prev => ({ ...prev, [variantId]: 'new_arrival' }));
    setError('');
    setSuccessMessage('');

    try {
      if (existing && existing.status === 'active') {
        // Deactivate / Remove from New Arrival
        const res = await fetch(`${API_BASE_URL}/new_arrival/update.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            id: existing.id,
            status: 'inactive'
          })
        });
        const result = await res.json();
        if (result.status) {
          setNewArrivalMap(prev => {
            const next = { ...prev };
            delete next[variantId];
            return next;
          });
          setSuccessMessage(`Variant "${variant.sku}" removed from New Arrivals.`);
          setTimeout(() => setSuccessMessage(''), 3000);
        } else {
          setError(result.message || 'Failed to update New Arrival status.');
        }
      } else {
        // Add to New Arrival
        const res = await fetch(`${API_BASE_URL}/new_arrival/create.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            product_variant_id: variantId,
            sort_order: 0,
            status: 'active'
          })
        });
        const result = await res.json();
        if (result.status) {
          setNewArrivalMap(prev => ({
            ...prev,
            [variantId]: {
              id: result.data?.new_arrival?.id || result.data?.id,
              product_variant_id: variantId,
              status: 'active'
            }
          }));
          setSuccessMessage(`Variant "${variant.sku}" marked as New Arrival.`);
          setTimeout(() => setSuccessMessage(''), 3000);
        } else if (result.data?.existing_new_arrival?.id) {
          // If already in table as inactive, reactivate it!
          const reactivateRes = await fetch(`${API_BASE_URL}/new_arrival/update.php`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
              id: result.data.existing_new_arrival.id,
              status: 'active'
            })
          });
          const reactivateData = await reactivateRes.json();
          if (reactivateData.status) {
            setNewArrivalMap(prev => ({
              ...prev,
              [variantId]: {
                id: result.data.existing_new_arrival.id,
                product_variant_id: variantId,
                status: 'active'
              }
            }));
            setSuccessMessage(`Variant "${variant.sku}" marked as New Arrival.`);
            setTimeout(() => setSuccessMessage(''), 3000);
          } else {
            setError(reactivateData.message || 'Failed to activate New Arrival.');
          }
        } else {
          setError(result.message || 'Failed to mark as New Arrival.');
        }
      }
    } catch (err) {
      console.error('Error toggling New Arrival:', err);
      setError('Network error while toggling New Arrival.');
    } finally {
      setCollectionActionLoading(prev => ({ ...prev, [variantId]: null }));
    }
  };

  const handleToggleTopSelling = async (variant) => {
    const variantId = variant.id;
    const existing = topSellingMap[variantId];
    setCollectionActionLoading(prev => ({ ...prev, [variantId]: 'top_selling' }));
    setError('');
    setSuccessMessage('');

    try {
      if (existing && existing.status === 'active') {
        // Deactivate / Remove from Top Selling
        const res = await fetch(`${API_BASE_URL}/top_selling/update.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            id: existing.id,
            status: 'inactive'
          })
        });
        const result = await res.json();
        if (result.status) {
          setTopSellingMap(prev => {
            const next = { ...prev };
            delete next[variantId];
            return next;
          });
          setSuccessMessage(`Variant "${variant.sku}" removed from Top Selling.`);
          setTimeout(() => setSuccessMessage(''), 3000);
        } else {
          setError(result.message || 'Failed to update Top Selling status.');
        }
      } else {
        // Add to Top Selling
        const res = await fetch(`${API_BASE_URL}/top_selling/create.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            product_variant_id: variantId,
            sort_order: 0,
            status: 'active'
          })
        });
        const result = await res.json();
        if (result.status) {
          setTopSellingMap(prev => ({
            ...prev,
            [variantId]: {
              id: result.data?.top_selling?.id || result.data?.id,
              product_variant_id: variantId,
              status: 'active'
            }
          }));
          setSuccessMessage(`Variant "${variant.sku}" marked as Top Selling.`);
          setTimeout(() => setSuccessMessage(''), 3000);
        } else if (result.data?.existing_top_selling?.id) {
          // If already in table as inactive, reactivate it!
          const reactivateRes = await fetch(`${API_BASE_URL}/top_selling/update.php`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
              id: result.data.existing_top_selling.id,
              status: 'active'
            })
          });
          const reactivateData = await reactivateRes.json();
          if (reactivateData.status) {
            setTopSellingMap(prev => ({
              ...prev,
              [variantId]: {
                id: result.data.existing_top_selling.id,
                product_variant_id: variantId,
                status: 'active'
              }
            }));
            setSuccessMessage(`Variant "${variant.sku}" marked as Top Selling.`);
            setTimeout(() => setSuccessMessage(''), 3000);
          } else {
            setError(reactivateData.message || 'Failed to activate Top Selling.');
          }
        } else {
          setError(result.message || 'Failed to mark as Top Selling.');
        }
      }
    } catch (err) {
      console.error('Error toggling Top Selling:', err);
      setError('Network error while toggling Top Selling.');
    } finally {
      setCollectionActionLoading(prev => ({ ...prev, [variantId]: null }));
    }
  };

  // Load options dynamically for AsyncSelect search
  const loadVariantOptions = async (inputValue) => {
    if (!inputValue || !inputValue.trim()) return [];
    try {
      const response = await fetch(
        `${API_BASE_URL}/varient/list.php?q=${encodeURIComponent(inputValue.trim())}&limit=10`,
        { headers: { 'Authorization': `Bearer ${token}` } }
      );
      const result = await response.json();
      if (result.status && result.data?.variants) {
        return result.data.variants.map((v) => ({
          value: v.sku || v.variant_name || v.product_name,
          label: `${v.sku} - ${v.product_name} (${v.variant_name || 'Standard'})`,
          variant: v
        }));
      }
      return [];
    } catch (err) {
      console.error('Error loading variant search options:', err);
      return [];
    }
  };

  const handleSearchSelectChange = (selectedOption) => {
    setSelectedSearchOption(selectedOption);
    const query = selectedOption ? selectedOption.value : '';
    setSearchQuery(query);
    fetchVariants(1, query);
  };

  // Check Active Filters
  const hasActiveFilters = Boolean(
    searchQuery ||
    selectedSearchOption ||
    productFilter ||
    categoryFilter ||
    colorFilter ||
    sizeFilter ||
    stockStatusFilter ||
    discountTypeFilter ||
    collectionFilter
  );

  const handleClearFilters = () => {
    setSelectedSearchOption(null);
    setSearchQuery('');
    setProductFilter('');
    setCategoryFilter('');
    setColorFilter('');
    setSizeFilter('');
    setStockStatusFilter('');
    setDiscountTypeFilter('');
    setCollectionFilter('');
    setSortBy('created_at');
    setSortOrder('desc');
    fetchVariants(1, '');
  };

  // Real-time Pricing Preview Calculation
  const priceCalculations = useMemo(() => {
    const orig = parseFloat(formData.original_price) || 0;
    const gstRate = parseFloat(formData.gst_rate) || 0;
    const gstAmount = (orig * gstRate) / 100;
    const priceWithTax = orig + gstAmount;

    let discountAmount = 0;
    const discVal = parseFloat(formData.discount_value) || 0;

    if (formData.discount_type === 'percentage') {
      discountAmount = (priceWithTax * Math.min(100, Math.max(0, discVal))) / 100;
    } else if (formData.discount_type === 'flat') {
      discountAmount = Math.min(priceWithTax, Math.max(0, discVal));
    }

    const sellingPrice = Math.max(0, priceWithTax - discountAmount);

    return {
      orig: orig.toFixed(2),
      gstRate: gstRate.toFixed(2),
      gstAmount: gstAmount.toFixed(2),
      priceWithTax: priceWithTax.toFixed(2),
      discountAmount: discountAmount.toFixed(2),
      sellingPrice: sellingPrice.toFixed(2)
    };
  }, [formData.original_price, formData.gst_rate, formData.discount_type, formData.discount_value]);

  // Handle Form Inputs
  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
    if (modalError) setModalError('');
  };

  // Handle New Image Selection
  const handleFileSelect = (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;

    const totalAllowed = 10 - (existingImages.filter(img => !img.remove).length + newUploads.length);
    if (files.length > totalAllowed) {
      setModalError(`You can only upload up to ${totalAllowed} more image(s). Maximum 10 images allowed.`);
      return;
    }

    const validExtensions = ['image/jpeg', 'image/png', 'image/webp'];
    const invalidFile = files.find(f => !validExtensions.includes(f.type) || f.size > 5 * 1024 * 1024);
    if (invalidFile) {
      setModalError('Images must be JPG, PNG or WEBP format and under 5 MB in size.');
      return;
    }

    const newEntries = files.map((file, idx) => ({
      file,
      preview: URL.createObjectURL(file),
      alt_text: '',
      is_primary: existingImages.filter(img => !img.remove && img.is_primary).length === 0 && newUploads.length === 0 && idx === 0 ? 1 : 0,
      sort_order: (existingImages.length + newUploads.length + idx + 1),
      image_status: 'active'
    }));

    setNewUploads(prev => [...prev, ...newEntries]);
    setModalError('');
  };

  const removeNewUpload = (index) => {
    setNewUploads(prev => {
      const updated = prev.filter((_, i) => i !== index);
      const hasExistingPrimary = existingImages.some(img => !img.remove && img.is_primary);
      if (!hasExistingPrimary && updated.length > 0 && !updated.some(u => u.is_primary)) {
        updated[0].is_primary = 1;
      }
      return updated;
    });
  };

  const updateNewUploadField = (index, field, value) => {
    setNewUploads(prev => prev.map((item, i) => {
      if (i === index) {
        return { ...item, [field]: value };
      }
      if (field === 'is_primary' && value === 1) {
        return { ...item, is_primary: 0 };
      }
      return item;
    }));

    if (field === 'is_primary' && value === 1) {
      setExistingImages(prev => prev.map(img => ({ ...img, is_primary: 0 })));
    }
  };

  const handleExistingPrimaryToggle = (imageId) => {
    setExistingImages(prev => prev.map(img => ({
      ...img,
      is_primary: img.id === imageId ? 1 : 0
    })));
    setNewUploads(prev => prev.map(u => ({ ...u, is_primary: 0 })));
  };

  const toggleRemoveExistingImage = (imageId) => {
    setExistingImages(prev => prev.map(img => {
      if (img.id === imageId) {
        const isCurrentlyRemoved = img.remove === 1 || img.remove === true || img.remove === '1';
        const nextRemove = isCurrentlyRemoved ? 0 : 1;
        return { ...img, remove: nextRemove, is_primary: nextRemove === 1 ? 0 : img.is_primary };
      }
      return img;
    }));
  };

  const updateExistingImageField = (imageId, field, value) => {
    setExistingImages(prev => prev.map(img => {
      if (img.id === imageId) {
        return { ...img, [field]: value };
      }
      return img;
    }));
  };

  // Open Modals
  const openModal = async (mode, variant = null) => {
    setModalMode(mode);
    setModalError('');
    setNewUploads([]);
    setExistingImages([]);

    if (mode === 'add') {
      setFormData({
        id: '',
        product_id: products.length > 0 ? products[0].id.toString() : '',
        size_id: '',
        color_id: '',
        variant_name: '',
        original_price: '0.00',
        gst_rate: '0.00',
        discount_type: 'none',
        discount_value: '0',
        stock_quantity: '0',
        reserved_quantity: '0',
        low_stock_limit: '5',
        is_available: true
      });
      setIsModalOpen(true);
    } else if (variant && variant.id) {
      setIsModalOpen(true);
      const isAvailableInit = variant.is_available === 1 || variant.is_available === '1' || variant.is_available === true;
      setActiveVariantDetail(variant);
      setFormData({
        id: variant.id,
        product_id: variant.product?.id?.toString() || variant.product_id?.toString() || '',
        size_id: variant.size?.id?.toString() || '',
        color_id: variant.color?.id?.toString() || '',
        variant_name: variant.variant_name || '',
        original_price: variant.pricing?.original_price || variant.original_price || '0.00',
        gst_rate: variant.pricing?.gst_rate || variant.gst_rate || '0.00',
        discount_type: variant.pricing?.discount_type || variant.discount_type || 'none',
        discount_value: variant.pricing?.discount_value || variant.discount_value || '0',
        stock_quantity: variant.stock?.stock_quantity?.toString() || variant.stock_quantity?.toString() || '0',
        reserved_quantity: variant.stock?.reserved_quantity?.toString() || variant.reserved_quantity?.toString() || '0',
        low_stock_limit: variant.stock?.low_stock_limit?.toString() || variant.low_stock_limit?.toString() || '5',
        is_available: isAvailableInit
      });

      if (variant.images && Array.isArray(variant.images)) {
        setExistingImages(variant.images.map(img => ({
          id: img.id,
          image: img.image,
          alt_text: img.alt_text || '',
          is_primary: img.is_primary ? 1 : 0,
          sort_order: img.sort_order || 0,
          status: img.status || 'active',
          remove: 0
        })));
      }

      setModalLoading(true);
      try {
        const response = await fetch(`${API_BASE_URL}/varient/view.php?id=${variant.id}`, {
          headers: { 'Authorization': `Bearer ${token}` }
        });
        const result = await response.json();

        if (result.status && result.data && result.data.variant) {
          const v = result.data.variant;
          setActiveVariantDetail(v);

          setFormData({
            id: v.id,
            product_id: v.product?.id?.toString() || '',
            size_id: v.size?.id?.toString() || '',
            color_id: v.color?.id?.toString() || '',
            variant_name: v.variant_name || '',
            original_price: v.pricing?.original_price || '0.00',
            gst_rate: v.pricing?.gst_rate || '0.00',
            discount_type: v.pricing?.discount_type || 'none',
            discount_value: v.pricing?.discount_value || '0',
            stock_quantity: v.stock?.stock_quantity?.toString() || '0',
            reserved_quantity: v.stock?.reserved_quantity?.toString() || '0',
            low_stock_limit: v.stock?.low_stock_limit?.toString() || '5',
            is_available: v.is_available === 1 || v.is_available === '1' || v.is_available === true
          });

          if (v.images && Array.isArray(v.images)) {
            setExistingImages(v.images.map(img => ({
              id: img.id,
              image: img.image,
              alt_text: img.alt_text || '',
              is_primary: img.is_primary ? 1 : 0,
              sort_order: img.sort_order || 0,
              status: img.status || 'active',
              remove: 0
            })));
          }
        }
      } catch (err) {
        console.error('Optional variant view details fetch error:', err);
      } finally {
        setModalLoading(false);
      }
    }
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setModalError('');
    setModalLoading(false);
    setActiveVariantDetail(null);
  };

  // Submit Form (Create / Update)
  const handleSubmit = async (e) => {
    e.preventDefault();
    if (modalMode === 'view' || modalMode === 'images') {
      closeModal();
      return;
    }

    setModalError('');

    // Client-side Validations
    const prodId = parseInt(formData.product_id, 10);
    if (isNaN(prodId) || prodId <= 0) {
      setModalError('Please select a valid Product.');
      return;
    }

    const hasSize = formData.size_id && parseInt(formData.size_id, 10) > 0;
    const hasColor = formData.color_id && parseInt(formData.color_id, 10) > 0;
    if (!hasSize && !hasColor) {
      setModalError('At least one attribute (Size or Colour) must be selected.');
      return;
    }

    const origPrice = parseFloat(formData.original_price);
    if (isNaN(origPrice) || origPrice <= 0) {
      setModalError('Original Price must be a valid number greater than 0.');
      return;
    }

    const gstRate = parseFloat(formData.gst_rate);
    if (isNaN(gstRate) || gstRate < 0 || gstRate > 100) {
      setModalError('GST Rate must be a valid percentage between 0 and 100.');
      return;
    }

    const stockQty = parseInt(formData.stock_quantity, 10);
    if (isNaN(stockQty) || stockQty < 0) {
      setModalError('Stock quantity must be a non-negative integer.');
      return;
    }

    const reservedQty = parseInt(formData.reserved_quantity, 10);
    if (isNaN(reservedQty) || reservedQty < 0) {
      setModalError('Reserved quantity must be a non-negative integer.');
      return;
    }

    if (reservedQty > stockQty) {
      setModalError('Reserved quantity cannot exceed total stock quantity.');
      return;
    }

    setIsSaving(true);

    const isCreate = modalMode === 'add';
    const endpoint = isCreate
      ? `${API_BASE_URL}/varient/create.php`
      : `${API_BASE_URL}/varient/update.php`;

    const payload = new FormData();

    if (!isCreate) {
      payload.append('id', formData.id.toString());
    }

    payload.append('product_id', formData.product_id.toString());
    if (formData.size_id) payload.append('size_id', formData.size_id.toString());
    if (formData.color_id) payload.append('color_id', formData.color_id.toString());
    if (formData.variant_name.trim()) payload.append('variant_name', formData.variant_name.trim());

    payload.append('original_price', formData.original_price.toString());
    payload.append('gst_rate', formData.gst_rate.toString());
    payload.append('discount_type', formData.discount_type);
    payload.append('discount_value', formData.discount_value.toString());
    payload.append('stock_quantity', formData.stock_quantity.toString());
    payload.append('reserved_quantity', formData.reserved_quantity.toString());
    payload.append('low_stock_limit', formData.low_stock_limit.toString());
    payload.append('is_available', formData.is_available ? '1' : '0');

    // Update Mode: Handle Existing Images JSON
    if (!isCreate && existingImages.length > 0) {
      const existingPayload = existingImages.map(img => ({
        id: img.id,
        alt_text: img.alt_text || null,
        is_primary: img.is_primary ? 1 : 0,
        sort_order: parseInt(img.sort_order, 10) || 0,
        status: img.status || 'active',
        remove: (img.remove === 1 || img.remove === true || img.remove === '1') ? 1 : 0
      }));
      payload.append('existing_images', JSON.stringify(existingPayload));
    }

    // New Image Uploads (Create & Update)
    if (newUploads.length > 0) {
      newUploads.forEach((upload, idx) => {
        payload.append('images[]', upload.file);
        payload.append('alt_text[]', upload.alt_text || `variant_img_${idx + 1}`);
        payload.append('is_primary[]', upload.is_primary ? '1' : '0');
        payload.append('sort_order[]', (upload.sort_order || idx + 1).toString());
        payload.append('image_status[]', upload.image_status || 'active');
      });
    }

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: payload
      });

      const result = await response.json();

      if (result.status) {
        setSuccessMessage(isCreate ? 'Product variant created successfully!' : 'Variant updated successfully!');
        setTimeout(() => setSuccessMessage(''), 4000);
        closeModal();
        fetchVariants(currentPage);
      } else {
        setModalError(result.message || 'Failed to save product variant.');
      }
    } catch (err) {
      console.error('Error saving variant:', err);
      setModalError('Network error: Unable to submit variant details.');
    } finally {
      setIsSaving(false);
    }
  };

  // Set Primary Image Endpoint Integration (`set_primary_image.php`)
  const handleSetPrimaryImage = async (variantId, imageId) => {
    if (!variantId || !imageId) return;
    setSettingPrimaryId(imageId);
    setModalError('');
    try {
      const response = await fetch(`${API_BASE_URL}/varient/set_primary_image.php`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          product_variant_id: parseInt(variantId, 10),
          image_id: parseInt(imageId, 10)
        })
      });
      const result = await response.json();

      if (result.status) {
        setSuccessMessage('Primary image updated successfully!');
        setTimeout(() => setSuccessMessage(''), 3000);

        if (activeVariantDetail) {
          setActiveVariantDetail(prev => {
            if (!prev) return prev;
            const updatedImages = (prev.images || []).map(img => ({
              ...img,
              is_primary: img.id === imageId ? 1 : 0
            }));
            return {
              ...prev,
              images: updatedImages,
              primary_image: updatedImages.find(i => i.id === imageId) || prev.primary_image
            };
          });
        }

        setExistingImages(prev => prev.map(img => ({
          ...img,
          is_primary: img.id === imageId ? 1 : 0
        })));

        fetchVariants(currentPage);
      } else {
        setModalError(result.message || 'Failed to set primary image.');
      }
    } catch (err) {
      console.error('Failed to set primary image:', err);
      setModalError('Network error while setting primary image.');
    } finally {
      setSettingPrimaryId(null);
    }
  };

  const isReadOnly = modalMode === 'view' || modalMode === 'images';

  return (
    <div className="admin-module-container">
      {/* Page Header */}
      <div className="admin-page-header">
        <div>
          <div className="admin-breadcrumb">
            <span>Admin</span> &gt; <span>Catalogue</span> &gt; <span className="active">Product Variants</span>
          </div>
          <h1 className="admin-module-title">Product Variants</h1>
          <p className="admin-module-desc">
            Manage product variations, SKUs, inventory levels, tax calculations, discounts, and gallery images.
          </p>
        </div>
        <div className="admin-header-actions">
          <button className="admin-btn admin-btn-primary" onClick={() => openModal('add')}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Variant
          </button>
        </div>
      </div>

      {/* Alerts */}
      {successMessage && (
        <div className="admin-alert admin-alert-success" style={{ marginBottom: '20px', padding: '14px 18px', backgroundColor: '#f0fdf4', color: '#166534', border: '1.5px solid #bbf7d0', borderRadius: '10px', display: 'flex', alignItems: 'center', gap: '10px' }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
          <span style={{ fontWeight: '600' }}>{successMessage}</span>
        </div>
      )}

      {error && !isModalOpen && (
        <div className="admin-alert admin-alert-error" style={{ marginBottom: '20px', padding: '14px 18px', backgroundColor: '#fdf2f8', color: '#9d174d', border: '1.5px solid #fbcfe8', borderRadius: '10px', display: 'flex', alignItems: 'center', gap: '10px' }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
          <span style={{ fontWeight: '500' }}>{error}</span>
        </div>
      )}

      {/* Filter and Search Bar */}
      <div className="admin-filters-bar" style={{ display: 'flex', flexWrap: 'wrap', gap: '12px', alignItems: 'center', marginBottom: '24px' }}>
        <div style={{ flex: '1 1 240px', minWidth: '220px' }}>
          <AsyncSelect
            cacheOptions
            defaultOptions={false}
            loadOptions={loadVariantOptions}
            value={selectedSearchOption}
            onChange={handleSearchSelectChange}
            placeholder="Search SKU, Product, Name..."
            isClearable
            noOptionsMessage={({ inputValue }) =>
              !inputValue ? 'Type to search variants...' : 'No matching variants found'
            }
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '170px' }}>
          <Select
            options={[
              { value: '', label: 'All Products' },
              ...products.map(p => ({ value: p.id.toString(), label: p.name }))
            ]}
            value={
              productFilter
                ? { value: productFilter, label: products.find(p => p.id.toString() === productFilter)?.name || 'Selected Product' }
                : { value: '', label: 'All Products' }
            }
            onChange={(opt) => setProductFilter(opt ? opt.value : '')}
            isClearable={false}
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '150px' }}>
          <Select
            options={[
              { value: '', label: 'All Categories' },
              ...categories.map(c => ({ value: c.id.toString(), label: c.name }))
            ]}
            value={
              categoryFilter
                ? { value: categoryFilter, label: categories.find(c => c.id.toString() === categoryFilter)?.name || 'Selected Category' }
                : { value: '', label: 'All Categories' }
            }
            onChange={(opt) => setCategoryFilter(opt ? opt.value : '')}
            isClearable={false}
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '140px' }}>
          <Select
            options={[
              { value: '', label: 'All Colours' },
              ...colours.map(c => ({ value: c.id.toString(), label: c.name }))
            ]}
            value={
              colorFilter
                ? { value: colorFilter, label: colours.find(c => c.id.toString() === colorFilter)?.name || 'Selected Colour' }
                : { value: '', label: 'All Colours' }
            }
            onChange={(opt) => setColorFilter(opt ? opt.value : '')}
            isClearable={false}
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '130px' }}>
          <Select
            options={[
              { value: '', label: 'All Sizes' },
              ...sizes.map(s => ({ value: s.id.toString(), label: s.name }))
            ]}
            value={
              sizeFilter
                ? { value: sizeFilter, label: sizes.find(s => s.id.toString() === sizeFilter)?.name || 'Selected Size' }
                : { value: '', label: 'All Sizes' }
            }
            onChange={(opt) => setSizeFilter(opt ? opt.value : '')}
            isClearable={false}
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '140px' }}>
          <Select
            options={[
              { value: '', label: 'All Stock' },
              { value: 'in_stock', label: 'In Stock' },
              { value: 'low_stock', label: 'Low Stock' },
              { value: 'out_of_stock', label: 'Out of Stock' }
            ]}
            value={
              [
                { value: '', label: 'All Stock' },
                { value: 'in_stock', label: 'In Stock' },
                { value: 'low_stock', label: 'Low Stock' },
                { value: 'out_of_stock', label: 'Out of Stock' }
              ].find(o => o.value === stockStatusFilter) || { value: '', label: 'All Stock' }
            }
            onChange={(opt) => setStockStatusFilter(opt ? opt.value : '')}
            isClearable={false}
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '185px' }}>
          <Select
            options={[
              { value: '', label: 'All Collections & Flags' },
              { value: 'new_arrival', label: 'New Arrivals (Variant)' },
              { value: 'top_selling', label: 'Top Selling (Variant)' },
              { value: 'is_new_arrival', label: 'is_new_arrival' },
              { value: 'is_featured', label: 'is_featured' },
              { value: 'is_best_seller', label: 'is_best_seller' }
            ]}
            value={[
              { value: '', label: 'All Collections & Flags' },
              { value: 'new_arrival', label: 'New Arrivals (Variant)' },
              { value: 'top_selling', label: 'Top Selling (Variant)' },
              { value: 'is_new_arrival', label: 'is_new_arrival' },
              { value: 'is_featured', label: 'is_featured' },
              { value: 'is_best_seller', label: 'is_best_seller' }
            ].find(o => o.value === collectionFilter) || { value: '', label: 'All Collections & Flags' }}
            onChange={(opt) => setCollectionFilter(opt ? opt.value : '')}
            isClearable={false}
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '160px' }}>
          <Select
            options={[
              { value: 'created_at-desc', label: 'Newest First' },
              { value: 'created_at-asc', label: 'Oldest First' },
              { value: 'selling_price-asc', label: 'Price (Low to High)' },
              { value: 'selling_price-desc', label: 'Price (High to Low)' },
              { value: 'stock_quantity-desc', label: 'Stock (High to Low)' },
              { value: 'sku-asc', label: 'SKU (A-Z)' }
            ]}
            value={
              [
                { value: 'created_at-desc', label: 'Newest First' },
                { value: 'created_at-asc', label: 'Oldest First' },
                { value: 'selling_price-asc', label: 'Price (Low to High)' },
                { value: 'selling_price-desc', label: 'Price (High to Low)' },
                { value: 'stock_quantity-desc', label: 'Stock (High to Low)' },
                { value: 'sku-asc', label: 'SKU (A-Z)' }
              ].find(o => o.value === `${sortBy}-${sortOrder}`) || { value: 'created_at-desc', label: 'Newest First' }
            }
            onChange={(opt) => {
              if (opt && opt.value) {
                const [sb, so] = opt.value.split('-');
                setSortBy(sb);
                setSortOrder(so);
              }
            }}
            isClearable={false}
            styles={customSelectStyles}
          />
        </div>

        {hasActiveFilters && (
          <button
            type="button"
            onClick={handleClearFilters}
            className="admin-btn admin-btn-clear"
            title="Clear all filters"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
            Clear Filters
          </button>
        )}
      </div>

      {/* Variants Master Data Table */}
      <div className="admin-premium-card" style={{ padding: '0', overflow: 'hidden' }}>
        {isLoading ? (
          <div style={{ padding: '50px', textAlign: 'center', color: '#6b7280' }}>
            <svg className="spinner" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#A049A3" strokeWidth="2" style={{ animation: 'spin 1s linear infinite', marginBottom: '8px' }}>
              <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            <div>Loading variants catalog...</div>
          </div>
        ) : (
          <div className="admin-table-container">
            <table className="admin-table">
              <thead>
                <tr>
                  <th style={{ width: '60px' }}>Image</th>
                  <th>SKU & Variant</th>
                  <th>Product</th>
                  <th>Attributes</th>
                  <th>Pricing</th>
                  <th>Stock & Inventory</th>
                  <th>Collections</th>
                  <th>Status</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {variants.filter(v => {
                  if (collectionFilter === 'new_arrival') return Boolean(newArrivalMap[v.id]);
                  if (collectionFilter === 'top_selling') return Boolean(topSellingMap[v.id]);
                  return true;
                }).length === 0 ? (
                  <tr>
                    <td colSpan="9" style={{ padding: '50px', textAlign: 'center', color: '#9ca3af' }}>
                      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="1.5" style={{ marginBottom: '8px' }}>
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                      </svg>
                      <div>No variants found matching your search and filter criteria.</div>
                    </td>
                  </tr>
                ) : (
                  variants.filter(v => {
                    if (collectionFilter === 'new_arrival') return Boolean(newArrivalMap[v.id]);
                    if (collectionFilter === 'top_selling') return Boolean(topSellingMap[v.id]);
                    return true;
                  }).map(vari => {
                    const primaryImg = vari.primary_image || (vari.images && vari.images.length > 0 ? vari.images[0] : null);
                    const sellingPrice = vari.pricing?.selling_price ?? vari.selling_price ?? '0.00';
                    const originalPrice = vari.pricing?.original_price ?? vari.original_price ?? '0.00';
                    const discType = vari.pricing?.discount_type ?? vari.discount_type ?? 'none';
                    const discVal = vari.pricing?.discount_value ?? vari.discount_value ?? '0';
                    const gstRate = vari.pricing?.gst_rate ?? vari.gst_rate ?? '0';
                    const stockQty = vari.stock?.stock_quantity ?? vari.stock_quantity ?? 0;
                    const reservedQty = vari.stock?.reserved_quantity ?? vari.reserved_quantity ?? 0;
                    const lowLimit = vari.stock?.low_stock_limit ?? vari.low_stock_limit ?? 5;
                    const availableQty = Math.max(0, stockQty - reservedQty);

                    const isNewArrival = Boolean(newArrivalMap[vari.id]);
                    const isTopSelling = Boolean(topSellingMap[vari.id]);

                    let stockBadgeClass = 'admin-badge-active';
                    let stockBadgeText = `${availableQty} in stock`;
                    if (availableQty <= 0) {
                      stockBadgeClass = 'admin-badge-blocked';
                      stockBadgeText = 'Out of Stock';
                    } else if (availableQty <= parseInt(lowLimit, 10)) {
                      stockBadgeClass = 'admin-badge-pending';
                      stockBadgeText = `Low Stock (${availableQty})`;
                    }

                    return (
                      <tr key={vari.id}>
                        {/* Image Thumbnail */}
                        <td>
                          <div
                            onClick={() => openModal('images', vari)}
                            style={{
                              width: '46px',
                              height: '46px',
                              borderRadius: '8px',
                              backgroundColor: '#f6edf6',
                              overflow: 'hidden',
                              display: 'flex',
                              alignItems: 'center',
                              justifyContent: 'center',
                              border: '1px solid #ebd7ed',
                              cursor: 'pointer',
                              position: 'relative'
                            }}
                            title="Click to view & manage images"
                          >
                            {primaryImg?.image ? (
                              <img
                                src={getImageUrl(primaryImg.image)}
                                alt={primaryImg.alt_text || vari.sku}
                                style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                                onError={(e) => { e.target.onerror = null; e.target.style.display = 'none'; }}
                              />
                            ) : (
                              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#A049A3" strokeWidth="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                              </svg>
                            )}
                            {vari.images && vari.images.length > 1 && (
                              <span style={{
                                position: 'absolute',
                                bottom: '2px',
                                right: '2px',
                                background: 'rgba(0,0,0,0.6)',
                                color: '#fff',
                                fontSize: '0.65rem',
                                padding: '1px 3px',
                                borderRadius: '3px',
                                fontWeight: '700'
                              }}>
                                +{vari.images.length}
                              </span>
                            )}
                          </div>
                        </td>

                        {/* SKU & Variant Name */}
                        <td>
                          <div style={{ fontWeight: '700', color: '#111827', fontFamily: 'monospace', fontSize: '0.92rem' }}>
                            {vari.sku}
                          </div>
                          {vari.variant_name && (
                            <div style={{ fontSize: '0.8rem', color: '#6b7280', marginTop: '2px' }}>
                              {vari.variant_name}
                            </div>
                          )}
                        </td>

                        {/* Product & Category */}
                        <td>
                          <div style={{ fontWeight: '600', color: '#374151' }}>
                            {vari.product?.name || vari.product_name || '-'}
                          </div>
                          {vari.category?.name && (
                            <div style={{ fontSize: '0.75rem', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                              {vari.category.name}
                            </div>
                          )}
                        </td>

                        {/* Attributes (Color & Size) */}
                        <td>
                          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                            {vari.size?.name ? (
                              <span style={{
                                padding: '3px 8px',
                                background: '#f3f4f6',
                                borderRadius: '6px',
                                fontWeight: '600',
                                fontSize: '0.8rem',
                                color: '#374151'
                              }}>
                                {vari.size.name}
                              </span>
                            ) : null}

                            {vari.color?.name ? (
                              <span style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '5px',
                                padding: '3px 8px',
                                background: '#fdfafd',
                                border: '1px solid #ebd7ed',
                                borderRadius: '6px',
                                fontSize: '0.8rem',
                                fontWeight: '500',
                                color: '#374151'
                              }}>
                                <span style={{
                                  width: '10px',
                                  height: '10px',
                                  borderRadius: '50%',
                                  backgroundColor: vari.color.hex_code,
                                  border: '1px solid #d1d5db'
                                }}></span>
                                {vari.color.name}
                              </span>
                            ) : null}

                            {!vari.size?.name && !vari.color?.name && (
                              <span style={{ color: '#9ca3af', fontSize: '0.82rem' }}>Standard</span>
                            )}
                          </div>
                        </td>

                        {/* Pricing */}
                        <td>
                          <div style={{ display: 'flex', alignItems: 'baseline', gap: '6px' }}>
                            <span style={{ fontWeight: '700', color: '#059669', fontSize: '0.98rem' }}>
                              ₹{sellingPrice}
                            </span>
                            {discType !== 'none' && parseFloat(originalPrice) > parseFloat(sellingPrice) && (
                              <span style={{ fontSize: '0.78rem', color: '#9ca3af', textDecoration: 'line-through' }}>
                                ₹{originalPrice}
                              </span>
                            )}
                          </div>
                          <div style={{ fontSize: '0.72rem', color: '#6b7280', marginTop: '2px' }}>
                            {gstRate > 0 ? `incl. ${gstRate}% GST` : '0% GST'}
                            {discType === 'percentage' && ` • ${discVal}% off`}
                            {discType === 'flat' && ` • ₹${discVal} off`}
                          </div>
                        </td>

                        {/* Stock & Reserved */}
                        <td>
                          <span className={`admin-badge ${stockBadgeClass}`}>
                            {stockBadgeText}
                          </span>
                          {reservedQty > 0 && (
                            <div style={{ fontSize: '0.72rem', color: '#d97706', marginTop: '3px', fontWeight: '500' }}>
                              ({reservedQty} reserved)
                            </div>
                          )}
                        </td>

                        {/* Collections (Variant-Level Toggles) */}
                        <td>
                          <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', minWidth: '135px' }}>
                            {/* New Arrival Toggle */}
                            <button
                              type="button"
                              disabled={collectionActionLoading[vari.id] === 'new_arrival'}
                              onClick={() => handleToggleNewArrival(vari)}
                              className={`admin-collection-btn ${isNewArrival ? 'admin-collection-active-na' : 'admin-collection-inactive'}`}
                              title={isNewArrival ? 'Remove this variant from New Arrivals' : 'Add this variant to New Arrivals'}
                            >
                              {collectionActionLoading[vari.id] === 'new_arrival' ? (
                                <span style={{ fontSize: '0.75rem' }}>...</span>
                              ) : isNewArrival ? (
                                <>
                                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                  <span>New Arrival</span>
                                </>
                              ) : (
                                <>
                                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                  <span>New Arrival</span>
                                </>
                              )}
                            </button>

                            {/* Top Selling Toggle */}
                            <button
                              type="button"
                              disabled={collectionActionLoading[vari.id] === 'top_selling'}
                              onClick={() => handleToggleTopSelling(vari)}
                              className={`admin-collection-btn ${isTopSelling ? 'admin-collection-active-ts' : 'admin-collection-inactive'}`}
                              title={isTopSelling ? 'Remove this variant from Top Selling' : 'Add this variant to Top Selling'}
                            >
                              {collectionActionLoading[vari.id] === 'top_selling' ? (
                                <span style={{ fontSize: '0.75rem' }}>...</span>
                              ) : isTopSelling ? (
                                <>
                                  <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                  <span>Top Selling</span>
                                </>
                              ) : (
                                <>
                                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                  <span>Top Selling</span>
                                </>
                              )}
                            </button>
                          </div>
                        </td>

                        {/* Availability */}
                        <td>
                          <span className={`admin-badge ${vari.is_available === 1 || vari.is_available === '1' || vari.is_available === true ? 'admin-badge-active' : 'admin-badge-blocked'}`}>
                            {vari.is_available === 1 || vari.is_available === '1' || vari.is_available === true ? 'Available' : 'Unavailable'}
                          </span>
                        </td>

                        {/* Actions */}
                        <td style={{ textAlign: 'right' }}>
                          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '6px', alignItems: 'center' }}>
                            <button
                              onClick={() => openModal('view', vari)}
                              className="admin-action-btn admin-action-view"
                              title="View full variant details"
                            >
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                              </svg>
                              View
                            </button>
                            <button
                              onClick={() => openModal('images', vari)}
                              className="admin-action-btn admin-action-status"
                              title="Manage variant gallery & primary image"
                            >
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                              </svg>
                              Images
                            </button>
                            <button
                              onClick={() => openModal('edit', vari)}
                              className="admin-action-btn admin-action-edit"
                              title="Edit variant pricing, stock, & attributes"
                            >
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                              </svg>
                              Edit
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {!isLoading && totalPages > 1 && (
          <div className="admin-pagination-wrapper">
            <div className="admin-pagination-info">
              Showing {(currentPage - 1) * perPage + 1} to {Math.min(currentPage * perPage, totalRecords)} of {totalRecords} variants
            </div>
            <AdminPagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={(page) => fetchVariants(page)}
            />
          </div>
        )}
      </div>

      {/* Main Admin Modal (Add, Edit, View, Images) */}
      {isModalOpen && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: modalMode === 'view' ? '820px' : modalMode === 'images' ? '760px' : '800px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}>
            {/* Modal Header */}
            <div className="admin-modal-header">
              <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#111827', fontWeight: '700' }}>
                {modalMode === 'add' && 'Create New Product Variant'}
                {modalMode === 'edit' && `Edit Variant: ${formData.id ? `SKU #${formData.id}` : ''}`}
                {modalMode === 'view' && 'Product Variant Details'}
                {modalMode === 'images' && 'Variant Gallery & Primary Image Management'}
              </h2>
              <button onClick={closeModal} className="admin-modal-close">
                &times;
              </button>
            </div>

            {/* Modal Body */}
            <div className="admin-modal-body" style={{ overflowY: 'auto', flex: 1, padding: '24px' }}>
              {modalError && (
                <div style={{ marginBottom: '20px', padding: '12px 16px', backgroundColor: '#fdf2f8', color: '#9d174d', borderRadius: '8px', fontSize: '0.875rem', border: '1px solid #fbcfe8', display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                  {modalError}
                </div>
              )}

              {modalLoading ? (
                <div style={{ padding: '60px', textAlign: 'center', color: '#6b7280' }}>
                  <svg className="spinner" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#A049A3" strokeWidth="2" style={{ animation: 'spin 1s linear infinite', marginBottom: '10px' }}>
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                  </svg>
                  <div>Loading variant data...</div>
                </div>
              ) : modalMode === 'view' && activeVariantDetail ? (
                /* View Variant Details */
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                  {/* Top Overview Bar */}
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#faf4fa', padding: '16px 20px', borderRadius: '12px', border: '1px solid #ebd7ed' }}>
                    <div>
                      <span style={{ fontSize: '0.75rem', color: '#6b7280', textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: '700' }}>Variant SKU</span>
                      <div style={{ fontSize: '1.2rem', fontWeight: '800', color: '#A049A3', fontFamily: 'monospace' }}>
                        {activeVariantDetail.sku}
                      </div>
                    </div>
                    <div>
                      <span className={`admin-badge ${activeVariantDetail.is_available === 1 || activeVariantDetail.is_available === '1' || activeVariantDetail.is_available === true ? 'admin-badge-active' : 'admin-badge-blocked'}`}>
                        {activeVariantDetail.is_available === 1 || activeVariantDetail.is_available === '1' || activeVariantDetail.is_available === true ? 'Available for Sale' : 'Unavailable'}
                      </span>
                    </div>
                  </div>

                  {/* Info Grid */}
                  <div className="admin-info-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))' }}>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Product</span>
                      <div style={{ fontSize: '0.98rem', fontWeight: '600', color: '#111827' }}>
                        {activeVariantDetail.product?.name || '-'}
                      </div>
                      <div style={{ fontSize: '0.8rem', color: '#6b7280', marginTop: '4px' }}>
                        Category: {activeVariantDetail.category?.name || '-'}
                      </div>
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">Variant Name</span>
                      <span style={{ fontSize: '0.95rem', fontWeight: '500', color: '#374151' }}>
                        {activeVariantDetail.variant_name || 'Standard'}
                      </span>
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">Size & Colour</span>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginTop: '4px' }}>
                        <span style={{ padding: '2px 8px', background: '#f3f4f6', borderRadius: '4px', fontWeight: '600', fontSize: '0.85rem' }}>
                          Size: {activeVariantDetail.size?.name || 'None'}
                        </span>
                        {activeVariantDetail.color && (
                          <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', fontSize: '0.85rem', fontWeight: '500' }}>
                            <span style={{ width: '12px', height: '12px', borderRadius: '50%', backgroundColor: activeVariantDetail.color.hex_code, border: '1px solid #d1d5db' }}></span>
                            {activeVariantDetail.color.name}
                          </span>
                        )}
                      </div>
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">HSN Profile</span>
                      <span style={{ fontSize: '0.9rem', color: '#374151' }}>
                        {activeVariantDetail.hsn_profile ? `${activeVariantDetail.hsn_profile.name} (HSN ${activeVariantDetail.hsn_profile.hsn_code})` : 'None'}
                      </span>
                    </div>
                  </div>

                  {/* Pricing Breakdown Card */}
                  <div style={{ background: '#ffffff', border: '1.5px solid #ebd7ed', borderRadius: '12px', padding: '18px' }}>
                    <h3 style={{ margin: '0 0 14px 0', fontSize: '0.95rem', color: '#4a384e', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      Pricing & Tax Breakdown
                    </h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '12px' }}>
                      <div>
                        <span className="admin-info-label">Original Price</span>
                        <div style={{ fontSize: '1rem', fontWeight: '600', color: '#374151' }}>
                          ₹{activeVariantDetail.pricing?.original_price}
                        </div>
                      </div>
                      <div>
                        <span className="admin-info-label">GST ({activeVariantDetail.pricing?.gst_rate}%)</span>
                        <div style={{ fontSize: '1rem', fontWeight: '600', color: '#374151' }}>
                          +₹{activeVariantDetail.pricing?.gst_amount}
                        </div>
                      </div>
                      <div>
                        <span className="admin-info-label">Price with Tax</span>
                        <div style={{ fontSize: '1rem', fontWeight: '600', color: '#374151' }}>
                          ₹{activeVariantDetail.pricing?.price_with_tax}
                        </div>
                      </div>
                      <div>
                        <span className="admin-info-label">Final Selling Price</span>
                        <div style={{ fontSize: '1.15rem', fontWeight: '800', color: '#059669' }}>
                          ₹{activeVariantDetail.pricing?.selling_price}
                        </div>
                      </div>
                    </div>
                    {activeVariantDetail.pricing?.discount_type !== 'none' && (
                      <div style={{ marginTop: '10px', fontSize: '0.85rem', color: '#b91c1c', fontWeight: '500' }}>
                        Discount: {activeVariantDetail.pricing?.discount_type === 'percentage' ? `${activeVariantDetail.pricing?.discount_value}%` : `₹${activeVariantDetail.pricing?.discount_value}`} off (-₹{activeVariantDetail.pricing?.discount_amount})
                      </div>
                    )}
                  </div>

                  {/* Stock Information */}
                  <div style={{ background: '#ffffff', border: '1.5px solid #ebd7ed', borderRadius: '12px', padding: '18px' }}>
                    <h3 style={{ margin: '0 0 14px 0', fontSize: '0.95rem', color: '#4a384e', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      Inventory & Stock
                    </h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '12px' }}>
                      <div>
                        <span className="admin-info-label">Total Stock</span>
                        <div style={{ fontSize: '1.1rem', fontWeight: '700', color: '#111827' }}>
                          {activeVariantDetail.stock?.stock_quantity}
                        </div>
                      </div>
                      <div>
                        <span className="admin-info-label">Reserved</span>
                        <div style={{ fontSize: '1.1rem', fontWeight: '700', color: '#d97706' }}>
                          {activeVariantDetail.stock?.reserved_quantity}
                        </div>
                      </div>
                      <div>
                        <span className="admin-info-label">Available for Sale</span>
                        <div style={{ fontSize: '1.1rem', fontWeight: '700', color: '#059669' }}>
                          {activeVariantDetail.stock?.available_quantity}
                        </div>
                      </div>
                      <div>
                        <span className="admin-info-label">Low Stock Threshold</span>
                        <div style={{ fontSize: '1.1rem', fontWeight: '600', color: '#6b7280' }}>
                          {activeVariantDetail.stock?.low_stock_limit}
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Parent Feature Flags & Timestamps */}
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '12px' }}>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Parent Product Flags</span>
                      <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap', marginTop: '4px' }}>
                        <span className={`admin-badge ${activeVariantDetail.product?.is_new_arrival ? 'admin-badge-new' : 'admin-badge-inactive'}`}>
                          is_new_arrival: {activeVariantDetail.product?.is_new_arrival ? 'Yes' : 'No'}
                        </span>
                        <span className={`admin-badge ${activeVariantDetail.product?.is_featured ? 'admin-badge-featured' : 'admin-badge-inactive'}`}>
                          is_featured: {activeVariantDetail.product?.is_featured ? 'Yes' : 'No'}
                        </span>
                        <span className={`admin-badge ${activeVariantDetail.product?.is_best_seller ? 'admin-badge-bestseller' : 'admin-badge-inactive'}`}>
                          is_best_seller: {activeVariantDetail.product?.is_best_seller ? 'Yes' : 'No'}
                        </span>
                      </div>
                    </div>

                    {activeVariantDetail.created_at && (
                      <div className="admin-info-box">
                        <span className="admin-info-label">Created At</span>
                        <span style={{ fontSize: '0.85rem', color: '#6b7280', marginTop: '4px', display: 'block' }}>
                          {new Date(activeVariantDetail.created_at).toLocaleString()}
                        </span>
                      </div>
                    )}

                    {activeVariantDetail.updated_at && (
                      <div className="admin-info-box">
                        <span className="admin-info-label">Updated At</span>
                        <span style={{ fontSize: '0.85rem', color: '#6b7280', marginTop: '4px', display: 'block' }}>
                          {new Date(activeVariantDetail.updated_at).toLocaleString()}
                        </span>
                      </div>
                    )}
                  </div>

                  {/* Image Gallery */}
                  <div>
                    <h3 style={{ margin: '0 0 14px 0', fontSize: '0.95rem', color: '#4a384e', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      Variant Images ({activeVariantDetail.images?.length || 0})
                    </h3>
                    {activeVariantDetail.images && activeVariantDetail.images.length > 0 ? (
                      <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
                        {activeVariantDetail.images.map((img) => (
                          <div
                            key={img.id}
                            style={{
                              position: 'relative',
                              width: '110px',
                              height: '110px',
                              borderRadius: '10px',
                              border: img.is_primary ? '2.5px solid #A049A3' : '1px solid #d1d5db',
                              overflow: 'hidden',
                              backgroundColor: '#f3f4f6'
                            }}
                          >
                            <img
                              src={getImageUrl(img.image)}
                              alt={img.alt_text || 'Variant'}
                              style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                            />
                            {img.is_primary === 1 && (
                              <span style={{
                                position: 'absolute',
                                top: '4px',
                                right: '4px',
                                background: '#A049A3',
                                color: '#fff',
                                fontSize: '0.65rem',
                                padding: '2px 5px',
                                borderRadius: '4px',
                                fontWeight: '700'
                              }}>
                                Primary
                              </span>
                            )}
                          </div>
                        ))}
                      </div>
                    ) : (
                      <div style={{ padding: '20px', background: '#fafafa', borderRadius: '8px', textAlign: 'center', color: '#9ca3af' }}>
                        No gallery images attached to this variant.
                      </div>
                    )}
                  </div>
                </div>
              ) : modalMode === 'images' && activeVariantDetail ? (
                /* Dedicated Image Management Modal */
                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#faf4fa', padding: '14px 18px', borderRadius: '10px', border: '1px solid #ebd7ed' }}>
                    <div>
                      <span style={{ fontSize: '0.8rem', color: '#6b7280' }}>Variant SKU</span>
                      <div style={{ fontWeight: '700', color: '#A049A3', fontFamily: 'monospace' }}>{activeVariantDetail.sku}</div>
                    </div>
                    <div>
                      <span style={{ fontSize: '0.85rem', color: '#374151' }}>
                        Total Images: <strong>{activeVariantDetail.images?.length || 0}</strong>
                      </span>
                    </div>
                  </div>

                  {activeVariantDetail.images && activeVariantDetail.images.length > 0 ? (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: '16px' }}>
                      {activeVariantDetail.images.map((img) => (
                        <div
                          key={img.id}
                          style={{
                            border: img.is_primary ? '2.5px solid #A049A3' : '1.5px solid #e5e7eb',
                            borderRadius: '12px',
                            overflow: 'hidden',
                            backgroundColor: '#fff',
                            boxShadow: img.is_primary ? '0 4px 12px rgba(160, 73, 163, 0.15)' : 'none',
                            display: 'flex',
                            flexDirection: 'column'
                          }}
                        >
                          <div style={{ height: '140px', backgroundColor: '#f9fafb', position: 'relative' }}>
                            <img
                              src={getImageUrl(img.image)}
                              alt={img.alt_text || 'Variant image'}
                              style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                            />
                            {img.is_primary === 1 && (
                              <span style={{
                                position: 'absolute',
                                top: '6px',
                                right: '6px',
                                background: 'linear-gradient(135deg, #A049A3 0%, #C86395 100%)',
                                color: '#ffffff',
                                fontSize: '0.7rem',
                                padding: '2px 8px',
                                borderRadius: '6px',
                                fontWeight: '700',
                                boxShadow: '0 2px 6px rgba(0,0,0,0.2)'
                              }}>
                                Primary
                              </span>
                            )}
                          </div>
                          <div style={{ padding: '12px', display: 'flex', flexDirection: 'column', gap: '8px', flex: 1, justifyContent: 'space-between' }}>
                            <div style={{ fontSize: '0.78rem', color: '#6b7280', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                              {img.alt_text || 'No alt text'}
                            </div>
                            {img.is_primary !== 1 ? (
                              <button
                                type="button"
                                disabled={settingPrimaryId === img.id}
                                onClick={() => handleSetPrimaryImage(activeVariantDetail.id, img.id)}
                                className="admin-btn admin-btn-secondary"
                                style={{ width: '100%', padding: '6px 10px', fontSize: '0.78rem' }}
                              >
                                {settingPrimaryId === img.id ? 'Setting...' : 'Set as Primary'}
                              </button>
                            ) : (
                              <div style={{ textAlign: 'center', fontSize: '0.78rem', color: '#059669', fontWeight: '700', padding: '6px 0' }}>
                                ✓ Current Primary Image
                              </div>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div style={{ padding: '40px', textAlign: 'center', color: '#9ca3af', backgroundColor: '#f9fafb', borderRadius: '10px' }}>
                      No images found for this variant. You can upload images via the Edit form.
                    </div>
                  )}
                </div>
              ) : (
                /* Create & Edit Variant Form */
                <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '22px' }}>
                  {/* Step 1: Product & Attributes */}
                  <div>
                    <h3 style={{ margin: '0 0 12px 0', fontSize: '0.92rem', color: '#4a384e', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      1. Product & Identification
                    </h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '16px' }}>
                      <div style={{ gridColumn: '1 / -1' }}>
                        <label className="admin-label">Product <span style={{ color: '#ef4444' }}>*</span></label>
                        <Select
                          name="product_id"
                          options={products.map(p => ({
                            value: p.id.toString(),
                            label: `${p.name} ${p.category_name ? `(${p.category_name})` : ''}`
                          }))}
                          value={products.map(p => ({
                            value: p.id.toString(),
                            label: `${p.name} ${p.category_name ? `(${p.category_name})` : ''}`
                          })).find(o => o.value === formData.product_id?.toString()) || null}
                          onChange={(opt) => {
                            setFormData(prev => ({ ...prev, product_id: opt ? opt.value : '' }));
                            if (modalError) setModalError('');
                          }}
                          placeholder="Select parent product"
                          isClearable={false}
                          menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                          styles={customSelectStyles}
                        />
                      </div>

                      <div>
                        <label className="admin-label">Size Attribute</label>
                        <Select
                          name="size_id"
                          options={[
                            { value: '', label: 'No specific size' },
                            ...sizes.map(s => ({ value: s.id.toString(), label: s.name }))
                          ]}
                          value={
                            formData.size_id
                              ? { value: formData.size_id.toString(), label: sizes.find(s => s.id.toString() === formData.size_id.toString())?.name || 'Selected Size' }
                              : { value: '', label: 'No specific size' }
                          }
                          onChange={(opt) => {
                            setFormData(prev => ({ ...prev, size_id: opt ? opt.value : '' }));
                            if (modalError) setModalError('');
                          }}
                          isClearable={false}
                          menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                          styles={customSelectStyles}
                        />
                      </div>

                      <div>
                        <label className="admin-label">Colour Attribute</label>
                        <Select
                          name="color_id"
                          options={[
                            { value: '', label: 'No specific colour' },
                            ...colours.map(c => ({ value: c.id.toString(), label: c.name }))
                          ]}
                          value={
                            formData.color_id
                              ? { value: formData.color_id.toString(), label: colours.find(c => c.id.toString() === formData.color_id.toString())?.name || 'Selected Colour' }
                              : { value: '', label: 'No specific colour' }
                          }
                          onChange={(opt) => {
                            setFormData(prev => ({ ...prev, color_id: opt ? opt.value : '' }));
                            if (modalError) setModalError('');
                          }}
                          isClearable={false}
                          menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                          styles={customSelectStyles}
                        />
                      </div>

                      <div style={{ gridColumn: '1 / -1' }}>
                        <label className="admin-label">Variant Name (Optional)</label>
                        <input
                          type="text"
                          name="variant_name"
                          value={formData.variant_name}
                          onChange={handleInputChange}
                          placeholder="e.g. Royal Blue Silk Special Edition"
                          maxLength={150}
                          className="admin-input"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Step 2: Pricing, GST & Discounts with Live Calculation */}
                  <div>
                    <h3 style={{ margin: '0 0 12px 0', fontSize: '0.92rem', color: '#4a384e', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      2. Pricing, GST & Discounts
                    </h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '16px' }}>
                      <div>
                        <label className="admin-label">Original Price (₹) <span style={{ color: '#ef4444' }}>*</span></label>
                        <input
                          type="number"
                          step="0.01"
                          min="0.01"
                          name="original_price"
                          value={formData.original_price}
                          onChange={handleInputChange}
                          required
                          className="admin-input"
                        />
                      </div>

                      <div>
                        <label className="admin-label">GST Rate (%) <span style={{ color: '#ef4444' }}>*</span></label>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          max="100"
                          name="gst_rate"
                          value={formData.gst_rate}
                          onChange={handleInputChange}
                          required
                          className="admin-input"
                        />
                      </div>

                      <div>
                        <label className="admin-label">Discount Type</label>
                        <Select
                          name="discount_type"
                          options={[
                            { value: 'none', label: 'None (No Discount)' },
                            { value: 'percentage', label: 'Percentage (%)' },
                            { value: 'flat', label: 'Flat Amount (₹)' }
                          ]}
                          value={[
                            { value: 'none', label: 'None (No Discount)' },
                            { value: 'percentage', label: 'Percentage (%)' },
                            { value: 'flat', label: 'Flat Amount (₹)' }
                          ].find(o => o.value === formData.discount_type) || { value: 'none', label: 'None (No Discount)' }}
                          onChange={(opt) => {
                            const val = opt ? opt.value : 'none';
                            setFormData(prev => ({
                              ...prev,
                              discount_type: val,
                              discount_value: val === 'none' ? '0' : prev.discount_value
                            }));
                            if (modalError) setModalError('');
                          }}
                          isClearable={false}
                          menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                          styles={customSelectStyles}
                        />
                      </div>

                      <div>
                        <label className="admin-label">Discount Value</label>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          name="discount_value"
                          value={formData.discount_value}
                          onChange={handleInputChange}
                          disabled={formData.discount_type === 'none'}
                          className="admin-input"
                          style={{ backgroundColor: formData.discount_type === 'none' ? '#f3f4f6' : '#fff' }}
                        />
                      </div>
                    </div>

                    {/* Live Calculated Price Breakdown Box */}
                    <div style={{ marginTop: '14px', padding: '14px 18px', background: '#faf4fa', border: '1.5px solid #ebd7ed', borderRadius: '10px' }}>
                      <span style={{ fontSize: '0.78rem', color: '#6b7280', textTransform: 'uppercase', letterSpacing: '0.04em', fontWeight: '700' }}>
                        Real-Time Calculated Preview:
                      </span>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '6px', flexWrap: 'wrap', gap: '8px' }}>
                        <span style={{ fontSize: '0.88rem', color: '#374151' }}>
                          Base: <strong>₹{priceCalculations.orig}</strong> + GST ({priceCalculations.gstRate}%): <strong>₹{priceCalculations.gstAmount}</strong> = Price with Tax: <strong>₹{priceCalculations.priceWithTax}</strong>
                        </span>
                        <span style={{ fontSize: '1.05rem', fontWeight: '800', color: '#059669' }}>
                          Selling Price: ₹{priceCalculations.sellingPrice}
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Step 3: Inventory & Availability */}
                  <div>
                    <h3 style={{ margin: '0 0 12px 0', fontSize: '0.92rem', color: '#4a384e', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      3. Stock & Availability
                    </h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '16px' }}>
                      <div>
                        <label className="admin-label">Stock Quantity <span style={{ color: '#ef4444' }}>*</span></label>
                        <input
                          type="number"
                          min="0"
                          name="stock_quantity"
                          value={formData.stock_quantity}
                          onChange={handleInputChange}
                          required
                          className="admin-input"
                        />
                      </div>

                      <div>
                        <label className="admin-label">Reserved Quantity</label>
                        <input
                          type="number"
                          min="0"
                          name="reserved_quantity"
                          value={formData.reserved_quantity}
                          onChange={handleInputChange}
                          className="admin-input"
                        />
                      </div>

                      <div>
                        <label className="admin-label">Low Stock Threshold</label>
                        <input
                          type="number"
                          min="0"
                          name="low_stock_limit"
                          value={formData.low_stock_limit}
                          onChange={handleInputChange}
                          className="admin-input"
                        />
                      </div>

                      <div style={{ gridColumn: '1 / -1', marginTop: '4px' }}>
                        <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.88rem', fontWeight: '600', color: '#374151', cursor: 'pointer' }}>
                          <input
                            type="checkbox"
                            name="is_available"
                            checked={formData.is_available}
                            onChange={handleInputChange}
                            style={{ width: '18px', height: '18px', accentColor: '#A049A3', cursor: 'pointer' }}
                          />
                          Available for customer purchase
                        </label>
                      </div>
                    </div>
                  </div>

                  {/* Step 4: Images & Gallery Management */}
                  <div>
                    <h3 style={{ margin: '0 0 12px 0', fontSize: '0.92rem', color: '#4a384e', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      4. Variant Gallery Images
                    </h3>

                    {/* Existing Images in Edit Mode */}
                    {modalMode === 'edit' && existingImages.length > 0 && (
                      <div style={{ marginBottom: '18px' }}>
                        <span className="admin-label">Existing Images:</span>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(130px, 1fr))', gap: '12px', marginTop: '8px' }}>
                          {existingImages.map((img) => (
                            <div
                              key={img.id}
                              style={{
                                border: img.remove ? '1.5px dashed #ef4444' : img.is_primary ? '2.5px solid #A049A3' : '1px solid #d1d5db',
                                opacity: img.remove ? 0.45 : 1,
                                borderRadius: '8px',
                                overflow: 'hidden',
                                backgroundColor: '#f9fafb',
                                padding: '6px',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: '6px'
                              }}
                            >
                              <div style={{ width: '100%', height: '90px', position: 'relative' }}>
                                <img
                                  src={getImageUrl(img.image)}
                                  alt={img.alt_text || 'Variant'}
                                  style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: '4px' }}
                                />
                                {img.is_primary === 1 && !img.remove && (
                                  <span style={{ position: 'absolute', top: '2px', left: '2px', background: '#A049A3', color: '#fff', fontSize: '0.65rem', padding: '1px 4px', borderRadius: '3px', fontWeight: '700' }}>
                                    Primary
                                  </span>
                                )}
                                <button
                                  type="button"
                                  onClick={() => toggleRemoveExistingImage(img.id)}
                                  style={{
                                    position: 'absolute',
                                    top: '4px',
                                    right: '4px',
                                    background: img.remove ? '#ef4444' : 'rgba(0, 0, 0, 0.65)',
                                    color: '#ffffff',
                                    border: 'none',
                                    borderRadius: '4px',
                                    width: '24px',
                                    height: '24px',
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    transition: 'all 0.2s ease',
                                    zIndex: 2
                                  }}
                                  title={img.remove ? 'Undo removal' : 'Delete / Remove image'}
                                >
                                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                  </svg>
                                </button>
                                {img.remove ? (
                                  <div style={{
                                    position: 'absolute',
                                    inset: 0,
                                    background: 'rgba(239, 68, 68, 0.35)',
                                    borderRadius: '4px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    color: '#ffffff',
                                    fontWeight: '700',
                                    fontSize: '0.72rem',
                                    textShadow: '0 1px 3px rgba(0,0,0,0.8)'
                                  }}>
                                    Marked for Delete
                                  </div>
                                ) : null}
                              </div>
                              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', minHeight: '18px' }}>
                                {!img.remove && img.is_primary !== 1 ? (
                                  <button
                                    type="button"
                                    onClick={() => handleExistingPrimaryToggle(img.id)}
                                    style={{ fontSize: '0.7rem', color: '#A049A3', background: 'none', border: 'none', cursor: 'pointer', padding: 0, fontWeight: '600' }}
                                  >
                                    Set Primary
                                  </button>
                                ) : (
                                  <span />
                                )}
                              </div>
                              <input
                                type="text"
                                placeholder="Alt text"
                                value={img.alt_text || ''}
                                onChange={(e) => updateExistingImageField(img.id, 'alt_text', e.target.value)}
                                disabled={Boolean(img.remove)}
                                className="admin-input"
                                style={{ padding: '3px 6px', fontSize: '0.74rem' }}
                              />
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* Upload Dropzone */}
                    <div style={{ border: '2px dashed #d8bedb', padding: '20px', borderRadius: '12px', textAlign: 'center', backgroundColor: '#faf4fa' }}>
                      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#A049A3" strokeWidth="1.8" style={{ marginBottom: '8px' }}>
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                      </svg>
                      <div style={{ fontSize: '0.9rem', fontWeight: '600', color: '#374151', marginBottom: '4px' }}>
                        Choose images or drag & drop here
                      </div>
                      <div style={{ fontSize: '0.78rem', color: '#6b7280', marginBottom: '12px' }}>
                        JPG, PNG, or WEBP up to 5 MB each. Up to 10 images per variant.
                      </div>
                      <input
                        type="file"
                        onChange={handleFileSelect}
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        style={{ display: 'none' }}
                        id="variant-file-input"
                      />
                      <label
                        htmlFor="variant-file-input"
                        className="admin-btn admin-btn-secondary"
                        style={{ cursor: 'pointer' }}
                      >
                        Browse Files
                      </label>
                    </div>

                    {/* New Uploads Preview List */}
                    {newUploads.length > 0 && (
                      <div style={{ marginTop: '16px' }}>
                        <span className="admin-label">New Images to Upload ({newUploads.length}):</span>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: '12px', marginTop: '8px' }}>
                          {newUploads.map((upload, idx) => (
                            <div
                              key={idx}
                              style={{
                                border: upload.is_primary ? '2px solid #A049A3' : '1px solid #e5e7eb',
                                borderRadius: '8px',
                                padding: '8px',
                                backgroundColor: '#fff',
                                display: 'flex',
                                flexDirection: 'column',
                                gap: '6px'
                              }}
                            >
                              <div style={{ width: '100%', height: '100px', position: 'relative' }}>
                                <img
                                  src={upload.preview}
                                  alt="Upload preview"
                                  style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: '4px' }}
                                />
                                <button
                                  type="button"
                                  onClick={() => removeNewUpload(idx)}
                                  style={{
                                    position: 'absolute',
                                    top: '4px',
                                    right: '4px',
                                    background: 'rgba(239, 68, 68, 0.9)',
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: '4px',
                                    width: '24px',
                                    height: '24px',
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    transition: 'background 0.2s ease'
                                  }}
                                  title="Remove image"
                                >
                                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                  </svg>
                                </button>
                              </div>
                              <input
                                type="text"
                                placeholder="Alt text (e.g. Front View)"
                                value={upload.alt_text}
                                onChange={(e) => updateNewUploadField(idx, 'alt_text', e.target.value)}
                                className="admin-input"
                                style={{ padding: '4px 8px', fontSize: '0.78rem' }}
                              />
                              <label style={{ fontSize: '0.75rem', display: 'flex', alignItems: 'center', gap: '4px', cursor: 'pointer', color: '#374151' }}>
                                <input
                                  type="radio"
                                  name="new_primary_select"
                                  checked={upload.is_primary === 1}
                                  onChange={() => updateNewUploadField(idx, 'is_primary', 1)}
                                />
                                Set as Primary
                              </label>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                </form>
              )}
            </div>

            {/* Modal Footer */}
            <div className="admin-modal-footer">
              <button type="button" onClick={closeModal} className="admin-btn admin-btn-secondary">
                {isReadOnly ? 'Close' : 'Cancel'}
              </button>
              {!isReadOnly && (
                <button
                  type="submit"
                  disabled={isSaving || modalLoading}
                  onClick={handleSubmit}
                  className="admin-btn admin-btn-primary"
                  style={{ opacity: isSaving || modalLoading ? 0.7 : 1, cursor: isSaving || modalLoading ? 'not-allowed' : 'pointer' }}
                >
                  {isSaving ? (
                    <>
                      <svg className="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ animation: 'spin 1s linear infinite' }}>
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                      </svg>
                      Saving...
                    </>
                  ) : (
                    modalMode === 'add' ? 'Create Variant' : 'Update Variant'
                  )}
                </button>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminVariants;
