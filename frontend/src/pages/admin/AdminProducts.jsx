import React, { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';

const AdminProducts = () => {
  const { token } = useAuth();
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState('add'); // 'add', 'edit', or 'view'
  const [formData, setFormData] = useState({
    id: '',
    category_id: '',
    name: '',
    slug: '',
    description: '',
    is_new_arrival: false,
    is_featured: false,
    is_best_seller: false,
    status: 'active'
  });
  const [isSaving, setIsSaving] = useState(false);

  // Fetch Categories for dropdown
  const fetchCategories = async () => {
    try {
      const response = await fetch('http://localhost/vivisha_boutique/backend/api/category/list.php', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      if (result.status) {
        setCategories(result.data.categories);
      }
    } catch (err) {
      console.error('Failed to fetch categories', err);
    }
  };

  // Fetch Products
  const fetchProducts = async (page = 1) => {
    setIsLoading(true);
    setError('');
    try {
      const response = await fetch(`http://localhost/vivisha_boutique/backend/api/product/list.php?page=${page}&limit=10`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      if (result.status) {
        setProducts(result.data.products);
        if (result.data.pagination) {
          setCurrentPage(result.data.pagination.page);
          setTotalPages(result.data.pagination.total_pages);
          setTotalRecords(result.data.pagination.total_records);
        }
      } else {
        setError(result.message || 'Failed to fetch products');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while fetching products.');
    }
    setIsLoading(false);
  };

  useEffect(() => {
    fetchCategories();
    fetchProducts(1);
  }, [token]);

  // Handle Input Changes
  const handleInputChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({ 
      ...prev, 
      [name]: type === 'checkbox' ? checked : value 
    }));
  };

  // Open Modal
  const openModal = (mode, product = null) => {
    setModalMode(mode);
    if ((mode === 'edit' || mode === 'view') && product) {
      setFormData({
        id: product.id,
        category_id: product.category_id || '',
        name: product.name || '',
        slug: product.slug || '',
        description: product.description || '',
        is_new_arrival: !!product.is_new_arrival,
        is_featured: !!product.is_featured,
        is_best_seller: !!product.is_best_seller,
        status: product.status || 'active'
      });
    } else {
      setFormData({
        id: '',
        category_id: categories.length > 0 ? categories[0].id : '',
        name: '',
        slug: '',
        description: '',
        is_new_arrival: false,
        is_featured: false,
        is_best_seller: false,
        status: 'active'
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
      ? 'http://localhost/vivisha_boutique/backend/api/product/create.php'
      : 'http://localhost/vivisha_boutique/backend/api/product/update.php';

    const method = modalMode === 'add' ? 'POST' : 'PUT';

    const payload = {
      category_id: parseInt(formData.category_id, 10),
      name: formData.name,
      description: formData.description,
      is_new_arrival: formData.is_new_arrival,
      is_featured: formData.is_featured,
      is_best_seller: formData.is_best_seller,
      status: formData.status
    };

    if (modalMode === 'edit') {
      payload.id = formData.id;
    }
    if (formData.slug) {
      payload.slug = formData.slug;
    }

    try {
      const response = await fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      
      if (result.status) {
        closeModal();
        fetchProducts(currentPage); 
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
            <span>Admin</span> &gt; <span>Catalogue</span> &gt; <span className="active">Products Catalogue</span>
          </div>
          <h1 className="admin-module-title">Products Catalogue</h1>
          <p className="admin-module-desc">Manage products, assignments, and base information.</p>
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

      {error && !isModalOpen && (
        <div className="admin-alert admin-alert-error" style={{ marginBottom: '20px', padding: '15px', backgroundColor: '#fdf2f8', color: '#9d174d', border: '1px solid #fbcfe8', borderRadius: '8px' }}>
          {error}
        </div>
      )}

      <div className="admin-premium-card" style={{ padding: '0', overflow: 'hidden' }}>
        {isLoading ? (
          <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading products...</div>
        ) : (
          <div className="admin-table-container">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Category</th>
                  <th>Tags</th>
                  <th>Status</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {products.length === 0 ? (
                  <tr>
                    <td colSpan="5" style={{ padding: '40px', textAlign: 'center', color: '#9ca3af' }}>
                      No products found.
                    </td>
                  </tr>
                ) : (
                  products.map(prod => (
                    <tr key={prod.id}>
                      <td style={{ fontWeight: '600', color: '#111827' }}>{prod.name}</td>
                      <td>{prod.category?.name || 'N/A'}</td>
                      <td>
                        <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                          {prod.is_new_arrival === 1 && <span className="admin-badge admin-badge-new">NEW</span>}
                          {prod.is_featured === 1 && <span className="admin-badge admin-badge-featured">FEATURED</span>}
                          {prod.is_best_seller === 1 && <span className="admin-badge admin-badge-bestseller">BEST SELLER</span>}
                        </div>
                      </td>
                      <td>
                        <span className={`admin-badge ${prod.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>
                          {prod.status}
                        </span>
                      </td>
                      <td style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', alignItems: 'center', height: '100%', minHeight: '52px' }}>
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
              onPageChange={(page) => fetchProducts(page)}
            />
          </div>
        )}
      </div>

      {/* Modal */}
      {isModalOpen && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '600px' }}>
            <div className="admin-modal-header">
              <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#111827', fontWeight: '700' }}>
                {modalMode === 'add' ? 'Add New Product' : modalMode === 'edit' ? 'Edit Product' : 'Product Details'}
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
                <div className="admin-info-grid">
                  <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                    <span className="admin-info-label">Product Name</span>
                    <span className="admin-info-value">{formData.name}</span>
                  </div>
                  
                  <div className="admin-info-box">
                    <span className="admin-info-label">Category</span>
                    <span style={{ fontSize: '0.9rem', color: '#374151', fontWeight: '500' }}>
                      {categories.find(c => c.id === parseInt(formData.category_id))?.name || 'N/A'}
                    </span>
                  </div>
                  <div className="admin-info-box">
                    <span className="admin-info-label">Status</span>
                    <span className={`admin-badge ${formData.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>
                      {formData.status}
                    </span>
                  </div>

                  <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                    <span className="admin-info-label">Slug / URL</span>
                    <span style={{ fontSize: '0.9rem', color: '#4b5563', fontFamily: 'monospace', backgroundColor: '#e5e7eb', padding: '2px 6px', borderRadius: '4px' }}>{formData.slug || 'N/A'}</span>
                  </div>

                  <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                    <span className="admin-info-label">Description</span>
                    <span style={{ fontSize: '0.9rem', color: '#4b5563', lineHeight: '1.5' }}>{formData.description || <em style={{color: '#9ca3af'}}>No description provided.</em>}</span>
                  </div>

                  <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                    <span className="admin-info-label">Product Tags</span>
                    <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                      <span className={`admin-badge ${formData.is_new_arrival ? 'admin-badge-new' : 'admin-badge-inactive'}`}>New Arrival</span>
                      <span className={`admin-badge ${formData.is_featured ? 'admin-badge-featured' : 'admin-badge-inactive'}`}>Featured</span>
                      <span className={`admin-badge ${formData.is_best_seller ? 'admin-badge-bestseller' : 'admin-badge-inactive'}`}>Best Seller</span>
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr', gap: '16px' }}>
                  
                  <div>
                    <label className="admin-label">Product Name <span style={{color: '#ef4444'}}>*</span></label>
                    <input 
                      type="text" 
                      name="name" 
                      value={formData.name} 
                      onChange={handleInputChange} 
                      required
                      placeholder="E.g., Red Banarasi Saree"
                      className="admin-input"
                    />
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                    <div>
                      <label className="admin-label">Category <span style={{color: '#ef4444'}}>*</span></label>
                      <select 
                        name="category_id" 
                        value={formData.category_id} 
                        onChange={handleInputChange}
                        required
                        className="admin-input"
                        style={{ appearance: 'none', backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' fill=\'none\' stroke=\'%236b7280\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpolyline points=\'3 5 8 10 13 5\'/%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 14px center' }}
                      >
                        <option value="" disabled>Select Category</option>
                        {categories.map(cat => (
                          <option key={cat.id} value={cat.id}>{cat.name}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="admin-label">Status</label>
                      <select 
                        name="status" 
                        value={formData.status} 
                        onChange={handleInputChange}
                        className="admin-input"
                        style={{ appearance: 'none', backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' fill=\'none\' stroke=\'%236b7280\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpolyline points=\'3 5 8 10 13 5\'/%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 14px center' }}
                      >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                      </select>
                    </div>
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
                      placeholder="Brief description about this product..."
                      className="admin-input"
                    />
                  </div>

                  <div>
                    <label className="admin-label">Product Tags / Badges</label>
                    <div style={{ display: 'flex', gap: '20px', flexWrap: 'wrap', backgroundColor: '#f9fafb', padding: '16px', borderRadius: '8px', border: '1px solid #e5e7eb' }}>
                      <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.875rem', fontWeight: '500', color: '#4b5563', cursor: 'pointer' }}>
                        <input type="checkbox" name="is_new_arrival" checked={formData.is_new_arrival} onChange={handleInputChange} style={{ width: '16px', height: '16px', cursor: 'pointer' }} />
                        New Arrival
                      </label>
                      <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.875rem', fontWeight: '500', color: '#4b5563', cursor: 'pointer' }}>
                        <input type="checkbox" name="is_featured" checked={formData.is_featured} onChange={handleInputChange} style={{ width: '16px', height: '16px', cursor: 'pointer' }} />
                        Featured
                      </label>
                      <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.875rem', fontWeight: '500', color: '#4b5563', cursor: 'pointer' }}>
                        <input type="checkbox" name="is_best_seller" checked={formData.is_best_seller} onChange={handleInputChange} style={{ width: '16px', height: '16px', cursor: 'pointer' }} />
                        Best Seller
                      </label>
                    </div>
                  </div>

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
                    'Save Product'
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

export default AdminProducts;
