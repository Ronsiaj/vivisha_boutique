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

const AdminSizes = () => {
  const { token } = useAdminAuth();
  const [sizes, setSizes] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedSearchOption, setSelectedSearchOption] = useState(null);
  const [statusFilter, setStatusFilter] = useState('');

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
    sort_order: '0',
    status: 'active'
  });
  const [isSaving, setIsSaving] = useState(false);
  const [modalError, setModalError] = useState('');

  // Fetch Sizes
  const fetchSizes = async (page = 1, searchOverride = null, statusOverride = null) => {
    setIsLoading(true);
    setError('');
    try {
      const queryParams = new URLSearchParams({
        page: page.toString(),
        limit: '15'
      });
      const qVal = searchOverride !== null ? searchOverride : searchQuery;
      const sVal = statusOverride !== null ? statusOverride : statusFilter;
      if (qVal.trim()) queryParams.append('q', qVal.trim());
      if (sVal) queryParams.append('status', sVal);

      const response = await fetch(`${API_BASE_URL}/size/list.php?${queryParams.toString()}`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const result = await response.json();
      if (result.status && result.data) {
        setSizes(result.data.sizes || []);
        if (result.data.pagination) {
          setCurrentPage(result.data.pagination.page);
          setTotalPages(result.data.pagination.total_pages);
          setTotalRecords(result.data.pagination.total_records);
        }
      } else {
        setError(result.message || 'Failed to fetch sizes');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while connecting to the size service.');
    }
    setIsLoading(false);
  };

  useEffect(() => {
    fetchSizes(1);
  }, [token, statusFilter]);

  // Load options dynamically for AsyncSelect search
  const loadSizeOptions = async (inputValue) => {
    if (!inputValue || !inputValue.trim()) return [];
    try {
      const response = await fetch(
        `${API_BASE_URL}/size/list.php?q=${encodeURIComponent(inputValue.trim())}&limit=10`,
        { headers: { 'Authorization': `Bearer ${token}` } }
      );
      const result = await response.json();
      if (result.status && result.data?.sizes) {
        return result.data.sizes.map((s) => ({
          value: s.name,
          label: `${s.name} (Order: ${s.sort_order || '0'})`,
          size: s
        }));
      }
      return [];
    } catch (err) {
      console.error('Error loading size search options:', err);
      return [];
    }
  };

  const handleSearchSelectChange = (selectedOption) => {
    setSelectedSearchOption(selectedOption);
    const query = selectedOption ? selectedOption.value : '';
    setSearchQuery(query);
    fetchSizes(1, query);
  };

  const hasActiveFilters = Boolean(searchQuery || selectedSearchOption || statusFilter);

  const handleClearFilters = () => {
    setSelectedSearchOption(null);
    setSearchQuery('');
    setStatusFilter('');
    fetchSizes(1, '', '');
  };

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    if (modalError) setModalError('');
  };

  const openModal = (mode, size = null) => {
    setModalMode(mode);
    setModalError('');
    if ((mode === 'edit' || mode === 'view') && size) {
      setFormData({
        id: size.id,
        name: size.name || '',
        sort_order: size.sort_order?.toString() || '0',
        status: size.status || 'active'
      });
    } else {
      setFormData({
        id: '',
        name: '',
        sort_order: (sizes.length + 1).toString(),
        status: 'active'
      });
    }
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setModalError('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (modalMode === 'view') {
      closeModal();
      return;
    }

    const trimmedName = formData.name.trim();
    if (!trimmedName) {
      setModalError('Size name is required.');
      return;
    }

    const sortOrder = parseInt(formData.sort_order, 10);
    if (isNaN(sortOrder) || sortOrder < 0) {
      setModalError('Sort order must be a non-negative number.');
      return;
    }

    setIsSaving(true);
    setModalError('');

    try {
      let response;
      if (modalMode === 'add') {
        response = await fetch(`${API_BASE_URL}/size/create.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            name: trimmedName,
            sort_order: sortOrder,
            status: formData.status
          })
        });
      } else {
        response = await fetch(`${API_BASE_URL}/size/update.php`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            id: formData.id,
            name: trimmedName,
            sort_order: sortOrder,
            status: formData.status
          })
        });
      }

      const result = await response.json();
      if (result.status) {
        closeModal();
        fetchSizes(currentPage);
      } else {
        setModalError(result.message || 'Operation failed.');
      }
    } catch (err) {
      console.error(err);
      setModalError('An error occurred while saving size details.');
    }
    setIsSaving(false);
  };

  const isReadOnly = modalMode === 'view';

  return (
    <div className="admin-module-container">
      {/* Page Header */}
      <div className="admin-page-header">
        <div>
          <div className="admin-breadcrumb">
            <span>Admin</span> &gt; <span>Catalogue</span> &gt; <span className="active">Size Master</span>
          </div>
          <h1 className="admin-module-title">Size Master</h1>
          <p className="admin-module-desc">Manage standard sizing (XS, S, M, L, XL, Free Size) and display sort sequence for variants.</p>
        </div>
        <div className="admin-header-actions">
          <button className="admin-btn admin-btn-primary" onClick={() => openModal('add')}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Size
          </button>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="admin-filter-bar" style={{ display: 'flex', gap: '12px', flexWrap: 'wrap', marginBottom: '20px', alignItems: 'center' }}>
        <div style={{ flex: '1', minWidth: '260px' }}>
          <AsyncSelect
            cacheOptions
            defaultOptions={false}
            loadOptions={loadSizeOptions}
            value={selectedSearchOption}
            onChange={handleSearchSelectChange}
            placeholder="Search sizes by name..."
            isClearable
            noOptionsMessage={({ inputValue }) =>
              !inputValue ? 'Type to search sizes...' : 'No matching sizes found'
            }
            styles={customSelectStyles}
          />
        </div>

        <div style={{ width: '160px' }}>
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

      {/* Data Table */}
      <div className="admin-premium-card" style={{ padding: '0', overflow: 'hidden' }}>
        {isLoading ? (
          <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading size master...</div>
        ) : (
          <div className="admin-table-container">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Sort #</th>
                  <th>Size Name</th>
                  <th>Status</th>
                  <th>Created Date</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {sizes.length === 0 ? (
                  <tr>
                    <td colSpan="5" style={{ padding: '40px', textAlign: 'center', color: '#9ca3af' }}>
                      No sizes found matching your search.
                    </td>
                  </tr>
                ) : (
                  sizes.map((size) => (
                    <tr key={size.id}>
                      <td style={{ width: '80px', color: '#6b7280', fontWeight: '500' }}>
                        #{size.sort_order}
                      </td>
                      <td style={{ fontWeight: '600', color: '#111827' }}>
                        <span style={{ display: 'inline-block', minWidth: '40px', padding: '3px 8px', background: '#F6EDF6', color: '#A049A3', borderRadius: '4px', textAlign: 'center', fontWeight: '700', fontSize: '13px' }}>
                          {size.name}
                        </span>
                      </td>
                      <td>
                        <span className={`admin-badge ${size.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>
                          {size.status}
                        </span>
                      </td>
                      <td style={{ fontSize: '13px', color: '#6b7280' }}>
                        {size.created_at ? new Date(size.created_at).toLocaleDateString() : '-'}
                      </td>
                      <td style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', alignItems: 'center', minHeight: '52px' }}>
                        <button
                          onClick={() => openModal('view', size)}
                          className="admin-action-btn admin-action-view"
                        >
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                          View
                        </button>
                        <button
                          onClick={() => openModal('edit', size)}
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
              Showing {(currentPage - 1) * 10 + 1} to {Math.min(currentPage * 10, totalRecords)} of {totalRecords} sizes
            </div>
            <AdminPagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={(p) => fetchSizes(p)}
            />
          </div>
        )}
      </div>

      {/* Modal Dialog */}
      {isModalOpen && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '460px' }}>
            <div className="admin-modal-header">
              <h3 className="admin-modal-title">
                {modalMode === 'add' && 'Add New Size'}
                {modalMode === 'edit' && 'Edit Size'}
                {modalMode === 'view' && 'Size Details'}
              </h3>
              <button onClick={closeModal} className="admin-modal-close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
            </div>

            {modalError && (
              <div style={{ padding: '10px 16px', margin: '0 24px 16px', backgroundColor: '#fef2f2', color: '#991b1b', borderRadius: '6px', fontSize: '13px', border: '1px solid #fecaca' }}>
                {modalError}
              </div>
            )}

            <form onSubmit={handleSubmit}>
              <div className="admin-modal-body">
                <div className="admin-form-group">
                  <label htmlFor="name">Size Code / Label *</label>
                  <input
                    type="text"
                    id="name"
                    name="name"
                    value={formData.name}
                    onChange={handleInputChange}
                    placeholder="e.g. S, M, L, XL, Free Size, 32, 34"
                    disabled={isReadOnly}
                    className="admin-input"
                    required
                  />
                </div>

                <div className="admin-form-group">
                  <label htmlFor="sort_order">Sort Order (Sequence)</label>
                  <input
                    type="number"
                    id="sort_order"
                    name="sort_order"
                    value={formData.sort_order}
                    onChange={handleInputChange}
                    min="0"
                    placeholder="0"
                    disabled={isReadOnly}
                    className="admin-input"
                  />
                  <small style={{ color: '#6b7280', fontSize: '12px', marginTop: '4px', display: 'block' }}>
                    Lower numbers display first in product size selectors.
                  </small>
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
                    isDisabled={isReadOnly}
                    isClearable={false}
                    menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                    styles={customSelectStyles}
                  />
                </div>
              </div>

              <div className="admin-modal-footer">
                <button type="button" onClick={closeModal} className="admin-btn admin-btn-secondary">
                  {isReadOnly ? 'Close' : 'Cancel'}
                </button>
                {!isReadOnly && (
                  <button type="submit" className="admin-btn admin-btn-primary" disabled={isSaving}>
                    {isSaving ? 'Saving...' : modalMode === 'add' ? 'Create Size' : 'Save Changes'}
                  </button>
                )}
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminSizes;
