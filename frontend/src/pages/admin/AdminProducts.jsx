import React, { useState, useEffect } from 'react';
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

const AdminProducts = () => {
  const { token } = useAdminAuth();
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [formHsnProfiles, setFormHsnProfiles] = useState([]);
  const [isLoadingHsn, setIsLoadingHsn] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  // Filters & Search
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedSearchOption, setSelectedSearchOption] = useState(null);
  const [categoryFilter, setCategoryFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [tagFilter, setTagFilter] = useState(''); // 'new_arrival' | 'featured' | 'best_seller'
  const [sortBy, setSortBy] = useState('created_at');
  const [sortOrder, setSortOrder] = useState('desc');

  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);
  const perPage = 10;

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState('add'); // 'add', 'edit', or 'view'
  const [modalLoading, setModalLoading] = useState(false);
  const [formData, setFormData] = useState({
    id: '',
    category_id: '',
    hsn_profile_id: '',
    name: '',
    slug: '',
    description: '',
    is_new_arrival: false,
    is_featured: false,
    is_best_seller: false,
    status: 'active',
    category_name: '',
    hsn_profile_name: '',
    hsn_code: '',
    hsn_description: '',
    created_at: '',
    updated_at: ''
  });
  const [isSaving, setIsSaving] = useState(false);
  const [modalError, setModalError] = useState('');

  // Fetch Active Categories for Filter and Form
  const fetchCategories = async () => {
    try {
      const response = await fetch(`${API_BASE_URL}/category/list.php?limit=100`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      if (result.status && result.data) {
        setCategories(result.data.categories || []);
      }
    } catch (err) {
      console.error('Failed to fetch categories:', err);
    }
  };

  // Fetch HSN profiles for a specific category
  const fetchHsnProfilesForCategory = async (catId) => {
    if (!catId) {
      setFormHsnProfiles([]);
      return [];
    }
    setIsLoadingHsn(true);
    try {
      const response = await fetch(`${API_BASE_URL}/hsn/list.php?category_id=${catId}&limit=100`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      if (result.status && result.data) {
        const profiles = result.data.hsn_profiles || [];
        setFormHsnProfiles(profiles);
        return profiles;
      }
      setFormHsnProfiles([]);
      return [];
    } catch (err) {
      console.error('Failed to fetch HSN profiles for category:', err);
      setFormHsnProfiles([]);
      return [];
    } finally {
      setIsLoadingHsn(false);
    }
  };

  // Fetch Products List
  const fetchProducts = async (page = 1, searchOverride = null) => {
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
      if (categoryFilter) queryParams.append('category_id', categoryFilter);
      if (statusFilter) queryParams.append('status', statusFilter);
      if (tagFilter === 'new_arrival') queryParams.append('is_new_arrival', '1');
      if (tagFilter === 'featured') queryParams.append('is_featured', '1');
      if (tagFilter === 'best_seller') queryParams.append('is_best_seller', '1');

      const response = await fetch(`${API_BASE_URL}/product/list.php?${queryParams.toString()}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();

      if (result.status && result.data) {
        setProducts(result.data.products || []);
        if (result.data.pagination) {
          setCurrentPage(result.data.pagination.current_page || 1);
          setTotalPages(result.data.pagination.total_pages || 1);
          setTotalRecords(result.data.pagination.total_records || 0);
        }
      } else {
        setProducts([]);
        setError(result.message || 'Failed to fetch products');
      }
    } catch (err) {
      console.error(err);
      setError('Network error: Unable to retrieve products from server.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (token) {
      fetchCategories();
      fetchProducts(1);
    }
  }, [token, categoryFilter, statusFilter, tagFilter, sortBy, sortOrder]);

  // Load options dynamically for AsyncSelect search
  const loadProductOptions = async (inputValue) => {
    if (!inputValue || !inputValue.trim()) return [];
    try {
      const response = await fetch(
        `${API_BASE_URL}/product/list.php?q=${encodeURIComponent(inputValue.trim())}&limit=10`,
        {
          headers: { 'Authorization': `Bearer ${token}` }
        }
      );
      const result = await response.json();
      if (result.status && result.data?.products) {
        return result.data.products.map((p) => ({
          value: p.name,
          label: `${p.name} (${p.category_name || 'No Category'})`,
          product: p
        }));
      }
      return [];
    } catch (err) {
      console.error('Error loading product search options:', err);
      return [];
    }
  };

  const handleSearchSelectChange = (selectedOption) => {
    setSelectedSearchOption(selectedOption);
    const query = selectedOption ? selectedOption.value : '';
    setSearchQuery(query);
    fetchProducts(1, query);
  };

  // Clear all filters
  const hasActiveFilters = Boolean(
    searchQuery ||
    selectedSearchOption ||
    categoryFilter ||
    statusFilter ||
    tagFilter ||
    sortBy !== 'created_at' ||
    sortOrder !== 'desc'
  );

  const handleClearFilters = () => {
    setSelectedSearchOption(null);
    setSearchQuery('');
    setCategoryFilter('');
    setStatusFilter('');
    setTagFilter('');
    setSortBy('created_at');
    setSortOrder('desc');
    fetchProducts(1, '');
  };

  // Handle Form Input Change
  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    const val = type === 'checkbox' ? checked : value;

    setFormData(prev => ({
      ...prev,
      [name]: val
    }));

    if (modalError) setModalError('');

    // If Category changes, refresh HSN options and reset hsn_profile_id
    if (name === 'category_id') {
      fetchHsnProfilesForCategory(val);
      setFormData(prev => ({
        ...prev,
        category_id: val,
        hsn_profile_id: ''
      }));
    }
  };

  // Open Modal (Add / Edit / View)
  const openModal = async (mode, product = null) => {
    setModalMode(mode);
    setModalError('');
    setIsModalOpen(true);

    if (mode === 'add') {
      const defaultCatId = categories.length > 0 ? categories[0].id.toString() : '';
      setFormData({
        id: '',
        category_id: defaultCatId,
        hsn_profile_id: '',
        name: '',
        slug: '',
        description: '',
        is_new_arrival: false,
        is_featured: false,
        is_best_seller: false,
        status: 'active',
        category_name: '',
        hsn_profile_name: '',
        hsn_code: '',
        hsn_description: '',
        created_at: '',
        updated_at: ''
      });
      if (defaultCatId) {
        fetchHsnProfilesForCategory(defaultCatId);
      }
    } else if (product && product.id) {
      setModalLoading(true);
      try {
        // Fetch detailed product data from view.php
        const response = await fetch(`${API_BASE_URL}/product/view.php?id=${product.id}`, {
          headers: { 'Authorization': `Bearer ${token}` }
        });
        const result = await response.json();

        if (result.status && result.data && result.data.product) {
          const prod = result.data.product;
          const catId = prod.category?.id?.toString() || '';
          
          setFormData({
            id: prod.id,
            category_id: catId,
            hsn_profile_id: prod.hsn_profile?.id?.toString() || '',
            name: prod.name || '',
            slug: prod.slug || '',
            description: prod.description || '',
            is_new_arrival: Boolean(prod.is_new_arrival),
            is_featured: Boolean(prod.is_featured),
            is_best_seller: Boolean(prod.is_best_seller),
            status: prod.status || 'active',
            category_name: prod.category?.name || '',
            hsn_profile_name: prod.hsn_profile?.name || '',
            hsn_code: prod.hsn_profile?.hsn_code || '',
            hsn_description: prod.hsn_profile?.description || '',
            created_at: prod.created_at || '',
            updated_at: prod.updated_at || ''
          });

          if (catId) {
            await fetchHsnProfilesForCategory(catId);
          }
        } else {
          // Fallback to table row data
          setFormData({
            id: product.id,
            category_id: product.category_id?.toString() || '',
            hsn_profile_id: product.hsn_profile_id?.toString() || '',
            name: product.name || '',
            slug: product.slug || '',
            description: product.description || '',
            is_new_arrival: Boolean(product.is_new_arrival),
            is_featured: Boolean(product.is_featured),
            is_best_seller: Boolean(product.is_best_seller),
            status: product.status || 'active',
            category_name: product.category_name || '',
            hsn_profile_name: product.hsn_profile_name || '',
            hsn_code: product.hsn_code || '',
            hsn_description: product.hsn_description || '',
            created_at: product.created_at || '',
            updated_at: product.updated_at || ''
          });
          if (product.category_id) {
            await fetchHsnProfilesForCategory(product.category_id);
          }
        }
      } catch (err) {
        console.error('Failed to view product details:', err);
      } finally {
        setModalLoading(false);
      }
    }
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setModalError('');
    setModalLoading(false);
  };

  // Form Submit (Create / Update)
  const handleSubmit = async (e) => {
    e.preventDefault();
    if (modalMode === 'view') {
      closeModal();
      return;
    }

    const catId = parseInt(formData.category_id, 10);
    if (isNaN(catId) || catId <= 0) {
      setModalError('Please select a valid Category.');
      return;
    }

    const hsnId = parseInt(formData.hsn_profile_id, 10);
    if (isNaN(hsnId) || hsnId <= 0) {
      setModalError('Please select an HSN Tax Profile for this Category.');
      return;
    }

    const trimmedName = formData.name.trim();
    if (!trimmedName || trimmedName.length < 2) {
      setModalError('Product name is required and must contain at least 2 characters.');
      return;
    }
    if (trimmedName.length > 200) {
      setModalError('Product name must not exceed 200 characters.');
      return;
    }

    setIsSaving(true);
    setModalError('');

    const payload = {
      category_id: catId,
      hsn_profile_id: hsnId,
      name: trimmedName,
      description: formData.description.trim() || '',
      is_new_arrival: formData.is_new_arrival ? 1 : 0,
      is_featured: formData.is_featured ? 1 : 0,
      is_best_seller: formData.is_best_seller ? 1 : 0,
      status: formData.status
    };

    const isEdit = modalMode === 'edit';
    if (isEdit) {
      payload.id = parseInt(formData.id, 10);
    }

    const url = isEdit
      ? `${API_BASE_URL}/product/update.php`
      : `${API_BASE_URL}/product/create.php`;
    const method = isEdit ? 'PUT' : 'POST';

    try {
      const response = await fetch(url, {
        method,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(payload)
      });
      const result = await response.json();

      if (result.status) {
        closeModal();
        fetchProducts(isEdit ? currentPage : 1);
      } else {
        setModalError(result.message || 'Operation failed. Please check form inputs.');
      }
    } catch (err) {
      console.error(err);
      setModalError('Network error: Unable to save product changes.');
    } finally {
      setIsSaving(false);
    }
  };

  const isReadOnly = modalMode === 'view';

  return (
    <div className="admin-module-container">
      {/* Page Header */}
      <div className="admin-page-header">
        <div>
          <div className="admin-breadcrumb">
            <span>Admin</span> &gt; <span>Catalogue</span> &gt; <span className="active">Products</span>
          </div>
          <h1 className="admin-module-title">Products Master</h1>
          <p className="admin-module-desc">Manage product catalog, HSN tax assignments, and feature badges.</p>
        </div>
        <div className="admin-header-actions">
          <button className="admin-btn admin-btn-primary" onClick={() => openModal('add')}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Product
          </button>
        </div>
      </div>

      {/* Filter & Search Toolbar */}
      <div className="admin-filter-bar" style={{ display: 'flex', flexWrap: 'wrap', gap: '12px', marginBottom: '20px', alignItems: 'center' }}>
        <div style={{ flex: '1 1 260px', minWidth: '220px' }}>
          <AsyncSelect
            cacheOptions
            defaultOptions={false}
            loadOptions={loadProductOptions}
            value={selectedSearchOption}
            onChange={handleSearchSelectChange}
            placeholder="Search products by name, slug or description..."
            isClearable
            noOptionsMessage={({ inputValue }) =>
              !inputValue ? 'Type to search products...' : 'No matching products found'
            }
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '180px' }}>
          <Select
            options={[
              { value: '', label: 'All Categories' },
              ...categories.map(cat => ({ value: cat.id.toString(), label: cat.name }))
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
              { value: '', label: 'All Statuses' },
              { value: 'active', label: 'Active Only' },
              { value: 'inactive', label: 'Inactive' }
            ]}
            value={
              [
                { value: '', label: 'All Statuses' },
                { value: 'active', label: 'Active Only' },
                { value: 'inactive', label: 'Inactive' }
              ].find(o => o.value === statusFilter) || { value: '', label: 'All Statuses' }
            }
            onChange={(opt) => setStatusFilter(opt ? opt.value : '')}
            isClearable={false}
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '150px' }}>
          <Select
            options={[
              { value: '', label: 'All Tags' },
              { value: 'new_arrival', label: 'New Arrivals' },
              { value: 'featured', label: 'Featured' },
              { value: 'best_seller', label: 'Best Sellers' }
            ]}
            value={
              [
                { value: '', label: 'All Tags' },
                { value: 'new_arrival', label: 'New Arrivals' },
                { value: 'featured', label: 'Featured' },
                { value: 'best_seller', label: 'Best Sellers' }
              ].find(o => o.value === tagFilter) || { value: '', label: 'All Tags' }
            }
            onChange={(opt) => setTagFilter(opt ? opt.value : '')}
            isClearable={false}
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '160px' }}>
          <Select
            options={[
              { value: 'created_at-desc', label: 'Newest First' },
              { value: 'created_at-asc', label: 'Oldest First' },
              { value: 'name-asc', label: 'Name (A-Z)' },
              { value: 'name-desc', label: 'Name (Z-A)' }
            ]}
            value={
              [
                { value: 'created_at-desc', label: 'Newest First' },
                { value: 'created_at-asc', label: 'Oldest First' },
                { value: 'name-asc', label: 'Name (A-Z)' },
                { value: 'name-desc', label: 'Name (Z-A)' }
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

      {error && !isModalOpen && (
        <div className="admin-alert admin-alert-error" style={{ marginBottom: '20px', padding: '15px', backgroundColor: '#fdf2f8', color: '#9d174d', border: '1px solid #fbcfe8', borderRadius: '8px' }}>
          {error}
        </div>
      )}

      {/* Product Master Data Table */}
      <div className="admin-premium-card" style={{ padding: '0', overflow: 'hidden' }}>
        {isLoading ? (
          <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading products catalog...</div>
        ) : (
          <div className="admin-table-container">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Product Info</th>
                  <th>Category</th>
                  <th>HSN Tax Profile</th>
                  <th>Badges</th>
                  <th>Status</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {products.length === 0 ? (
                  <tr>
                    <td colSpan="6" style={{ padding: '40px', textAlign: 'center', color: '#9ca3af' }}>
                      No products found matching the criteria.
                    </td>
                  </tr>
                ) : (
                  products.map(prod => (
                    <tr key={prod.id}>
                      <td>
                        <div style={{ fontWeight: '600', color: '#111827' }}>{prod.name}</div>
                        <div style={{ fontSize: '12px', color: '#6b7280', fontFamily: 'monospace', marginTop: '2px' }}>
                          /{prod.slug}
                        </div>
                      </td>
                      <td>
                        <span style={{ display: 'inline-block', padding: '3px 8px', background: '#F6EDF6', color: '#A049A3', borderRadius: '4px', fontWeight: '600', fontSize: '12px' }}>
                          {prod.category_name || 'N/A'}
                        </span>
                      </td>
                      <td>
                        {prod.hsn_code ? (
                          <div>
                            <span style={{ fontWeight: '600', color: '#374151', fontSize: '13px' }}>
                              HSN: {prod.hsn_code}
                            </span>
                            {prod.hsn_profile_name && (
                              <div style={{ fontSize: '11px', color: '#6b7280' }}>
                                {prod.hsn_profile_name}
                              </div>
                            )}
                          </div>
                        ) : (
                          <span style={{ color: '#9ca3af', fontSize: '12px' }}>No HSN</span>
                        )}
                      </td>
                      <td>
                        <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                          {Boolean(prod.is_new_arrival) && <span className="admin-badge admin-badge-new">is_new_arrival</span>}
                          {Boolean(prod.is_featured) && <span className="admin-badge admin-badge-featured">is_featured</span>}
                          {Boolean(prod.is_best_seller) && <span className="admin-badge admin-badge-bestseller">is_best_seller</span>}
                          {!prod.is_new_arrival && !prod.is_featured && !prod.is_best_seller && (
                            <span style={{ color: '#9ca3af', fontSize: '12px' }}>-</span>
                          )}
                        </div>
                      </td>
                      <td>
                        <span className={`admin-badge ${prod.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>
                          {prod.status}
                        </span>
                      </td>
                      <td style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', alignItems: 'center', minHeight: '52px' }}>
                        <button
                          onClick={() => openModal('view', prod)}
                          className="admin-action-btn admin-action-view"
                        >
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                          View
                        </button>
                        <button
                          onClick={() => openModal('edit', prod)}
                          className="admin-action-btn admin-action-edit"
                        >
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                          </svg>
                          Edit
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}

        {!isLoading && totalPages > 1 && (
          <div className="admin-pagination-wrapper">
            <div className="admin-pagination-info">
              Showing {(currentPage - 1) * 10 + 1} to {Math.min(currentPage * 10, totalRecords)} of {totalRecords} products
            </div>
            <AdminPagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={(p) => fetchProducts(p)}
            />
          </div>
        )}
      </div>

      {/* Modal Dialog (Add, Edit, View) */}
      {isModalOpen && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '640px' }}>
            <div className="admin-modal-header">
              <h3 className="admin-modal-title">
                {modalMode === 'add' && 'Add New Product'}
                {modalMode === 'edit' && 'Edit Product'}
                {modalMode === 'view' && 'Product Details'}
              </h3>
              <button onClick={closeModal} className="admin-modal-close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
            </div>

            {modalError && (
              <div style={{ padding: '10px 16px', margin: '16px 24px 0', backgroundColor: '#fef2f2', color: '#991b1b', borderRadius: '6px', fontSize: '13px', border: '1px solid #fecaca' }}>
                {modalError}
              </div>
            )}

            {modalLoading ? (
              <div style={{ padding: '50px', textAlign: 'center', color: '#6b7280' }}>
                Loading details from server...
              </div>
            ) : isReadOnly ? (
              /* View Modal Content */
              <div>
                <div className="admin-modal-body">
                  <div className="admin-info-grid">
                    <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                      <span className="admin-info-label">Product Name</span>
                      <span className="admin-info-value" style={{ fontSize: '1.1rem', fontWeight: '700', color: '#221226' }}>
                        {formData.name}
                      </span>
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">Category</span>
                      <span className="admin-info-value" style={{ color: '#A049A3', fontWeight: '600' }}>
                        {formData.category_name || categories.find(c => c.id === parseInt(formData.category_id))?.name || 'N/A'}
                      </span>
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">Status</span>
                      <span className={`admin-badge ${formData.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>
                        {formData.status}
                      </span>
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">HSN Profile & Code</span>
                      <span className="admin-info-value" style={{ fontWeight: '600' }}>
                        {formData.hsn_code ? `HSN ${formData.hsn_code}` : 'N/A'}
                      </span>
                      {formData.hsn_profile_name && (
                        <div style={{ fontSize: '12px', color: '#6b7280', marginTop: '2px' }}>
                          Profile: {formData.hsn_profile_name}
                        </div>
                      )}
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">Slug / URL Alias</span>
                      <span className="admin-info-value" style={{ fontFamily: 'monospace', fontSize: '13px', color: '#4b5563' }}>
                        {formData.slug || 'N/A'}
                      </span>
                    </div>

                    <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                      <span className="admin-info-label">Badges / Feature Flags</span>
                      <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', marginTop: '4px' }}>
                        <span className={`admin-badge ${formData.is_new_arrival ? 'admin-badge-new' : 'admin-badge-inactive'}`}>
                          is_new_arrival: {formData.is_new_arrival ? 'Yes' : 'No'}
                        </span>
                        <span className={`admin-badge ${formData.is_featured ? 'admin-badge-featured' : 'admin-badge-inactive'}`}>
                          is_featured: {formData.is_featured ? 'Yes' : 'No'}
                        </span>
                        <span className={`admin-badge ${formData.is_best_seller ? 'admin-badge-bestseller' : 'admin-badge-inactive'}`}>
                          is_best_seller: {formData.is_best_seller ? 'Yes' : 'No'}
                        </span>
                      </div>
                    </div>

                    <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                      <span className="admin-info-label">Description</span>
                      <div style={{ fontSize: '13px', color: '#4b5563', lineHeight: '1.6', marginTop: '4px', whiteSpace: 'pre-wrap' }}>
                        {formData.description || <em style={{ color: '#9ca3af' }}>No description provided.</em>}
                      </div>
                    </div>

                    {formData.created_at && (
                      <div className="admin-info-box">
                        <span className="admin-info-label">Created At</span>
                        <span className="admin-info-value" style={{ fontSize: '12px', color: '#6b7280' }}>
                          {new Date(formData.created_at).toLocaleString()}
                        </span>
                      </div>
                    )}

                    {formData.updated_at && (
                      <div className="admin-info-box">
                        <span className="admin-info-label">Last Updated</span>
                        <span className="admin-info-value" style={{ fontSize: '12px', color: '#6b7280' }}>
                          {new Date(formData.updated_at).toLocaleString()}
                        </span>
                      </div>
                    )}
                  </div>
                </div>

                <div className="admin-modal-footer">
                  <button type="button" onClick={closeModal} className="admin-btn admin-btn-secondary">
                    Close
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setModalMode('edit');
                    }}
                    className="admin-btn admin-btn-primary"
                  >
                    Edit Product
                  </button>
                </div>
              </div>
            ) : (
              /* Create / Edit Modal Form */
              <form onSubmit={handleSubmit}>
                <div className="admin-modal-body">
                  <div className="admin-form-group">
                    <label htmlFor="name">Product Name *</label>
                    <input
                      type="text"
                      id="name"
                      name="name"
                      value={formData.name}
                      onChange={handleInputChange}
                      placeholder="e.g. Kanchipuram Pure Silk Saree"
                      className="admin-input"
                      required
                    />
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                    <div className="admin-form-group">
                      <label htmlFor="category_id">Parent Category *</label>
                      <Select
                        id="category_id"
                        name="category_id"
                        options={categories.map(cat => ({ value: cat.id.toString(), label: cat.name }))}
                        value={categories.map(cat => ({ value: cat.id.toString(), label: cat.name })).find(o => o.value === formData.category_id?.toString()) || null}
                        onChange={(opt) => {
                          const val = opt ? opt.value : '';
                          setFormData(prev => ({
                            ...prev,
                            category_id: val,
                            hsn_profile_id: ''
                          }));
                          if (modalError) setModalError('');
                          fetchHsnProfilesForCategory(val);
                        }}
                        placeholder="-- Select Category --"
                        isClearable={false}
                        menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                        styles={customSelectStyles}
                      />
                    </div>

                    <div className="admin-form-group">
                      <label htmlFor="hsn_profile_id">HSN Tax Profile *</label>
                      <Select
                        id="hsn_profile_id"
                        name="hsn_profile_id"
                        options={formHsnProfiles.map(hsn => ({ value: hsn.id.toString(), label: `${hsn.name} (HSN: ${hsn.hsn_code})` }))}
                        value={formHsnProfiles.map(hsn => ({ value: hsn.id.toString(), label: `${hsn.name} (HSN: ${hsn.hsn_code})` })).find(o => o.value === formData.hsn_profile_id?.toString()) || null}
                        onChange={(opt) => {
                          setFormData(prev => ({ ...prev, hsn_profile_id: opt ? opt.value : '' }));
                          if (modalError) setModalError('');
                        }}
                        placeholder={
                          isLoadingHsn
                            ? 'Loading HSN profiles...'
                            : formHsnProfiles.length === 0
                            ? 'No HSN profiles for category'
                            : '-- Select HSN Profile --'
                        }
                        isDisabled={isLoadingHsn || !formData.category_id}
                        isClearable={false}
                        menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                        styles={customSelectStyles}
                      />
                      {formHsnProfiles.length === 0 && formData.category_id && !isLoadingHsn && (
                        <small style={{ color: '#dc2626', fontSize: '11px', marginTop: '2px', display: 'block' }}>
                          No HSN Profile found for this category. Please create one under Master Data &gt; HSN.
                        </small>
                      )}
                    </div>
                  </div>

                  <div className="admin-form-group">
                    <label htmlFor="description">Product Description (Optional)</label>
                    <textarea
                      id="description"
                      name="description"
                      value={formData.description}
                      onChange={handleInputChange}
                      placeholder="Detailed fabric, weave, care instructions, and boutique craftsmanship information..."
                      className="admin-input"
                      rows="4"
                    />
                  </div>

                  <div className="admin-form-group">
                    <label htmlFor="status">Status</label>
                    <Select
                      id="status"
                      name="status"
                      options={[
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                      ]}
                      value={[
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                      ].find(o => o.value === formData.status) || { value: 'active', label: 'Active' }}
                      onChange={(opt) => setFormData(prev => ({ ...prev, status: opt ? opt.value : 'active' }))}
                      isClearable={false}
                      menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                      styles={customSelectStyles}
                    />
                  </div>
                </div>

                <div className="admin-modal-footer">
                  <button type="button" onClick={closeModal} className="admin-btn admin-btn-secondary">
                    Cancel
                  </button>
                  <button type="submit" className="admin-btn admin-btn-primary" disabled={isSaving}>
                    {isSaving ? 'Saving...' : modalMode === 'add' ? 'Create Product' : 'Save Changes'}
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminProducts;
