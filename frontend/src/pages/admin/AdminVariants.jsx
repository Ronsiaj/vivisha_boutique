import React, { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext.jsx';

const AdminVariants = () => {
  const { token } = useAuth();
  const [variants, setVariants] = useState([]);
  const [products, setProducts] = useState([]);
  const [sizes, setSizes] = useState([]);
  const [colours, setColours] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState('add');
  const [formData, setFormData] = useState({
    id: '',
    product_id: '',
    size_id: '',
    color_id: '',
    sku: '',
    variant_name: '',
    original_price: '0.00',
    discount_type: 'none',
    discount_value: '0',
    stock_quantity: '0',
    reserved_quantity: '0',
    low_stock_limit: '5',
    is_available: true,
    images: [] // Handle files
  });
  const [isSaving, setIsSaving] = useState(false);

  // Fetch Dependencies
  const fetchDependencies = async () => {
    try {
      const [prodRes, sizeRes, colRes] = await Promise.all([
        fetch('http://localhost/vivisha_boutique/backend/api/product/list.php', { headers: { 'Authorization': `Bearer ${token}` } }),
        fetch('http://localhost/vivisha_boutique/backend/api/size/list.php', { headers: { 'Authorization': `Bearer ${token}` } }),
        fetch('http://localhost/vivisha_boutique/backend/api/colour/list.php', { headers: { 'Authorization': `Bearer ${token}` } })
      ]);
      const prods = await prodRes.json();
      const szs = await sizeRes.json();
      const cols = await colRes.json();
      
      if (prods.status) setProducts(prods.data.products);
      if (szs.status) setSizes(szs.data.sizes);
      if (cols.status) setColours(cols.data.colors);
    } catch (err) {
      console.error('Failed to fetch dependencies', err);
    }
  };

  // Fetch Variants
  const fetchVariants = async () => {
    setIsLoading(true);
    setError('');
    try {
      const response = await fetch('http://localhost/vivisha_boutique/backend/api/varient/list.php', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      if (result.status) {
        setVariants(result.data.variants);
      } else {
        setError(result.message || 'Failed to fetch variants');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while fetching variants.');
    }
    setIsLoading(false);
  };

  useEffect(() => {
    fetchDependencies();
    fetchVariants();
  }, [token]);

  // Handle Input Changes
  const handleInputChange = (e) => {
    const { name, value, type, checked, files } = e.target;
    if (type === 'file') {
      setFormData(prev => ({ ...prev, [name]: Array.from(files) }));
    } else {
      setFormData(prev => ({ 
        ...prev, 
        [name]: type === 'checkbox' ? checked : value 
      }));
    }
  };

  // Open Modal
  const openModal = (mode, variant = null) => {
    setModalMode(mode);
    if ((mode === 'edit' || mode === 'view') && variant) {
      setFormData({
        id: variant.id,
        product_id: variant.product_id || '',
        size_id: variant.size?.id || '',
        color_id: variant.color?.id || '',
        sku: variant.sku || '',
        variant_name: variant.variant_name || '',
        original_price: variant.original_price || '0.00',
        discount_type: variant.discount_type || 'none',
        discount_value: variant.discount_value || '0',
        stock_quantity: variant.stock_quantity || '0',
        reserved_quantity: variant.reserved_quantity || '0',
        low_stock_limit: variant.low_stock_limit || '5',
        is_available: !!variant.is_available,
        images: [] 
      });
    } else {
      setFormData({
        id: '',
        product_id: products.length > 0 ? products[0].id : '',
        size_id: '',
        color_id: '',
        sku: '',
        variant_name: '',
        original_price: '0.00',
        discount_type: 'none',
        discount_value: '0',
        stock_quantity: '0',
        reserved_quantity: '0',
        low_stock_limit: '5',
        is_available: true,
        images: []
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
      ? 'http://localhost/vivisha_boutique/backend/api/varient/create.php'
      : 'http://localhost/vivisha_boutique/backend/api/varient/update.php';

    const payload = new FormData();
    if (modalMode === 'edit') payload.append('id', formData.id);
    
    payload.append('product_id', formData.product_id);
    if (formData.size_id) payload.append('size_id', formData.size_id);
    if (formData.color_id) payload.append('color_id', formData.color_id);
    payload.append('sku', formData.sku);
    if (formData.variant_name) payload.append('variant_name', formData.variant_name);
    payload.append('original_price', formData.original_price);
    payload.append('discount_type', formData.discount_type);
    payload.append('discount_value', formData.discount_value);
    payload.append('stock_quantity', formData.stock_quantity);
    payload.append('reserved_quantity', formData.reserved_quantity);
    payload.append('low_stock_limit', formData.low_stock_limit);
    payload.append('is_available', formData.is_available ? '1' : '0');

    // Append Images for Create (Update might need different logic based on API, but following multipart standard)
    if (formData.images && formData.images.length > 0) {
      formData.images.forEach((file, index) => {
        payload.append('images[]', file);
        // Add default values for required array fields matching the images
        payload.append('alt_text[]', `${formData.sku}_img_${index}`);
        payload.append('is_primary[]', index === 0 ? '1' : '0');
        payload.append('sort_order[]', index.toString());
        payload.append('image_status[]', 'active');
      });
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
        fetchVariants(); 
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
            <span>Admin</span> &gt; <span>Catalogue</span> &gt; <span className="active">Product Variants</span>
          </div>
          <h1 className="admin-module-title">Product Variants</h1>
          <p className="admin-module-desc">Manage sizes, colours, stock, and pricing per variant.</p>
        </div>
        <div className="admin-header-actions">
          <button className="admin-btn admin-btn-primary" onClick={() => openModal('add')} style={{ backgroundColor: '#A049A3' }}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Variant
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
          <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading variants...</div>
        ) : (
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
              <thead>
                <tr style={{ backgroundColor: '#f9fafb', borderBottom: '1px solid #e5e7eb' }}>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem' }}>SKU</th>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem' }}>Product</th>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem' }}>Size / Color</th>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem' }}>Price</th>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem' }}>Stock</th>
                  <th style={{ padding: '16px', fontWeight: '600', color: '#4b5563', fontSize: '0.875rem', textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {variants.length === 0 ? (
                  <tr>
                    <td colSpan="6" style={{ padding: '40px', textAlign: 'center', color: '#9ca3af' }}>
                      No variants found.
                    </td>
                  </tr>
                ) : (
                  variants.map(vari => (
                    <tr key={vari.id} style={{ borderBottom: '1px solid #e5e7eb', transition: 'background-color 0.2s' }} onMouseOver={(e) => e.currentTarget.style.backgroundColor = '#fdfafc'} onMouseOut={(e) => e.currentTarget.style.backgroundColor = 'transparent'}>
                      <td style={{ padding: '12px 16px', fontWeight: '600', color: '#1f2937', fontFamily: 'monospace' }}>{vari.sku}</td>
                      <td style={{ padding: '12px 16px', color: '#4b5563', fontSize: '0.875rem' }}>{vari.product_name}</td>
                      <td style={{ padding: '12px 16px', color: '#6b7280', fontSize: '0.875rem' }}>
                        {vari.size?.name ? <span style={{ marginRight: '8px', padding: '2px 6px', background: '#f3f4f6', borderRadius: '4px', fontWeight: '500' }}>{vari.size.name}</span> : null}
                        {vari.color?.name ? (
                           <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
                             <span style={{ display: 'inline-block', width: '12px', height: '12px', borderRadius: '50%', backgroundColor: vari.color.hex_code, border: '1px solid #e5e7eb' }}></span>
                             {vari.color.name}
                           </span>
                        ) : null}
                      </td>
                      <td style={{ padding: '12px 16px', fontWeight: '500', color: '#059669' }}>
                        ${vari.selling_price}
                      </td>
                      <td style={{ padding: '12px 16px' }}>
                        <span style={{ 
                          padding: '4px 10px', 
                          borderRadius: '9999px', 
                          fontSize: '0.75rem',
                          fontWeight: '600',
                          backgroundColor: parseInt(vari.stock_quantity) > parseInt(vari.low_stock_limit) ? '#dcfce7' : '#fee2e2',
                          color: parseInt(vari.stock_quantity) > parseInt(vari.low_stock_limit) ? '#166534' : '#991b1b',
                          border: parseInt(vari.stock_quantity) > parseInt(vari.low_stock_limit) ? '1px solid #bbf7d0' : '1px solid #fecaca'
                        }}>
                          {vari.stock_quantity} in stock
                        </span>
                      </td>
                      <td style={{ padding: '12px 16px', display: 'flex', justifyContent: 'flex-end', gap: '8px', alignItems: 'center', height: '100%', minHeight: '52px' }}>
                        <button 
                          onClick={() => openModal('view', vari)}
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
                          onClick={() => openModal('edit', vari)}
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

      {/* Modal */}
      {isModalOpen && (
        <div style={{
          position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, 
          backgroundColor: 'rgba(0,0,0,0.6)', zIndex: 1000, 
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          padding: '16px',
          boxSizing: 'border-box'
        }}>
          <div className="admin-card" style={{ 
            width: '100%', maxWidth: '750px', maxHeight: '90vh', overflowY: 'auto', 
            padding: '24px', position: 'relative', borderRadius: '16px',
            boxSizing: 'border-box', backgroundColor: '#ffffff',
            boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)'
          }}>
            
            <button 
              onClick={closeModal}
              style={{ position: 'absolute', top: '20px', right: '20px', background: '#f3f4f6', border: 'none', width: '32px', height: '32px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '20px', cursor: 'pointer', color: '#6b7280', transition: 'background 0.2s' }}
            >
              &times;
            </button>
            
            <h2 style={{ marginBottom: '24px', fontSize: '1.25rem', color: '#111827', fontWeight: '700', paddingRight: '40px' }}>
              {modalMode === 'add' ? 'Add New Variant' : modalMode === 'edit' ? 'Edit Variant' : 'Variant Details'}
            </h2>

            {error && (
              <div style={{ marginBottom: '20px', padding: '12px 16px', backgroundColor: '#fdf2f8', color: '#9d174d', borderRadius: '8px', fontSize: '0.875rem', border: '1px solid #fbcfe8', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                {error}
              </div>
            )}

            {isReadOnly ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>SKU</span>
                    <span style={{ fontSize: '1rem', fontWeight: '600', color: '#111827', fontFamily: 'monospace' }}>{formData.sku}</span>
                  </div>
                  
                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Product</span>
                    <span style={{ fontSize: '0.95rem', color: '#374151', fontWeight: '500' }}>
                      {products.find(p => p.id === parseInt(formData.product_id))?.name || 'N/A'}
                    </span>
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Size</span>
                    <span style={{ fontSize: '0.95rem', color: '#374151' }}>
                      {sizes.find(s => s.id === parseInt(formData.size_id))?.name || 'None'}
                    </span>
                  </div>
                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Colour</span>
                    <span style={{ fontSize: '0.95rem', color: '#374151', display: 'flex', alignItems: 'center', gap: '6px' }}>
                      {(() => {
                        const col = colours.find(c => c.id === parseInt(formData.color_id));
                        return col ? (
                          <><span style={{ width: '12px', height: '12px', borderRadius: '50%', backgroundColor: col.hex_code, border: '1px solid #e5e7eb' }}></span> {col.name}</>
                        ) : 'None';
                      })()}
                    </span>
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))', gap: '12px' }}>
                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Orig Price</span>
                    <span style={{ fontSize: '1rem', fontWeight: '600', color: '#374151' }}>${formData.original_price}</span>
                  </div>
                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Discount</span>
                    <span style={{ fontSize: '0.95rem', color: '#4b5563' }}>
                      {formData.discount_type === 'none' ? 'None' : `${formData.discount_value}${formData.discount_type === 'percentage' ? '%' : '$'}`}
                    </span>
                  </div>
                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Stock Qty</span>
                    <span style={{ fontSize: '1rem', fontWeight: '600', color: parseInt(formData.stock_quantity) > parseInt(formData.low_stock_limit) ? '#059669' : '#dc2626' }}>
                      {formData.stock_quantity}
                    </span>
                  </div>
                  <div style={{ padding: '12px 16px', backgroundColor: '#f9fafb', borderRadius: '10px', border: '1px solid #f3f4f6' }}>
                    <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: '700', color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '4px' }}>Availability</span>
                    <span style={{ fontSize: '0.95rem', color: formData.is_available ? '#059669' : '#4b5563', fontWeight: '600' }}>
                      {formData.is_available ? 'Available' : 'Unavailable'}
                    </span>
                  </div>
                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '10px' }}>
                  <button type="button" onClick={closeModal} className="admin-btn admin-btn-secondary" style={{ padding: '10px 24px', borderRadius: '8px', fontWeight: '600', backgroundColor: '#f3f4f6', color: '#374151', border: 'none' }}>Close Profile</button>
                </div>
              </div>
            ) : (
              <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                  
                  <div style={{ gridColumn: '1 / -1' }}>
                    <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Select Product <span style={{color: '#ef4444'}}>*</span></label>
                    <select 
                      name="product_id" 
                      value={formData.product_id} 
                      onChange={handleInputChange}
                      required
                      style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none' }}
                    >
                      <option value="" disabled>Choose a product</option>
                      {products.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </select>
                  </div>

                  <div>
                    <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>SKU <span style={{color: '#ef4444'}}>*</span></label>
                    <input 
                      type="text" name="sku" value={formData.sku} onChange={handleInputChange} required
                      placeholder="e.g. SAREE-RED-M"
                      style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none' }}
                    />
                  </div>

                  <div>
                    <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Variant Name</label>
                    <input 
                      type="text" name="variant_name" value={formData.variant_name} onChange={handleInputChange} 
                      placeholder="Optional display name"
                      style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none' }}
                    />
                  </div>

                  <div>
                    <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Size</label>
                    <select 
                      name="size_id" value={formData.size_id} onChange={handleInputChange}
                      style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none' }}
                    >
                      <option value="">No specific size</option>
                      {sizes.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                  </div>

                  <div>
                    <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Colour</label>
                    <select 
                      name="color_id" value={formData.color_id} onChange={handleInputChange}
                      style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none' }}
                    >
                      <option value="">No specific colour</option>
                      {colours.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                  </div>

                  <div>
                    <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Original Price ($) <span style={{color: '#ef4444'}}>*</span></label>
                    <input 
                      type="number" step="0.01" name="original_price" value={formData.original_price} onChange={handleInputChange} required
                      style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none' }}
                    />
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                    <div>
                      <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Discount</label>
                      <select 
                        name="discount_type" value={formData.discount_type} onChange={handleInputChange}
                        style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none' }}
                      >
                        <option value="none">None</option>
                        <option value="percentage">%</option>
                        <option value="flat">Flat ($)</option>
                      </select>
                    </div>
                    <div>
                      <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Value</label>
                      <input 
                        type="number" step="0.01" name="discount_value" value={formData.discount_value} onChange={handleInputChange} disabled={formData.discount_type === 'none'}
                        style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none', backgroundColor: formData.discount_type === 'none' ? '#f3f4f6' : '#fff' }}
                      />
                    </div>
                  </div>

                  <div>
                    <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Stock Quantity <span style={{color: '#ef4444'}}>*</span></label>
                    <input 
                      type="number" name="stock_quantity" value={formData.stock_quantity} onChange={handleInputChange} required min="0"
                      style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none' }}
                    />
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                    <div>
                      <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Reserved</label>
                      <input 
                        type="number" name="reserved_quantity" value={formData.reserved_quantity} onChange={handleInputChange} min="0"
                        style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none' }}
                      />
                    </div>
                    <div>
                      <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Low Limit</label>
                      <input 
                        type="number" name="low_stock_limit" value={formData.low_stock_limit} onChange={handleInputChange} min="0"
                        style={{ width: '100%', boxSizing: 'border-box', padding: '12px 14px', border: '1px solid #d1d5db', borderRadius: '8px', fontSize: '0.95rem', outline: 'none' }}
                      />
                    </div>
                  </div>

                  <div style={{ gridColumn: '1 / -1' }}>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151', cursor: 'pointer' }}>
                      <input type="checkbox" name="is_available" checked={formData.is_available} onChange={handleInputChange} style={{ width: '18px', height: '18px', cursor: 'pointer' }} />
                      Is Available for Sale
                    </label>
                  </div>

                  {modalMode === 'add' && (
                    <div style={{ gridColumn: '1 / -1' }}>
                      <label style={{ display: 'block', marginBottom: '8px', fontSize: '0.875rem', fontWeight: '600', color: '#374151' }}>Variant Images (Max 10)</label>
                      <div style={{ border: '2px dashed #d1d5db', padding: '16px', borderRadius: '8px', textAlign: 'center', backgroundColor: '#f9fafb' }}>
                        <input 
                          type="file" 
                          name="images" 
                          onChange={handleInputChange} 
                          accept="image/jpeg,image/png,image/webp"
                          multiple
                          style={{ width: '100%', boxSizing: 'border-box', fontSize: '0.875rem', cursor: 'pointer' }}
                        />
                      </div>
                    </div>
                  )}

                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px', marginTop: '16px' }}>
                  <button type="button" onClick={closeModal} style={{ padding: '10px 20px', borderRadius: '8px', fontWeight: '600', border: '1px solid #d1d5db', backgroundColor: '#fff', color: '#374151', cursor: 'pointer' }}>Cancel</button>
                  <button type="submit" disabled={isSaving} style={{ padding: '10px 24px', borderRadius: '8px', fontWeight: '600', backgroundColor: '#A049A3', border: 'none', color: '#fff', cursor: isSaving ? 'not-allowed' : 'pointer', opacity: isSaving ? 0.7 : 1, display: 'flex', alignItems: 'center', gap: '8px' }}>
                    {isSaving ? 'Saving...' : 'Save Variant'}
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

export default AdminVariants;
