import React, { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';

const AdminVariants = () => {
  const { token } = useAuth();
  const [variants, setVariants] = useState([]);
  const [products, setProducts] = useState([]);
  const [sizes, setSizes] = useState([]);
  const [colours, setColours] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);

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
  const fetchVariants = async (page = 1) => {
    setIsLoading(true);
    setError('');
    try {
      const response = await fetch(`http://localhost/vivisha_boutique/backend/api/varient/list.php?page=${page}&limit=10`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      if (result.status) {
        setVariants(result.data.variants);
        if (result.data.pagination) {
          setCurrentPage(result.data.pagination.page);
          setTotalPages(result.data.pagination.total_pages);
          setTotalRecords(result.data.pagination.total_records);
        }
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
    fetchVariants(1);
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
        product_id: variant.product?.id || variant.product_id || '',
        size_id: variant.size?.id || '',
        color_id: variant.color?.id || '',
        sku: variant.sku || '',
        variant_name: variant.variant_name || '',
        original_price: variant.pricing?.original_price ?? variant.original_price ?? '0.00',
        discount_type: variant.pricing?.discount_type ?? variant.discount_type ?? 'none',
        discount_value: variant.pricing?.discount_value ?? variant.discount_value ?? '0',
        stock_quantity: variant.stock?.stock_quantity ?? variant.stock_quantity ?? '0',
        reserved_quantity: variant.stock?.reserved_quantity ?? variant.reserved_quantity ?? '0',
        low_stock_limit: variant.stock?.low_stock_limit ?? variant.low_stock_limit ?? '5',
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
        fetchVariants(currentPage);
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
          <button className="admin-btn admin-btn-primary" onClick={() => openModal('add')}>
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

      <div className="admin-premium-card" style={{ padding: '0', overflow: 'hidden' }}>
        {isLoading ? (
          <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading variants...</div>
        ) : (
          <div className="admin-table-container">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Product</th>
                  <th>Size / Color</th>
                  <th>Price</th>
                  <th>Stock</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
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
                  variants.map(vari => {
                    const price = vari.pricing?.selling_price ?? vari.selling_price ?? vari.pricing?.original_price ?? vari.original_price ?? '0.00';
                    const productName = vari.product?.name ?? vari.product_name ?? '-';
                    const stockQty = vari.stock?.stock_quantity ?? vari.stock_quantity ?? 0;
                    const lowStockLimit = vari.stock?.low_stock_limit ?? vari.low_stock_limit ?? 5;

                    return (
                      <tr key={vari.id}>
                        <td style={{ fontWeight: '600', color: '#111827', fontFamily: 'monospace' }}>{vari.sku}</td>
                        <td>{productName}</td>
                        <td>
                          {vari.size?.name ? <span style={{ marginRight: '8px', padding: '2px 6px', background: '#f3f4f6', borderRadius: '4px', fontWeight: '500' }}>{vari.size.name}</span> : null}
                          {vari.color?.name ? (
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
                              <span style={{ display: 'inline-block', width: '12px', height: '12px', borderRadius: '50%', backgroundColor: vari.color.hex_code, border: '1px solid #e5e7eb' }}></span>
                              {vari.color.name}
                            </span>
                          ) : null}
                        </td>
                        <td style={{ fontWeight: '600', color: '#059669' }}>
                          ₹{price}
                        </td>
                        <td>
                          <span className={`admin-badge ${parseInt(stockQty) > parseInt(lowStockLimit) ? 'admin-badge-active' : 'admin-badge-blocked'}`}>
                            {stockQty} in stock
                          </span>
                        </td>
                        <td style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', alignItems: 'center', height: '100%', minHeight: '52px' }}>
                        <button
                          onClick={() => openModal('view', vari)}
                          className="admin-action-btn admin-action-view"
                        >
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                          View
                        </button>
                        <button
                          onClick={() => openModal('edit', vari)}
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
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        )}

        {!isLoading && totalPages > 1 && (
          <div className="admin-pagination-wrapper">
            <div className="admin-pagination-info">
              Showing {(currentPage - 1) * 10 + 1} to {Math.min(currentPage * 10, totalRecords)} of {totalRecords} variants
            </div>
            <AdminPagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={(page) => fetchVariants(page)}
            />
          </div>
        )}
      </div>

      {/* Modal */}
      {isModalOpen && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '750px' }}>
            <div className="admin-modal-header">
              <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#111827', fontWeight: '700' }}>
                {modalMode === 'add' ? 'Add New Variant' : modalMode === 'edit' ? 'Edit Variant' : 'Variant Details'}
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
                    <div className="admin-info-box">
                      <span className="admin-info-label">SKU</span>
                      <span style={{ fontSize: '1rem', fontWeight: '600', color: '#111827', fontFamily: 'monospace' }}>{formData.sku}</span>
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">Product</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151', fontWeight: '500' }}>
                        {products.find(p => p.id === parseInt(formData.product_id))?.name || '-'}
                      </span>
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">Size</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>
                        {sizes.find(s => s.id === parseInt(formData.size_id))?.name || 'None'}
                      </span>
                    </div>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Colour</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151', display: 'flex', alignItems: 'center', gap: '6px' }}>
                        {(() => {
                          const col = colours.find(c => c.id === parseInt(formData.color_id));
                          return col ? (
                            <><span style={{ width: '12px', height: '12px', borderRadius: '50%', backgroundColor: col.hex_code, border: '1px solid #e5e7eb' }}></span> {col.name}</>
                          ) : 'None';
                        })()}
                      </span>
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">Orig Price</span>
                      <span style={{ fontSize: '1rem', fontWeight: '600', color: '#374151' }}>₹{formData.original_price}</span>
                    </div>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Discount</span>
                      <span style={{ fontSize: '0.95rem', color: '#4b5563' }}>
                        {formData.discount_type === 'none' ? 'None' : `${formData.discount_type === 'flat' ? '₹' : ''}${formData.discount_value}${formData.discount_type === 'percentage' ? '%' : ''}`}
                      </span>
                    </div>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Stock Qty</span>
                      <span style={{ fontSize: '1rem', fontWeight: '600', color: parseInt(formData.stock_quantity) > parseInt(formData.low_stock_limit) ? '#059669' : '#dc2626' }}>
                        {formData.stock_quantity}
                      </span>
                    </div>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Availability</span>
                      <span style={{ fontSize: '0.95rem', color: formData.is_available ? '#059669' : '#4b5563', fontWeight: '600' }}>
                        {formData.is_available ? 'Available' : 'Unavailable'}
                      </span>
                    </div>
                  </div>
                </div>
              ) : (
                <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>

                    <div style={{ gridColumn: '1 / -1' }}>
                      <label className="admin-label">Select Product <span style={{ color: '#ef4444' }}>*</span></label>
                      <select
                        name="product_id"
                        value={formData.product_id}
                        onChange={handleInputChange}
                        required
                        className="admin-input"
                      >
                        <option value="" disabled>Choose a product</option>
                        {products.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                      </select>
                    </div>

                    <div>
                      <label className="admin-label">SKU <span style={{ color: '#ef4444' }}>*</span></label>
                      <input
                        type="text" name="sku" value={formData.sku} onChange={handleInputChange} required
                        placeholder="e.g. SAREE-RED-M"
                        className="admin-input"
                      />
                    </div>

                    <div>
                      <label className="admin-label">Variant Name</label>
                      <input
                        type="text" name="variant_name" value={formData.variant_name} onChange={handleInputChange}
                        placeholder="Optional display name"
                        className="admin-input"
                      />
                    </div>

                    <div>
                      <label className="admin-label">Size</label>
                      <select
                        name="size_id" value={formData.size_id} onChange={handleInputChange}
                        className="admin-input"
                      >
                        <option value="">No specific size</option>
                        {sizes.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                      </select>
                    </div>

                    <div>
                      <label className="admin-label">Colour</label>
                      <select
                        name="color_id" value={formData.color_id} onChange={handleInputChange}
                        className="admin-input"
                      >
                        <option value="">No specific colour</option>
                        {colours.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                      </select>
                    </div>

                    <div>
                      <label className="admin-label">Original Price (₹) <span style={{ color: '#ef4444' }}>*</span></label>
                      <input
                        type="number" step="0.01" name="original_price" value={formData.original_price} onChange={handleInputChange} required
                        className="admin-input"
                      />
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                      <div>
                        <label className="admin-label">Discount</label>
                        <select
                          name="discount_type" value={formData.discount_type} onChange={handleInputChange}
                          className="admin-input"
                        >
                          <option value="none">None</option>
                          <option value="percentage">%</option>
                          <option value="flat">Flat (₹)</option>
                        </select>
                      </div>
                      <div>
                        <label className="admin-label">Value</label>
                        <input
                          type="number" step="0.01" name="discount_value" value={formData.discount_value} onChange={handleInputChange} disabled={formData.discount_type === 'none'}
                          className="admin-input"
                          style={{ backgroundColor: formData.discount_type === 'none' ? '#f3f4f6' : '#fff' }}
                        />
                      </div>
                    </div>

                    <div>
                      <label className="admin-label">Stock Quantity <span style={{ color: '#ef4444' }}>*</span></label>
                      <input
                        type="number" name="stock_quantity" value={formData.stock_quantity} onChange={handleInputChange} required min="0"
                        className="admin-input"
                      />
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                      <div>
                        <label className="admin-label">Reserved</label>
                        <input
                          type="number" name="reserved_quantity" value={formData.reserved_quantity} onChange={handleInputChange} min="0"
                          className="admin-input"
                        />
                      </div>
                      <div>
                        <label className="admin-label">Low Limit</label>
                        <input
                          type="number" name="low_stock_limit" value={formData.low_stock_limit} onChange={handleInputChange} min="0"
                          className="admin-input"
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
                        <label className="admin-label">Variant Images (Max 10)</label>
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
                    'Save Variant'
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
