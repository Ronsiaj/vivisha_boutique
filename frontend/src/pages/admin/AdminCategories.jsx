import React, { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext.jsx';

const AdminCategories = () => {
  const { token } = useAuth();
  const [categories, setCategories] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

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
  const fetchCategories = async () => {
    setIsLoading(true);
    setError('');
    try {
      const response = await fetch('http://localhost/vivisha_boutique/backend/api/category/list.php', {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const result = await response.json();
      if (result.status) {
        setCategories(result.data.categories);
      } else {
        setError(result.message || 'Failed to fetch categories');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while fetching categories.');
    }
    setIsLoading(false);
  };

  useEffect(() => {
    fetchCategories();
  }, [token]);

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
      ? 'http://localhost/vivisha_boutique/backend/api/category/create.php'
      : 'http://localhost/vivisha_boutique/backend/api/category/update.php';

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
        fetchCategories(); 
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
          <button className="admin-btn admin-btn-primary" onClick={() => openModal('add')} style={{ backgroundColor: '#A049A3' }}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Category
          </button>
        </div>
      </div>

      {error && !isModalOpen && (
        <div className="admin-alert admin-alert-error" style={{ marginBottom: '20px', padding: '15px', backgroundColor: '#fdf2f8', color: '#9d174d', border: '1px solid #fbcfe8', borderRadius: '8px' }}>
          {error}
        </div>
      )}

      <div className="admin-card" style={{ padding: '0', overflow: 'hidden', border: '1px solid #f3f4f6', boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.05)' }}>
        {isLoading ? (
          <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading categories...</div>
        ) : (
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
              <thead>
                <tr style={{ backgroundColor: '#f9fafb', borderBottom: '1px solid #e5e7eb' }}>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem' }}>Image</th>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem' }}>Name</th>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem' }}>Slug</th>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem' }}>Sort Order</th>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem' }}>Status</th>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem', textAlign: 'right' }}>Actions</th>
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
                    <tr key={cat.id} style={{ borderBottom: '1px solid #e5e7eb', transition: 'background-color 0.2s' }} onMouseOver={(e) => e.currentTarget.style.backgroundColor = '#fdfafc'} onMouseOut={(e) => e.currentTarget.style.backgroundColor = 'transparent'}>
                      <td style={{ padding: '12px 16px' }}>
                        {cat.image ? (
                          <img 
                            src={`http://localhost/vivisha_boutique/backend/${cat.image}`} 
                            alt={cat.name} 
                            style={{ width: '48px', height: '48px', objectFit: 'cover', borderRadius: '6px', border: '1px solid #e5e7eb' }}
                          />
                        ) : (
                          <div style={{ width: '48px', height: '48px', backgroundColor: '#f3f4f6', borderRadius: '6px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10px', color: '#9ca3af', border: '1px solid #e5e7eb' }}>No Img</div>
                        )}
                      </td>
                      <td style={{ padding: '12px 16px', fontWeight: '500', color: '#1f2937' }}>{cat.name}</td>
                      <td style={{ padding: '12px 16px', color: '#6b7280', fontSize: '0.875rem' }}>{cat.slug}</td>
                      <td style={{ padding: '12px 16px', color: '#6b7280', fontSize: '0.875rem' }}>{cat.sort_order}</td>
                      <td style={{ padding: '12px 16px' }}>
                        <span style={{ 
                          padding: '4px 10px', 
                          borderRadius: '9999px', 
                          fontSize: '0.75rem',
                          fontWeight: '600',
                          backgroundColor: cat.status === 'active' ? '#dcfce7' : '#f3f4f6',
                          color: cat.status === 'active' ? '#166534' : '#4b5563',
                          border: cat.status === 'active' ? '1px solid #bbf7d0' : '1px solid #e5e7eb'
                        }}>
                          {cat.status.charAt(0).toUpperCase() + cat.status.slice(1)}
                        </span>
                      </td>
                      <td style={{ padding: '12px 16px', display: 'flex', justifyContent: 'flex-end', gap: '8px', alignItems: 'center', height: '100%', minHeight: '52px' }}>
                        <button 
                          onClick={() => openModal('view', cat)}
                          style={{ display: 'flex', alignItems: 'center', gap: '6px', background: '#f3f4f6', border: '1px solid #e5e7eb', color: '#4b5563', padding: '6px 12px', borderRadius: '6px', fontSize: '0.75rem', fontWeight: '600', cursor: 'pointer', transition: 'all 0.2s' }}
                          onMouseOver={(e) => { e.currentTarget.style.borderColor = '#d1d5db'; e.currentTarget.style.backgroundColor = '#e5e7eb'; }}
                          onMouseOut={(e) => { e.currentTarget.style.borderColor = '#e5e7eb'; e.currentTarget.style.backgroundColor = '#f3f4f6'; }}
                        >
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                          View
                        </button>
                        <button 
                          onClick={() => openModal('edit', cat)}
                          style={{ display: 'flex', alignItems: 'center', gap: '6px', background: '#fdf2f8', border: '1px solid #fbcfe8', color: '#A049A3', padding: '6px 12px', borderRadius: '6px', fontSize: '0.75rem', fontWeight: '600', cursor: 'pointer', transition: 'all 0.2s' }}
                          onMouseOver={(e) => { e.currentTarget.style.borderColor = '#f9a8d4'; e.currentTarget.style.backgroundColor = '#fce7f3'; }}
                          onMouseOut={(e) => { e.currentTarget.style.borderColor = '#fbcfe8'; e.currentTarget.style.backgroundColor = '#fdf2f8'; }}
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
      </div>

      {isModalOpen && (
        <div style={{
          position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, 
          backgroundColor: 'rgba(0,0,0,0.6)', zIndex: 1000, 
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          padding: '16px',
          boxSizing: 'border-box'
        }}>
          <div className="admin-card" style={{ 
            width: '100%', maxWidth: '550px', maxHeight: '90vh', overflowY: 'auto', 
            padding: '24px', position: 'relative', borderRadius: '16px',
            boxSizing: 'border-box', backgroundColor: '#ffffff',
            boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)'
          }}>
            
            <button 
              onClick={closeModal}
              style={{ position: 'absolute', top: '20px', right: '20px', background: '#f3f4f6', border: 'none', width: '32px', height: '32px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '20px', cursor: 'pointer', color: '#6b7280', transition: 'background 0.2s' }}
              onMouseOver={(e) => e.currentTarget.style.backgroundColor = '#e5e7eb'}
              onMouseOut={(e) => e.currentTarget.style.backgroundColor = '#f3f4f6'}
            >
              &times;
            </button>
            
            <h2 style={{ marginBottom: '24px', fontSize: '1.25rem', color: '#111827', fontWeight: '700', paddingRight: '40px' }}>
              {modalMode === 'add' ? 'Add New Category' : modalMode === 'edit' ? 'Edit Category' : 'Category Details'}
            </h2>

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
                      src={`http://localhost/vivisha_boutique/backend/${formData.imageUrl}`} 
                      alt={formData.name} 
                      style={{ width: '120px', height: '120px', objectFit: 'cover', borderRadius: '50%', border: '4px solid #fdf2f8', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1)' }}
                    />
                  ) : (
                    <div style={{ width: '120px', height: '120px', backgroundColor: '#f3f4f6', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9ca3af', border: '4px solid #fdf2f8', fontSize: '0.875rem', fontWeight: '500' }}>No Image</div>
                  )}
                </div>
                
                <div style={{ display: 'grid', gridTemplateColumns: '1fr', gap: '12px' }}>
                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Category Name</span>
                    <span style={{ fontSize: '1rem', fontWeight: '600', color: '#111827' }}>{formData.name}</span>
                  </div>
                  
                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Slug / URL</span>
                    <span style={{ fontSize: '0.9rem', color: '#4b5563', fontFamily: 'monospace', backgroundColor: '#e5e7eb', padding: '2px 6px', borderRadius: '4px' }}>{formData.slug || 'N/A'}</span>
                  </div>

                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Description</span>
                    <span style={{ fontSize: '0.9rem', color: '#4b5563', lineHeight: '1.5' }}>{formData.description || <em style={{color: '#9ca3af'}}>No description provided.</em>}</span>
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                    <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                      <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Sort Order</span>
                      <span style={{ fontSize: '1rem', fontWeight: '600', color: '#374151' }}>{formData.sort_order}</span>
                    </div>
                    <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                      <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Status</span>
                      <span style={{ 
                            display: 'inline-block',
                            padding: '4px 12px', 
                            borderRadius: '9999px', 
                            fontSize: '0.75rem',
                            fontWeight: '600',
                            backgroundColor: formData.status === 'active' ? '#dcfce7' : '#f3f4f6',
                            color: formData.status === 'active' ? '#166534' : '#4b5563',
                            border: formData.status === 'active' ? '1px solid #bbf7d0' : '1px solid #e5e7eb'
                          }}>
                            {formData.status.charAt(0).toUpperCase() + formData.status.slice(1)}
                      </span>
                    </div>
                  </div>
                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '10px' }}>
                  <button 
                    type="button" 
                    onClick={closeModal}
                    className="admin-btn admin-btn-secondary"
                    style={{ padding: '10px 24px', borderRadius: '8px', fontWeight: '600', backgroundColor: '#f3f4f6', color: '#374151', border: 'none' }}
                  >
                    Close Profile
                  </button>
                </div>
              </div>
            ) : (
              <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                <div>
                  <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Category Name <span style={{color: '#ef4444'}}>*</span></label>
                  <input 
                    type="text" 
                    name="name" 
                    value={formData.name} 
                    onChange={handleInputChange} 
                    required
                    placeholder="E.g., Designer Sarees"
                    style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none', transition: 'border-color 0.2s' }}
                    onFocus={(e) => e.target.style.borderColor = '#A049A3'}
                    onBlur={(e) => e.target.style.borderColor = '#d1d5db'}
                  />
                </div>

                <div>
                  <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Slug URL <span style={{fontWeight: '400', color: '#9ca3af'}}>(Optional)</span></label>
                  <input 
                    type="text" 
                    name="slug" 
                    value={formData.slug} 
                    onChange={handleInputChange} 
                    placeholder="Auto-generated if left blank"
                    style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none', transition: 'border-color 0.2s' }}
                    onFocus={(e) => e.target.style.borderColor = '#A049A3'}
                    onBlur={(e) => e.target.style.borderColor = '#d1d5db'}
                  />
                </div>

                <div>
                  <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Description</label>
                  <textarea 
                    name="description" 
                    value={formData.description} 
                    onChange={handleInputChange} 
                    rows="3"
                    placeholder="Brief description about this category..."
                    style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none', resize: 'vertical', transition: 'border-color 0.2s' }}
                    onFocus={(e) => e.target.style.borderColor = '#A049A3'}
                    onBlur={(e) => e.target.style.borderColor = '#d1d5db'}
                  />
                </div>

                <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
                  <div style={{ flex: '1 1 calc(50% - 8px)' }}>
                    <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Sort Order</label>
                    <input 
                      type="number" 
                      name="sort_order" 
                      value={formData.sort_order} 
                      onChange={handleInputChange} 
                      min="0"
                      style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none', transition: 'border-color 0.2s' }}
                      onFocus={(e) => e.target.style.borderColor = '#A049A3'}
                      onBlur={(e) => e.target.style.borderColor = '#d1d5db'}
                    />
                  </div>
                  <div style={{ flex: '1 1 calc(50% - 8px)' }}>
                    <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Status</label>
                    <select 
                      name="status" 
                      value={formData.status} 
                      onChange={handleInputChange}
                      style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none', backgroundColor: '#fff', transition: 'border-color 0.2s', appearance: 'none', backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' fill=\'none\' stroke=\'%236b7280\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpolyline points=\'3 5 8 10 13 5\'/%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 14px center' }}
                      onFocus={(e) => e.target.style.borderColor = '#A049A3'}
                      onBlur={(e) => e.target.style.borderColor = '#d1d5db'}
                    >
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Category Image</label>
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

                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px', marginTop: '16px' }}>
                  <button 
                    type="button" 
                    onClick={closeModal}
                    style={{ padding: '10px 20px', borderRadius: '8px', fontWeight: '600', border: '1px solid #d1d5db', backgroundColor: '#fff', color: '#374151', cursor: 'pointer', transition: 'background 0.2s' }}
                    onMouseOver={(e) => e.target.style.backgroundColor = '#f9fafb'}
                    onMouseOut={(e) => e.target.style.backgroundColor = '#fff'}
                  >
                    Cancel
                  </button>
                  <button 
                    type="submit" 
                    disabled={isSaving}
                    style={{ padding: '10px 24px', borderRadius: '8px', fontWeight: '600', backgroundColor: '#A049A3', border: 'none', color: '#fff', cursor: isSaving ? 'not-allowed' : 'pointer', transition: 'background 0.2s', opacity: isSaving ? 0.7 : 1, display: 'flex', alignItems: 'center', gap: '8px' }}
                    onMouseOver={(e) => { if(!isSaving) e.currentTarget.style.backgroundColor = '#823b84'; }}
                    onMouseOut={(e) => { if(!isSaving) e.currentTarget.style.backgroundColor = '#A049A3'; }}
                  >
                    {isSaving ? (
                      <>
                        <svg className="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ animation: 'spin 1s linear infinite' }}><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                        Saving...
                      </>
                    ) : (
                      'Save Category'
                    )}
                    <style>{`@keyframes spin { 100% { transform: rotate(360deg); } }`}</style>
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

export default AdminCategories;
