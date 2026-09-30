import React, { useState, useEffect } from 'react';
import { useAdminAuth } from '../../context/AuthContext.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';
import Select from 'react-select';
import AsyncSelect from 'react-select/async';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const getImageUrl = (imagePath) => {
  if (!imagePath) return '';
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://') || imagePath.startsWith('data:')) {
    return imagePath;
  }
  const cleanPath = imagePath.startsWith('/') ? imagePath.slice(1) : imagePath;
  const backendRoot = API_BASE_URL.replace(/\/api\/?$/, '');
  return `${backendRoot}/${cleanPath}`;
};

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

const AdminCategories = () => {
  const { token } = useAdminAuth();
  const [categories, setCategories] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedSearchOption, setSelectedSearchOption] = useState(null);

  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState('add'); // 'add', 'edit', or 'view'
  const [formData, setFormData] = useState({
    id: '',
    name: '',
    slug: '',
    description: '',
    sort_order: '0',
    status: 'active',
    image: null,
    imageUrl: null
  });
  const [isSaving, setIsSaving] = useState(false);

  // Fetch Categories
  const fetchCategories = async (page = 1, searchOverride = null) => {
    setIsLoading(true);
    setError('');
    try {
      const qVal = searchOverride !== null ? searchOverride : searchQuery;
      const queryParams = new URLSearchParams({
        page: page.toString(),
        limit: '10'
      });
      if (qVal.trim()) queryParams.append('q', qVal.trim());

      const response = await fetch(`${API_BASE_URL}/category/list.php?${queryParams.toString()}`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const result = await response.json();
      if (result.status && result.data) {
        setCategories(result.data.categories || []);
        if (result.data.pagination) {
          setCurrentPage(result.data.pagination.page);
          setTotalPages(result.data.pagination.total_pages);
          setTotalRecords(result.data.pagination.total_records);
        }
      } else {
        setCategories([]);
        setError(result.message || 'Failed to fetch categories');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while fetching categories.');
    }
    setIsLoading(false);
  };

  useEffect(() => {
    fetchCategories(1);
  }, [token]);

  // Load options dynamically for AsyncSelect search
  const loadCategoryOptions = async (inputValue) => {
    if (!inputValue || !inputValue.trim()) return [];
    try {
      const response = await fetch(
        `${API_BASE_URL}/category/list.php?q=${encodeURIComponent(inputValue.trim())}&limit=10`,
        { headers: { 'Authorization': `Bearer ${token}` } }
      );
      const result = await response.json();
      if (result.status && result.data?.categories) {
        return result.data.categories.map((c) => ({
          value: c.name,
          label: `${c.name} (${c.slug || ''})`,
          category: c
        }));
      }
      return [];
    } catch (err) {
      console.error('Error loading category search options:', err);
      return [];
    }
  };

  const handleSearchSelectChange = (selectedOption) => {
    setSelectedSearchOption(selectedOption);
    const query = selectedOption ? selectedOption.value : '';
    setSearchQuery(query);
    fetchCategories(1, query);
  };

  const hasActiveFilters = Boolean(searchQuery || selectedSearchOption);

  const handleClearFilters = () => {
    setSelectedSearchOption(null);
    setSearchQuery('');
    fetchCategories(1, '');
  };

  // Handle Input Changes
  const handleInputChange = (e) => {
    const { name, value, type, files } = e.target;
    if (type === 'file') {
      setFormData(prev => ({ ...prev, [name]: files[0] }));
    } else {
      setFormData(prev => ({ ...prev, [name]: value }));
    }
  };

  // Open Modal
  const openModal = (mode, category = null) => {
    setModalMode(mode);
    if ((mode === 'edit' || mode === 'view') && category) {
      setFormData({
        id: category.id,
        name: category.name || '',
        slug: category.slug || '',
        description: category.description || '',
        sort_order: category.sort_order?.toString() || '0',
        status: category.status || 'active',
        image: null, 
        imageUrl: category.image || null
      });
    } else {
      setFormData({
        id: '',
        name: '',
        slug: '',
        description: '',
        sort_order: '0',
        status: 'active',
        image: null,
        imageUrl: null
      });
    }
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setError('');
  };

  // Submit Form
  const handleSubmit = async (e) => {
    e.preventDefault();
    if (modalMode === 'view') {
      closeModal();
      return;
    }

    setIsSaving(true);
    setError('');

    const url = modalMode === 'add' 
      ? `${API_BASE_URL}/category/create.php`
      : `${API_BASE_URL}/category/update.php`;

    const payload = new FormData();
    if (modalMode === 'edit') payload.append('id', formData.id);
    payload.append('name', formData.name);
    if (formData.slug) payload.append('slug', formData.slug);
    if (formData.description) payload.append('description', formData.description);
    payload.append('sort_order', formData.sort_order);
    payload.append('status', formData.status);
    if (formData.image) {
      payload.append('image', formData.image);
    }

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: payload
      });
      const result = await response.json();
      
      if (result.status) {
        closeModal();
        fetchCategories(currentPage); 
      } else {
        setError(result.message || 'Operation failed');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while saving.');
    }
    setIsSaving(false);
  };

  const isReadOnly = modalMode === 'view';

  return (
    <div className="admin-module-container">
      <div className="admin-page-header">
        <div>
          <div className="admin-breadcrumb">
            <span>Admin</span> &gt; <span>Catalogue</span> &gt; <span className="active">Product Categories</span>
          </div>
          <h1 className="admin-module-title">Product Categories</h1>
          <p className="admin-module-desc">Manage your product categories, images, and statuses.</p>
        </div>
        <div className="admin-header-actions">
          <button className="admin-btn admin-btn-primary" onClick={() => openModal('add')}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Category
          </button>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="admin-filter-bar" style={{ display: 'flex', gap: '12px', flexWrap: 'wrap', marginBottom: '20px', alignItems: 'center' }}>
        <div style={{ flex: '1', minWidth: '260px' }}>
          <AsyncSelect
            cacheOptions
            defaultOptions={false}
            loadOptions={loadCategoryOptions}
            value={selectedSearchOption}
            onChange={handleSearchSelectChange}
            placeholder="Search categories by name or slug..."
            isClearable
            noOptionsMessage={({ inputValue }) =>
              !inputValue ? 'Type to search categories...' : 'No matching categories found'
            }
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

      <div className="admin-premium-card" style={{ padding: '0', overflow: 'hidden' }}>
        {isLoading ? (
          <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading categories...</div>
        ) : (
          <div className="admin-table-container">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Image</th>
                  <th>Name</th>
                  <th>Slug</th>
                  <th>Sort Order</th>
                  <th>Status</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {categories.length === 0 ? (
                  <tr>
                    <td colSpan="6" style={{ padding: '40px', textAlign: 'center', color: '#9ca3af' }}>
                      No categories found.
                    </td>
                  </tr>
                ) : (
                  categories.map(cat => (
                    <tr key={cat.id}>
                      <td>
                        {cat.image ? (
                          <img 
                            src={getImageUrl(cat.image)} 
                            alt={cat.name} 
                            style={{ width: '48px', height: '48px', objectFit: 'cover', borderRadius: '6px', border: '1px solid #e5e7eb' }}
                          />
                        ) : (
                          <div style={{ width: '48px', height: '48px', backgroundColor: '#f3f4f6', borderRadius: '6px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10px', color: '#9ca3af', border: '1px solid #e5e7eb' }}>No Img</div>
                        )}
                      </td>
                      <td style={{ fontWeight: '600', color: '#111827' }}>{cat.name}</td>
                      <td>{cat.slug}</td>
                      <td>{cat.sort_order}</td>
                      <td>
                        <span className={`admin-badge ${cat.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>
                          {cat.status}
                        </span>
                      </td>
                      <td style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', alignItems: 'center', height: '100%', minHeight: '52px' }}>
                        <button 
                          onClick={() => openModal('view', cat)}
                          className="admin-action-btn admin-action-view"
                        >
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                          View
                        </button>
                        <button 
                          onClick={() => openModal('edit', cat)}
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
              Showing {(currentPage - 1) * 10 + 1} to {Math.min(currentPage * 10, totalRecords)} of {totalRecords} categories
            </div>
            <AdminPagination 
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={(page) => fetchCategories(page)}
            />
          </div>
        )}
      </div>

      {isModalOpen && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '550px' }}>
            <div className="admin-modal-header">
              <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#111827', fontWeight: '700' }}>
                {modalMode === 'add' ? 'Add New Category' : modalMode === 'edit' ? 'Edit Category' : 'Category Details'}
              </h2>
              <button onClick={closeModal} className="admin-modal-close">
                &times;
              </button>
            </div>
            
            <div className="admin-modal-body">

            {error && (
              <div style={{ marginBottom: '20px', padding: '12px 16px', backgroundColor: '#fdf2f8', color: '#9d174d', borderRadius: '8px', fontSize: '0.875rem', border: '1px solid #fbcfe8', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                {error}
              </div>
            )}

            {isReadOnly ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '8px' }}>
                  {formData.imageUrl ? (
                    <img 
                      src={getImageUrl(formData.imageUrl)} 
                      alt={formData.name} 
                      style={{ maxWidth: '100%', maxHeight: '250px', objectFit: 'contain', borderRadius: '12px', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1)', border: '1px solid #F6EDF6' }}
                    />
                  ) : (
                    <div style={{ width: '120px', height: '120px', backgroundColor: '#f3f4f6', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9ca3af', border: '4px solid #fdf2f8', fontSize: '0.875rem', fontWeight: '500' }}>No Image</div>
                  )}
                </div>
                
                <div className="admin-info-grid">
                  <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                    <span className="admin-info-label">Category Name</span>
                    <span className="admin-info-value">{formData.name}</span>
                  </div>
                  
                  <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                    <span className="admin-info-label">Slug / URL</span>
                    <span style={{ fontSize: '0.9rem', color: '#4b5563', fontFamily: 'monospace', backgroundColor: '#e5e7eb', padding: '2px 6px', borderRadius: '4px' }}>{formData.slug || 'N/A'}</span>
                  </div>

                  <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                    <span className="admin-info-label">Description</span>
                    <span style={{ fontSize: '0.9rem', color: '#4b5563', lineHeight: '1.5' }}>{formData.description || <em style={{color: '#9ca3af'}}>No description provided.</em>}</span>
                  </div>

                  <div className="admin-info-box">
                    <span className="admin-info-label">Sort Order</span>
                    <span className="admin-info-value">{formData.sort_order}</span>
                  </div>
                  <div className="admin-info-box">
                    <span className="admin-info-label">Status</span>
                    <span className={`admin-badge ${formData.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>
                      {formData.status}
                    </span>
                  </div>
                </div>
              </div>
            ) : (
              <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                <div>
                  <label className="admin-label">Category Name <span style={{color: '#ef4444'}}>*</span></label>
                  <input 
                    type="text" 
                    name="name" 
                    value={formData.name} 
                    onChange={handleInputChange} 
                    required
                    placeholder="E.g., Designer Sarees"
                    className="admin-input"
                  />
                </div>

                <div>
                  <label className="admin-label">Slug URL <span style={{fontWeight: '400', color: '#9ca3af'}}>(Optional)</span></label>
                  <input 
                    type="text" 
                    name="slug" 
                    value={formData.slug} 
                    onChange={handleInputChange} 
                    placeholder="Auto-generated if left blank"
                    className="admin-input"
                  />
                </div>

                <div>
                  <label className="admin-label">Description</label>
                  <textarea 
                    name="description" 
                    value={formData.description} 
                    onChange={handleInputChange} 
                    rows="3"
                    placeholder="Brief description about this category..."
                    className="admin-input"
                  />
                </div>

                <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
                  <div style={{ flex: '1 1 calc(50% - 8px)' }}>
                    <label className="admin-label">Sort Order</label>
                    <input 
                      type="number" 
                      name="sort_order" 
                      value={formData.sort_order} 
                      onChange={handleInputChange} 
                      min="0"
                      className="admin-input"
                    />
                  </div>
                  <div style={{ flex: '1 1 calc(50% - 8px)' }}>
                    <label className="admin-label">Status</label>
                    <Select
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

                <div>
                  <label className="admin-label">Category Image</label>
                  <div style={{ border: '2px dashed #d1d5db', padding: '16px', borderRadius: '8px', textAlign: 'center', backgroundColor: '#f9fafb' }}>
                    <input 
                      type="file" 
                      name="image" 
                      onChange={handleInputChange} 
                      accept="image/jpeg,image/png,image/webp"
                      style={{ width: '100%', boxSizing: 'border-box', fontSize: '0.875rem', cursor: 'pointer' }}
                    />
                  </div>
                  {modalMode === 'edit' && (
                    <p style={{ marginTop: '8px', fontSize: '0.75rem', color: '#6b7280', display: 'flex', alignItems: 'center', gap: '4px' }}>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                      Leave empty to keep existing image.
                    </p>
                  )}
                </div>
              </form>
            )}
            </div>

            <div className="admin-modal-footer">
              <button type="button" onClick={closeModal} className="admin-btn admin-btn-secondary">
                {isReadOnly ? 'Close' : 'Cancel'}
              </button>
              {!isReadOnly && (
                <button 
                  type="submit" 
                  disabled={isSaving}
                  onClick={handleSubmit}
                  className="admin-btn admin-btn-primary"
                  style={{ opacity: isSaving ? 0.7 : 1, cursor: isSaving ? 'not-allowed' : 'pointer' }}
                >
                  {isSaving ? (
                    <>
                      <svg className="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ animation: 'spin 1s linear infinite' }}><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                      Saving...
                    </>
                  ) : (
                    'Save Category'
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

export default AdminCategories;
