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

const getImageUrl = (imagePath) => {
  if (!imagePath) return '';
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://') || imagePath.startsWith('data:')) {
    return imagePath;
  }
  const cleanPath = imagePath.startsWith('/') ? imagePath.slice(1) : imagePath;
  const backendRoot = API_BASE_URL.replace(/\/api\/?$/, '');
  return `${backendRoot}/${cleanPath}`;
};

const AdminNewArrivals = () => {
  const { token } = useAdminAuth();

  // Data States
  const [newArrivals, setNewArrivals] = useState([]);
  const [summary, setSummary] = useState({
    total_records: 0,
    active_count: 0,
    inactive_count: 0,
    available_count: 0,
    unavailable_count: 0
  });
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

  // Filters & Search
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedSearchOption, setSelectedSearchOption] = useState(null);
  const [stockStatusFilter, setStockStatusFilter] = useState('');
  const [sortBy, setSortBy] = useState('sort_order');
  const [sortOrder, setSortOrder] = useState('asc');

  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);
  const perPage = 10;

  // Add / Edit Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState('add'); // 'add', 'edit', 'view'
  const [selectedVariantOption, setSelectedVariantOption] = useState(null);
  const [selectedRecord, setSelectedRecord] = useState(null);
  const [formData, setFormData] = useState({
    id: '',
    product_variant_id: '',
    sort_order: '0',
    status: 'active'
  });
  const [isSaving, setIsSaving] = useState(false);
  const [modalError, setModalError] = useState('');
  const [actionLoadingId, setActionLoadingId] = useState(null);

  // Fetch New Arrivals List
  const fetchNewArrivals = async (page = 1, searchOverride = null, stockStatusOverride = null) => {
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
      const ssVal = stockStatusOverride !== null ? stockStatusOverride : stockStatusFilter;
      if (qVal && qVal.trim()) queryParams.append('q', qVal.trim());
      if (ssVal) queryParams.append('stock_status', ssVal);

      const response = await fetch(`${API_BASE_URL}/new_arrival/list.php?${queryParams.toString()}`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const result = await response.json();

      if ((result.status || result.success) && result.data) {
        setNewArrivals(result.data.new_arrivals || []);
        if (result.data.summary) {
          setSummary(result.data.summary);
        }
        if (result.data.pagination) {
          setCurrentPage(result.data.pagination.page || 1);
          setTotalPages(result.data.pagination.total_pages || 1);
          setTotalRecords(result.data.pagination.total_records || 0);
        }
      } else {
        setNewArrivals([]);
        setError(result.message || 'Failed to load new arrivals.');
      }
    } catch (err) {
      console.error('Error loading new arrivals:', err);
      setError('Network error: Unable to connect to New Arrival service.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (token) {
      fetchNewArrivals(1);
    }
  }, [token, stockStatusFilter, sortBy, sortOrder]);

  // Load variant search options dynamically for AsyncSelect in Add Modal
  const loadVariantOptions = async (inputValue) => {
    if (!inputValue || !inputValue.trim()) return [];
    try {
      const response = await fetch(
        `${API_BASE_URL}/varient/list.php?q=${encodeURIComponent(inputValue.trim())}&limit=15`,
        { headers: { 'Authorization': `Bearer ${token}` } }
      );
      const result = await response.json();
      if ((result.status || result.success) && result.data?.variants) {
        return result.data.variants.map((v) => {
          const colorName = v.color?.name ? ` • ${v.color.name}` : '';
          const sizeName = v.size?.name ? ` • Size: ${v.size.name}` : '';
          const prodName = v.product?.name || 'Product';
          return {
            value: v.id,
            label: `${v.sku} - ${prodName}${colorName}${sizeName} (₹${v.pricing?.selling_price || '0'})`,
            variantData: v
          };
        });
      }
      return [];
    } catch (err) {
      console.error('Failed to load variants:', err);
      return [];
    }
  };

  // Quick Remove / Deactivate or Reactivate
  const handleToggleStatus = async (record) => {
    const nextStatus = record.status === 'active' ? 'inactive' : 'active';
    const actionLabel = nextStatus === 'active' ? 'Reactivated' : 'Removed from New Arrivals';
    setActionLoadingId(record.id);
    setError('');
    setSuccessMessage('');

    try {
      const response = await fetch(`${API_BASE_URL}/new_arrival/update.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          id: record.id,
          status: nextStatus
        })
      });

      const result = await response.json();
      if (result.status || result.success) {
        setSuccessMessage(`Success: ${record.product?.name || 'Variant'} ${actionLabel}.`);
        fetchNewArrivals(currentPage);
      } else {
        setError(result.message || 'Failed to update new arrival status.');
      }
    } catch (err) {
      console.error('Status update error:', err);
      setError('An error occurred while updating status.');
    } finally {
      setActionLoadingId(null);
    }
  };

  // Modal handlers
  const openAddModal = () => {
    setModalMode('add');
    setSelectedVariantOption(null);
    setSelectedRecord(null);
    setFormData({
      id: '',
      product_variant_id: '',
      sort_order: '0',
      status: 'active'
    });
    setModalError('');
    setIsModalOpen(true);
  };

  const openEditModal = (record) => {
    setModalMode('edit');
    setSelectedRecord(record);
    setSelectedVariantOption({
      value: record.product_variant_id,
      label: `${record.product_variant?.sku || record.sku} - ${record.product?.name || ''}`
    });
    setFormData({
      id: record.id,
      product_variant_id: record.product_variant_id,
      sort_order: String(record.sort_order ?? 0),
      status: record.status || 'active'
    });
    setModalError('');
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setSelectedVariantOption(null);
    setSelectedRecord(null);
    setModalError('');
  };

  const handleModalSubmit = async (e) => {
    e.preventDefault();
    setModalError('');
    setIsSaving(true);

    try {
      if (modalMode === 'add') {
        if (!formData.product_variant_id) {
          setModalError('Please select a product variant to add to New Arrivals.');
          setIsSaving(false);
          return;
        }

        const response = await fetch(`${API_BASE_URL}/new_arrival/create.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            product_variant_id: parseInt(formData.product_variant_id, 10),
            sort_order: parseInt(formData.sort_order || '0', 10),
            status: formData.status
          })
        });

        const result = await response.json();
        if (result.status) {
          setSuccessMessage('Product variant successfully added to New Arrivals.');
          closeModal();
          fetchNewArrivals(1);
        } else {
          // If already exists, offer to reactivate
          if (result.data?.existing_new_arrival?.id) {
            setModalError(`${result.message} (Record ID: ${result.data.existing_new_arrival.id})`);
          } else {
            setModalError(result.message || 'Failed to add variant to New Arrivals.');
          }
        }
      } else if (modalMode === 'edit') {
        const response = await fetch(`${API_BASE_URL}/new_arrival/update.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            id: parseInt(formData.id, 10),
            sort_order: parseInt(formData.sort_order || '0', 10),
            status: formData.status
          })
        });

        const result = await response.json();
        if (result.status) {
          setSuccessMessage('New arrival settings updated successfully.');
          closeModal();
          fetchNewArrivals(currentPage);
        } else {
          setModalError(result.message || 'Failed to update new arrival.');
        }
      }
    } catch (err) {
      console.error('Save error:', err);
      setModalError('Network error while saving new arrival.');
    } finally {
      setIsSaving(false);
    }
  };

  const loadSearchOptions = async (inputValue) => {
    if (!inputValue || !inputValue.trim()) return [];
    try {
      const res = await fetch(`${API_BASE_URL}/new_arrival/list.php?q=${encodeURIComponent(inputValue.trim())}&limit=10`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const data = await res.json();
      if ((data.status || data.success) && data.data?.new_arrivals) {
        return data.data.new_arrivals.map(item => {
          const v = item.product_variant || {};
          const p = item.product || {};
          const labelParts = [v.sku || `ID: ${item.id}`, p.name || 'Product'];
          if (v.variant_name) labelParts.push(v.variant_name);
          if (v.color?.name) labelParts.push(v.color.name);
          if (v.size?.name) labelParts.push(`Size: ${v.size.name}`);
          return {
            value: v.sku || p.name || item.id.toString(),
            label: labelParts.join(' • ')
          };
        });
      }
      return [];
    } catch (e) {
      return [];
    }
  };

  const handleSearchSelectChange = (selectedOption) => {
    setSelectedSearchOption(selectedOption);
    const query = selectedOption ? selectedOption.value : '';
    setSearchQuery(query);
    fetchNewArrivals(1, query);
  };

  const handleClearFilters = () => {
    setSearchQuery('');
    setSelectedSearchOption(null);
    setStockStatusFilter('');
    setSortBy('sort_order');
    setSortOrder('asc');
    fetchNewArrivals(1, '', '');
  };

  return (
    <div className="admin-module-container">
      {/* Page Header */}
      <div className="admin-page-header">
        <div>
          <div className="admin-breadcrumb">
            <span>Catalogue</span> / <span className="active">New Arrivals</span>
          </div>
          <h1 className="admin-module-title">New Arrivals Management</h1>
          <p className="admin-module-desc">
            Manage and prioritize products displayed in the dedicated New Arrivals store section.
          </p>
        </div>
        <div className="admin-header-actions">
          <button
            type="button"
            onClick={() => fetchNewArrivals(currentPage)}
            className="admin-btn admin-btn-secondary"
            title="Refresh List"
            disabled={isLoading}
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={isLoading ? 'spinner' : ''}>
              <path d="M23 4v6h-6"></path>
              <path d="M1 20v-6h6"></path>
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
            </svg>
            Refresh
          </button>
          <button
            type="button"
            onClick={openAddModal}
            className="admin-btn admin-btn-primary"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add New Arrival
          </button>
        </div>
      </div>

      {/* KPI Stats Cards */}
      <div className="admin-stats-grid">
        <div className="admin-stat-card">
          <span className="stat-label">Total in Collection</span>
          <div className="stat-value">{summary.total_records || totalRecords}</div>
        </div>
        <div className="admin-stat-card">
          <span className="stat-label">Active on Website</span>
          <div className="stat-value" style={{ color: '#059669' }}>{summary.active_count || 0}</div>
        </div>
        <div className="admin-stat-card">
          <span className="stat-label">Inactive / Hidden</span>
          <div className="stat-value" style={{ color: '#6b7280' }}>{summary.inactive_count || 0}</div>
        </div>
        <div className="admin-stat-card">
          <span className="stat-label">In Stock Variants</span>
          <div className="stat-value" style={{ color: '#A049A3' }}>{summary.available_count || 0}</div>
        </div>
      </div>

      {/* Notifications */}
      {error && (
        <div className="admin-alert admin-alert-error" style={{ margin: '0' }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span>{error}</span>
        </div>
      )}
      {successMessage && (
        <div className="admin-alert" style={{ background: '#ecfdf5', border: '1px solid #a7f3d0', color: '#047857', margin: '0' }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <span>{successMessage}</span>
        </div>
      )}

      {/* Search & Filter Controls */}
      <div className="admin-filter-bar" style={{ display: 'flex', flexWrap: 'wrap', gap: '12px', marginBottom: '20px', alignItems: 'center' }}>
        <div style={{ flex: '1 1 280px', minWidth: '240px' }}>
          <AsyncSelect
            cacheOptions
            defaultOptions={false}
            loadOptions={loadSearchOptions}
            value={selectedSearchOption}
            onChange={handleSearchSelectChange}
            placeholder="Search by SKU, Product Name, or Variant..."
            isClearable
            noOptionsMessage={({ inputValue }) =>
              !inputValue ? 'Type to search new arrivals...' : 'No matching new arrivals found'
            }
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '160px' }}>
          <Select
            options={[
              { value: '', label: 'All Stock Status' },
              { value: 'in_stock', label: 'In Stock' },
              { value: 'low_stock', label: 'Low Stock' },
              { value: 'out_of_stock', label: 'Out of Stock' }
            ]}
            value={[
              { value: '', label: 'All Stock Status' },
              { value: 'in_stock', label: 'In Stock' },
              { value: 'low_stock', label: 'Low Stock' },
              { value: 'out_of_stock', label: 'Out of Stock' }
            ].find(o => o.value === stockStatusFilter) || { value: '', label: 'All Stock Status' }}
            onChange={(opt) => setStockStatusFilter(opt ? opt.value : '')}
            isClearable={false}
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '180px' }}>
          <Select
            options={[
              { value: 'sort_order-asc', label: 'Sort Order (Low to High)' },
              { value: 'sort_order-desc', label: 'Sort Order (High to Low)' },
              { value: 'created_at-desc', label: 'Recently Added' },
              { value: 'selling_price-asc', label: 'Price (Low to High)' },
              { value: 'selling_price-desc', label: 'Price (High to Low)' },
              { value: 'stock_quantity-desc', label: 'Stock (High to Low)' },
              { value: 'sku-asc', label: 'SKU (A-Z)' }
            ]}
            value={[
              { value: 'sort_order-asc', label: 'Sort Order (Low to High)' },
              { value: 'sort_order-desc', label: 'Sort Order (High to Low)' },
              { value: 'created_at-desc', label: 'Recently Added' },
              { value: 'selling_price-asc', label: 'Price (Low to High)' },
              { value: 'selling_price-desc', label: 'Price (High to Low)' },
              { value: 'stock_quantity-desc', label: 'Stock (High to Low)' },
              { value: 'sku-asc', label: 'SKU (A-Z)' }
            ].find(o => o.value === `${sortBy}-${sortOrder}`) || { value: 'sort_order-asc', label: 'Sort Order (Low to High)' }}
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

        {(searchQuery || stockStatusFilter || sortBy !== 'sort_order' || sortOrder !== 'asc') && (
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

      {/* Main Table */}
      <div className="admin-premium-card" style={{ padding: '0', overflow: 'hidden' }}>
        {isLoading ? (
          <div style={{ padding: '60px', textAlign: 'center', color: '#6b7280' }}>
            <svg className="spinner" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#A049A3" strokeWidth="2" style={{ animation: 'spin 1s linear infinite', marginBottom: '8px' }}>
              <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            <div>Loading New Arrivals catalog...</div>
          </div>
        ) : (
          <div className="admin-table-container">
            <table className="admin-table">
              <thead>
                <tr>
                  <th style={{ width: '60px' }}>Image</th>
                  <th>SKU & Variant</th>
                  <th>Parent Product</th>
                  <th>Attributes</th>
                  <th>Price</th>
                  <th>Stock Status</th>
                  <th style={{ textAlign: 'center' }}>Sort Order</th>
                  <th>Status</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {newArrivals.length === 0 ? (
                  <tr>
                    <td colSpan="9" style={{ padding: '60px', textAlign: 'center', color: '#9ca3af' }}>
                      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" strokeWidth="1.5" style={{ marginBottom: '10px' }}>
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                      </svg>
                      <div style={{ fontSize: '1.05rem', fontWeight: '600', color: '#374151' }}>No New Arrivals Found</div>
                      <p style={{ margin: '4px 0 14px 0', fontSize: '0.88rem' }}>Click "Add New Arrival" to feature product variants on the Home page.</p>
                      <button
                        type="button"
                        onClick={openAddModal}
                        className="admin-btn admin-btn-primary"
                        style={{ display: 'inline-flex' }}
                      >
                        + Add First Variant
                      </button>
                    </td>
                  </tr>
                ) : (
                  newArrivals.map((item) => {
                    const variant = item.product_variant || {};
                    const product = item.product || {};
                    const primaryImg = variant.primary_image || (variant.images && variant.images.length > 0 ? variant.images[0] : null);
                    const sellingPrice = variant.pricing?.selling_price || '0.00';
                    const originalPrice = variant.pricing?.original_price || '0.00';
                    const stockQty = variant.stock?.available_quantity ?? variant.stock?.stock_quantity ?? 0;
                    const isOutOfStock = stockQty <= 0;

                    return (
                      <tr key={item.id}>
                        {/* Image Thumbnail */}
                        <td>
                          <div
                            style={{
                              width: '46px',
                              height: '46px',
                              borderRadius: '8px',
                              backgroundColor: '#f6edf6',
                              overflow: 'hidden',
                              display: 'flex',
                              alignItems: 'center',
                              justifyContent: 'center',
                              border: '1px solid #ebd7ed'
                            }}
                          >
                            {primaryImg?.image ? (
                              <img
                                src={getImageUrl(primaryImg.image)}
                                alt={variant.sku || 'Variant'}
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
                          </div>
                        </td>

                        {/* SKU & Variant Name */}
                        <td>
                          <div style={{ fontWeight: '700', color: '#111827', fontFamily: 'monospace', fontSize: '0.92rem' }}>
                            {variant.sku || '-'}
                          </div>
                          {variant.variant_name && (
                            <div style={{ fontSize: '0.8rem', color: '#6b7280', marginTop: '2px' }}>
                              {variant.variant_name}
                            </div>
                          )}
                        </td>

                        {/* Product & Category */}
                        <td>
                          <div style={{ fontWeight: '600', color: '#374151' }}>
                            {product.name || '-'}
                          </div>
                          {product.category?.name && (
                            <div style={{ fontSize: '0.75rem', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                              {product.category.name}
                            </div>
                          )}
                        </td>

                        {/* Attributes (Size & Color) */}
                        <td>
                          <div style={{ display: 'flex', alignItems: 'center', gap: '6px', flexWrap: 'wrap' }}>
                            {variant.size?.name && (
                              <span style={{ padding: '2px 7px', background: '#f3f4f6', borderRadius: '5px', fontWeight: '600', fontSize: '0.78rem', color: '#374151' }}>
                                {variant.size.name}
                              </span>
                            )}
                            {variant.color?.name && (
                              <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '2px 7px', background: '#fdfafd', border: '1px solid #ebd7ed', borderRadius: '5px', fontSize: '0.78rem', color: '#374151' }}>
                                <span style={{ width: '9px', height: '9px', borderRadius: '50%', backgroundColor: variant.color.hex_code || '#ccc', border: '1px solid #d1d5db' }}></span>
                                {variant.color.name}
                              </span>
                            )}
                          </div>
                        </td>

                        {/* Pricing */}
                        <td>
                          <div style={{ fontWeight: '700', color: '#059669', fontSize: '0.95rem' }}>
                            ₹{sellingPrice}
                          </div>
                          {parseFloat(originalPrice) > parseFloat(sellingPrice) && (
                            <div style={{ fontSize: '0.75rem', color: '#9ca3af', textDecoration: 'line-through' }}>
                              ₹{originalPrice}
                            </div>
                          )}
                        </td>

                        {/* Stock Status */}
                        <td>
                          <span className={`admin-badge ${isOutOfStock ? 'admin-badge-blocked' : 'admin-badge-active'}`}>
                            {isOutOfStock ? 'Out of Stock' : `${stockQty} In Stock`}
                          </span>
                        </td>

                        {/* Sort Order */}
                        <td style={{ textAlign: 'center', fontWeight: '700', color: '#4b5563' }}>
                          {item.sort_order ?? 0}
                        </td>

                        {/* Status */}
                        <td>
                          <span className={`admin-badge ${item.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>
                            {item.status === 'active' ? 'Active' : 'Inactive'}
                          </span>
                        </td>

                        {/* Actions */}
                        <td style={{ textAlign: 'right' }}>
                          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '6px', alignItems: 'center' }}>
                            <button
                              type="button"
                              onClick={() => openEditModal(item)}
                              className="admin-action-btn admin-action-edit"
                              title="Edit Sort Order & Settings"
                            >
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                              </svg>
                              Edit
                            </button>

                            <button
                              type="button"
                              disabled={actionLoadingId === item.id}
                              onClick={() => handleToggleStatus(item)}
                              className={`admin-action-btn ${item.status === 'active' ? 'admin-action-delete' : 'admin-action-status'}`}
                              title={item.status === 'active' ? 'Remove from New Arrivals' : 'Reactivate New Arrival'}
                            >
                              {actionLoadingId === item.id ? (
                                '...'
                              ) : item.status === 'active' ? (
                                <>
                                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                  </svg>
                                  Remove
                                </>
                              ) : (
                                <>
                                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                  </svg>
                                  Activate
                                </>
                              )}
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
              Showing {(currentPage - 1) * perPage + 1} to {Math.min(currentPage * perPage, totalRecords)} of {totalRecords} items
            </div>
            <AdminPagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={(page) => fetchNewArrivals(page)}
            />
          </div>
        )}
      </div>

      {/* Add / Edit Modal */}
      {isModalOpen && (
        <div className="admin-modal-backdrop" onClick={closeModal}>
          <div className="admin-modal-card" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '540px' }}>
            <div className="admin-modal-header">
              <h2 className="admin-modal-title">
                {modalMode === 'add' ? 'Add Product Variant to New Arrivals' : 'Edit New Arrival Settings'}
              </h2>
              <button type="button" onClick={closeModal} className="admin-modal-close-btn">&times;</button>
            </div>

            <form onSubmit={handleModalSubmit}>
              <div className="admin-modal-body" style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                {modalError && (
                  <div className="admin-alert admin-alert-error" style={{ margin: '0' }}>
                    <span>{modalError}</span>
                  </div>
                )}

                {/* Variant Selection (Async search on Add, read-only on Edit) */}
                <div className="form-group">
                  <label className="admin-form-label">
                    Select Product Variant <span style={{ color: '#dc2626' }}>*</span>
                  </label>
                  {modalMode === 'add' ? (
                    <AsyncSelect
                      cacheOptions
                      defaultOptions
                      loadOptions={loadVariantOptions}
                      value={selectedVariantOption}
                      onChange={(opt) => {
                        setSelectedVariantOption(opt);
                        setFormData((prev) => ({ ...prev, product_variant_id: opt ? opt.value : '' }));
                      }}
                      placeholder="Type SKU or Product Name to search..."
                      styles={customSelectStyles}
                      menuPortalTarget={document.body}
                    />
                  ) : (
                    <input
                      type="text"
                      className="admin-form-input"
                      value={selectedVariantOption?.label || ''}
                      disabled
                      style={{ background: '#f3f4f6' }}
                    />
                  )}
                </div>

                {/* Selected Variant Summary Preview */}
                {selectedVariantOption?.variantData && (
                  <div style={{ background: '#faf4fa', border: '1px solid #ebd7ed', borderRadius: '8px', padding: '12px' }}>
                    <div style={{ fontSize: '0.85rem', fontWeight: '700', color: '#A049A3' }}>
                      {selectedVariantOption.variantData.product?.name} ({selectedVariantOption.variantData.sku})
                    </div>
                    <div style={{ fontSize: '0.8rem', color: '#4b5563', marginTop: '4px' }}>
                      Selling Price: <strong>₹{selectedVariantOption.variantData.pricing?.selling_price}</strong> • Stock: <strong>{selectedVariantOption.variantData.stock?.stock_quantity}</strong>
                    </div>
                  </div>
                )}

                {/* Sort Order */}
                <div className="form-group">
                  <label className="admin-form-label">
                    Display Sort Order (Lower numbers appear first)
                  </label>
                  <input
                    type="number"
                    min="0"
                    className="admin-form-input"
                    value={formData.sort_order}
                    onChange={(e) => setFormData({ ...formData, sort_order: e.target.value })}
                    placeholder="0"
                  />
                </div>

                {/* Status */}
                <div className="form-group">
                  <label className="admin-form-label">Status</label>
                  <Select
                    options={[
                      { value: 'active', label: 'Active (Visible on Home New Arrivals)' },
                      { value: 'inactive', label: 'Inactive (Hidden from Website)' }
                    ]}
                    value={[
                      { value: 'active', label: 'Active (Visible on Home New Arrivals)' },
                      { value: 'inactive', label: 'Inactive (Hidden from Website)' }
                    ].find((o) => o.value === formData.status)}
                    onChange={(opt) => setFormData({ ...formData, status: opt ? opt.value : 'active' })}
                    styles={customSelectStyles}
                    menuPortalTarget={document.body}
                  />
                </div>
              </div>

              <div className="admin-modal-footer">
                <button type="button" onClick={closeModal} className="admin-btn admin-btn-secondary">
                  Cancel
                </button>
                <button type="submit" disabled={isSaving} className="admin-btn admin-btn-primary">
                  {isSaving ? 'Saving...' : modalMode === 'add' ? 'Add to New Arrivals' : 'Save Changes'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminNewArrivals;
