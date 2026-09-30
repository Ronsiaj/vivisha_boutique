import React, { useState, useEffect } from 'react';
import { useAdminAuth } from '../../context/AuthContext.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';
import AsyncSelect from 'react-select/async';
import AdminKpiCard from '../../components/admin/AdminKpiCard.jsx';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';
const ASSET_BASE_URL = import.meta.env.VITE_ASSET_BASE_URL || 'http://localhost/vivisha_boutique/backend/';

const formatOrderItem = (item) => {
  if (!item) return {};

  const productName = item.snapshot?.product_name 
    || item.current_product?.name 
    || item.product_name 
    || 'Product';

  const variantName = item.snapshot?.variant_name 
    || item.current_variant?.variant_name 
    || item.variant_name 
    || '';

  const sizeName = item.snapshot?.size_name 
    || item.current_variant?.size?.name 
    || item.size_name 
    || '';

  const colorName = item.snapshot?.color_name 
    || item.current_variant?.color?.name 
    || item.color_name 
    || '';

  const sku = item.snapshot?.sku 
    || item.current_variant?.sku 
    || item.sku 
    || '-';

  const hsnCode = item.snapshot?.hsn_code 
    || item.hsn_profile?.hsn_code 
    || item.current_hsn_code 
    || item.hsn_code 
    || '-';

  const rawSellingPrice = item.snapshot?.pricing?.selling_price 
    ?? item.selling_price 
    ?? item.current_variant?.pricing?.selling_price 
    ?? item.unit_price 
    ?? 0;
  const sellingPrice = parseFloat(rawSellingPrice || 0);

  const rawOriginalPrice = item.snapshot?.pricing?.original_price 
    ?? item.original_price 
    ?? item.current_variant?.pricing?.original_price;
  const originalPrice = rawOriginalPrice !== null && rawOriginalPrice !== undefined ? parseFloat(rawOriginalPrice) : null;

  const quantity = Number(item.snapshot?.pricing?.quantity ?? item.quantity ?? 1);

  const gstRate = parseFloat(item.snapshot?.tax?.gst_rate ?? item.gst_rate ?? item.current_variant?.pricing?.gst_rate ?? 0);
  const cgstAmount = parseFloat(item.snapshot?.tax?.cgst_amount ?? item.cgst_amount ?? 0);
  const sgstAmount = parseFloat(item.snapshot?.tax?.sgst_amount ?? item.sgst_amount ?? 0);
  const igstAmount = parseFloat(item.snapshot?.tax?.igst_amount ?? item.igst_amount ?? 0);
  const taxAmount = parseFloat(item.snapshot?.tax?.tax_amount ?? item.tax_amount ?? (cgstAmount + sgstAmount + igstAmount));

  const rawLineTotal = item.snapshot?.pricing?.line_total 
    ?? item.line_total 
    ?? (sellingPrice * quantity);
  const lineTotal = parseFloat(rawLineTotal || 0);

  const rawImg = item.current_variant?.primary_image?.image
    || item.current_variant?.images?.[0]?.image
    || item.images?.[0]?.image
    || (typeof item.images?.[0] === 'string' ? item.images[0] : null)
    || item.image;

  let imageUrl = '';
  if (rawImg) {
    if (rawImg.startsWith('http://') || rawImg.startsWith('https://')) {
      imageUrl = rawImg;
    } else {
      const base = ASSET_BASE_URL.replace(/\/+$/, '');
      imageUrl = `${base}/${rawImg.replace(/^\/+/, '')}`;
    }
  }

  const categoryName = item.category?.name || item.current_product?.category_name || '';

  return {
    productName,
    variantName,
    sizeName,
    colorName,
    sku,
    hsnCode,
    sellingPrice,
    originalPrice,
    quantity,
    gstRate,
    cgstAmount,
    sgstAmount,
    igstAmount,
    taxAmount,
    lineTotal,
    imageUrl,
    categoryName
  };
};

const AdminOrders = () => {
  const { token } = useAdminAuth();
  const [orders, setOrders] = useState([]);
  const [summary, setSummary] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  // Filters, Search & Sorting
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedSearchOption, setSelectedSearchOption] = useState(null);
  const [orderStatusFilter, setOrderStatusFilter] = useState('');
  const [paymentStatusFilter, setPaymentStatusFilter] = useState('');
  const [paymentMethodFilter, setPaymentMethodFilter] = useState('');
  const [sortBy, setSortBy] = useState('created_at');
  const [sortOrder, setSortOrder] = useState('desc');
  const [page, setPage] = useState(1);
  const [limit] = useState(15);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);

  // Modals
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [isFetchingDetails, setIsFetchingDetails] = useState(false);

  const [isAddressModalOpen, setIsAddressModalOpen] = useState(false);
  const [selectedOrderAddress, setSelectedOrderAddress] = useState(null);

  // Status Update Modal State
  const [isStatusModalOpen, setIsStatusModalOpen] = useState(false);
  const [statusOrder, setStatusOrder] = useState(null);
  const [selectedTargetStatus, setSelectedTargetStatus] = useState('');
  const [statusCancelReason, setStatusCancelReason] = useState('');
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);
  const [statusModalError, setStatusModalError] = useState('');
  const [statusSuccessMsg, setStatusSuccessMsg] = useState('');

  // Fetch Orders from backend
  const fetchOrders = async () => {
    setIsLoading(true);
    setError('');

    const params = new URLSearchParams({
      page: page.toString(),
      limit: limit.toString(),
      sort_by: sortBy,
      sort_order: sortOrder
    });

    if (searchQuery.trim()) params.append('q', searchQuery.trim());
    if (orderStatusFilter) params.append('order_status', orderStatusFilter);
    if (paymentStatusFilter) params.append('payment_status', paymentStatusFilter);
    if (paymentMethodFilter) params.append('payment_method', paymentMethodFilter);

    try {
      const response = await fetch(`${API_BASE_URL}/orders/list.php?${params.toString()}`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const result = await response.json();

      if (result.status && result.data) {
        setOrders(result.data.orders || []);
        setSummary(result.data.summary || null);
        if (result.data.pagination) {
          setTotalPages(result.data.pagination.total_pages || 1);
          setTotalRecords(result.data.pagination.total_records || 0);
        }
      } else {
        setOrders([]);
        setError(result.message || 'Failed to fetch orders.');
      }
    } catch (err) {
      console.error('Error fetching admin orders:', err);
      setError('An error occurred while communicating with the server.');
      setOrders([]);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, searchQuery, orderStatusFilter, paymentStatusFilter, paymentMethodFilter, sortBy, sortOrder]);

  // Load options dynamically for React-Select
  const loadOrderOptions = async (inputValue) => {
    if (!inputValue || !inputValue.trim()) return [];
    try {
      const response = await fetch(
        `${API_BASE_URL}/orders/list.php?q=${encodeURIComponent(inputValue.trim())}&limit=10`,
        {
          headers: { 'Authorization': `Bearer ${token}` }
        }
      );
      const result = await response.json();
      if (result.status && result.data?.orders) {
        return result.data.orders.map((o) => ({
          value: o.order_number,
          label: `#${o.order_number} — ${o.user?.name || 'Customer'} (₹${parseFloat(o.amounts?.grand_total || o.grand_total || 0).toFixed(0)})`,
          order: o
        }));
      }
      return [];
    } catch (err) {
      console.error('Error loading order search options:', err);
      return [];
    }
  };

  const handleSearchSelectChange = (selectedOption) => {
    setSelectedSearchOption(selectedOption);
    if (selectedOption) {
      setSearchQuery(selectedOption.value);
    } else {
      setSearchQuery('');
    }
    setPage(1);
  };

  const hasActiveFilters = Boolean(searchQuery || selectedSearchOption || orderStatusFilter || paymentStatusFilter || paymentMethodFilter || sortBy !== 'created_at' || sortOrder !== 'desc');

  const handleClearFilters = () => {
    setSelectedSearchOption(null);
    setSearchQuery('');
    setOrderStatusFilter('');
    setPaymentStatusFilter('');
    setPaymentMethodFilter('');
    setSortBy('created_at');
    setSortOrder('desc');
    setPage(1);
  };

  // Open Detailed Order Modal
  const openViewModal = async (orderId) => {
    setIsFetchingDetails(true);
    setSelectedOrder(null);
    setIsViewModalOpen(true);

    try {
      const response = await fetch(`${API_BASE_URL}/orders/view.php?id=${orderId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();

      if (result.status && result.data?.order) {
        setSelectedOrder(result.data.order);
      } else {
        setError(result.message || 'Failed to load order details');
      }
    } catch (err) {
      console.error('Error fetching order view:', err);
    } finally {
      setIsFetchingDetails(false);
    }
  };

  const closeViewModal = () => {
    setIsViewModalOpen(false);
    setSelectedOrder(null);
  };

  // Open Address Modal
  const openAddressModal = (order) => {
    setSelectedOrderAddress({
      order_number: order.order_number,
      customer_name: order.user?.name,
      address: order.address
    });
    setIsAddressModalOpen(true);
  };

  const closeAddressModal = () => {
    setIsAddressModalOpen(false);
    setSelectedOrderAddress(null);
  };

  // Status Update Handlers
  const openStatusModal = (order) => {
    setStatusOrder(order);
    setSelectedTargetStatus('');
    setStatusCancelReason(order.cancel_reason || '');
    setStatusModalError('');
    setIsStatusModalOpen(true);
  };

  const closeStatusModal = () => {
    if (isUpdatingStatus) return;
    setIsStatusModalOpen(false);
    setStatusOrder(null);
    setSelectedTargetStatus('');
    setStatusCancelReason('');
    setStatusModalError('');
  };

  const PIPELINE_ORDER = [
    'pending',
    'confirmed',
    'processing',
    'packed',
    'shipped',
    'out_for_delivery',
    'delivered'
  ];

  const getTransitionSteps = (currentStatus, targetStatus) => {
    if (targetStatus === 'cancelled') {
      return ['cancelled'];
    }

    const currentIndex = PIPELINE_ORDER.indexOf(currentStatus);
    const targetIndex = PIPELINE_ORDER.indexOf(targetStatus);

    if (currentIndex === -1 || targetIndex === -1 || targetIndex <= currentIndex) {
      return [targetStatus];
    }

    return PIPELINE_ORDER.slice(currentIndex + 1, targetIndex + 1);
  };

  const handleStatusSubmit = async (e) => {
    if (e) e.preventDefault();
    if (!statusOrder || !selectedTargetStatus) return;

    if (selectedTargetStatus === statusOrder.order_status) {
      setStatusModalError('The order is already in this status.');
      return;
    }

    setIsUpdatingStatus(true);
    setStatusModalError('');

    try {
      const steps = getTransitionSteps(statusOrder.order_status, selectedTargetStatus);
      let lastResult = null;

      for (const step of steps) {
        const payload = {
          id: statusOrder.id,
          order_status: step
        };

        if (step === 'cancelled') {
          payload.cancel_reason = statusCancelReason.trim() || 'Cancelled by administrator';
        }

        const response = await fetch(`${API_BASE_URL}/orders/status_update.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!result.status) {
          throw new Error(result.message || `Failed to transition order to ${step}.`);
        }

        lastResult = result;
      }

      setStatusSuccessMsg(
        lastResult?.message ||
        `Order #${statusOrder.order_number} status updated to ${selectedTargetStatus.toUpperCase()} successfully.`
      );

      closeStatusModal();

      // Refresh orders list
      await fetchOrders();

      // If the Order Details modal is currently open for this order, refresh its details too
      if (selectedOrder && selectedOrder.id === statusOrder.id) {
        await openViewModal(statusOrder.id);
      }

      setTimeout(() => {
        setStatusSuccessMsg('');
      }, 5000);
    } catch (err) {
      console.error('Error updating order status:', err);
      setStatusModalError(err.message || 'An error occurred while updating the order status.');
    } finally {
      setIsUpdatingStatus(false);
    }
  };

  // Status Badge Colors
  const getOrderStatusBadge = (status) => {
    switch (status) {
      case 'confirmed':
      case 'delivered':
        return 'admin-badge-active';
      case 'shipped':
        return 'admin-badge-info';
      case 'cancelled':
        return 'admin-badge-danger';
      case 'pending':
      default:
        return 'admin-badge-warning';
    }
  };

  const getPaymentStatusBadge = (status) => {
    switch (status) {
      case 'success':
        return 'admin-badge-active';
      case 'processing':
        return 'admin-badge-info';
      case 'failed':
      case 'cancelled':
      case 'refunded':
        return 'admin-badge-danger';
      case 'pending':
      default:
        return 'admin-badge-warning';
    }
  };

  return (
    <div className="admin-page-container">
      {/* Header & Title */}
      <div className="admin-header-row" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, fontSize: '1.75rem', fontWeight: '800', color: '#111827' }}>Orders Management</h1>
          <p style={{ margin: '4px 0 0 0', color: '#6b7280', fontSize: '0.9rem' }}>
            Monitor boutique sales, fulfill customer orders, and track invoices & deliveries
          </p>
        </div>

        <button
          onClick={() => fetchOrders()}
          className="admin-btn admin-btn-secondary"
          style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <polyline points="23 4 23 10 17 10"></polyline>
            <polyline points="1 20 1 14 7 14"></polyline>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
          </svg>
          Refresh Orders
        </button>
      </div>

      {/* Status Update Success Banner */}
      {statusSuccessMsg && (
        <div style={{ background: '#dcfce7', color: '#166534', border: '1px solid #bbf7d0', padding: '12px 18px', borderRadius: '8px', marginBottom: '20px', fontSize: '0.9rem', display: 'flex', alignItems: 'center', justifyContent: 'space-between', animation: 'fadeIn 0.2s ease' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span><strong>Success:</strong> {statusSuccessMsg}</span>
          </div>
          <button
            type="button"
            onClick={() => setStatusSuccessMsg('')}
            style={{ background: 'none', border: 'none', color: '#166534', cursor: 'pointer', fontSize: '1.2rem', fontWeight: 'bold' }}
            aria-label="Dismiss"
          >
            &times;
          </button>
        </div>
      )}

      {/* Aggregate Statistics Cards (Inspired by Reference Design) */}
      {summary && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px', marginBottom: '28px' }}>
          {/* KPI 1: Total Orders */}
          <AdminKpiCard
            variant="blue"
            title="Total Orders"
            value={summary.total_orders.toLocaleString('en-IN')}
            badgeText="Filtered Scope"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
              </svg>
            }
            footerLeft={<span>Total Invoices</span>}
            footerRight={<span>Boutique Sales</span>}
          />

          {/* KPI 2: Total Revenue */}
          <AdminKpiCard
            variant="pink"
            title="Total Revenue"
            value={`₹${parseFloat(summary.grand_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`}
            badgeText="Grand Total"
            icon={
              <span style={{ fontSize: '1.25rem', fontWeight: '800' }}>₹</span>
            }
            footerLeft={<span>Product Subtotal</span>}
            footerRight={<span>₹{parseFloat(summary.subtotal || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}</span>}
          />

          {/* KPI 3: Total Discounts Given */}
          <AdminKpiCard
            variant="orange"
            title="Discounts Given"
            value={`₹${(parseFloat(summary.product_discount_amount || 0) + parseFloat(summary.coupon_discount_amount || 0)).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`}
            badgeText="Savings"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="19" y1="5" x2="5" y2="19"></line>
                <circle cx="6.5" cy="6.5" r="2.5"></circle>
                <circle cx="17.5" cy="17.5" r="2.5"></circle>
              </svg>
            }
            footerLeft={<span>Products + Coupons</span>}
            footerRight={<span>Buyer Savings</span>}
          />

          {/* KPI 4: COD Handling Fees */}
          <AdminKpiCard
            variant="purple"
            title="COD Fees"
            value={`₹${parseFloat(summary.cod_charge || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`}
            badgeText="Logistics"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="1" y="3" width="15" height="13"></rect>
                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                <circle cx="18.5" cy="18.5" r="2.5"></circle>
              </svg>
            }
            footerLeft={<span>COD Deliveries</span>}
            footerRight={<span>Handling Surcharge</span>}
          />
        </div>
      )}

      {/* Search & Filters Card */}
      <div style={{ background: '#fff', padding: '20px', borderRadius: '12px', border: '1px solid #e5e7eb', marginBottom: '20px', boxShadow: '0 1px 3px rgba(0,0,0,0.03)' }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '14px', alignItems: 'flex-end' }}>
          {/* Search Input with React-Select */}
          <div style={{ gridColumn: 'span 2' }}>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Search Orders (Order #, Customer, Mobile, Email, SKU...)
            </label>
            <AsyncSelect
              cacheOptions
              defaultOptions={false}
              loadOptions={loadOrderOptions}
              value={selectedSearchOption}
              onChange={handleSearchSelectChange}
              placeholder="Type to search Order #, Customer, Mobile, Email..."
              isClearable
              noOptionsMessage={({ inputValue }) =>
                !inputValue ? 'Type to search orders...' : 'No matching orders found'
              }
              styles={{
                control: (base, state) => ({
                  ...base,
                  borderColor: state.isFocused ? 'var(--primary-color, #6b21a8)' : '#d1d5db',
                  boxShadow: state.isFocused ? '0 0 0 1px var(--primary-color, #6b21a8)' : 'none',
                  borderRadius: '6px',
                  fontSize: '0.88rem',
                  minHeight: '40px',
                  backgroundColor: '#fff'
                }),
                clearIndicator: (base) => ({
                  ...base,
                  cursor: 'pointer',
                  color: '#9ca3af',
                  padding: '6px',
                  '&:hover': {
                    color: '#be123c'
                  }
                }),
                menu: (base) => ({
                  ...base,
                  zIndex: 9999,
                  boxShadow: '0 4px 12px rgba(0,0,0,0.12)',
                  borderRadius: '8px'
                }),
                option: (base, state) => ({
                  ...base,
                  fontSize: '0.84rem',
                  backgroundColor: state.isSelected
                    ? 'var(--primary-color, #6b21a8)'
                    : state.isFocused
                    ? '#f3e8ff'
                    : 'transparent',
                  color: state.isSelected ? '#fff' : '#1f2937',
                  cursor: 'pointer'
                })
              }}
            />
          </div>

          {/* Order Status Filter */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Order Status
            </label>
            <select
              className="admin-input"
              value={orderStatusFilter}
              onChange={(e) => {
                setOrderStatusFilter(e.target.value);
                setPage(1);
              }}
            >
              <option value="">All Order Statuses</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="shipped">Shipped</option>
              <option value="delivered">Delivered</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>

          {/* Payment Status Filter */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Payment Status
            </label>
            <select
              className="admin-input"
              value={paymentStatusFilter}
              onChange={(e) => {
                setPaymentStatusFilter(e.target.value);
                setPage(1);
              }}
            >
              <option value="">All Payment Statuses</option>
              <option value="pending">Pending</option>
              <option value="processing">Processing</option>
              <option value="success">Success</option>
              <option value="failed">Failed</option>
              <option value="cancelled">Cancelled</option>
              <option value="refunded">Refunded</option>
            </select>
          </div>

          {/* Payment Method Filter */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Payment Method
            </label>
            <select
              className="admin-input"
              value={paymentMethodFilter}
              onChange={(e) => {
                setPaymentMethodFilter(e.target.value);
                setPage(1);
              }}
            >
              <option value="">All Methods</option>
              <option value="cod">Cash on Delivery (COD)</option>
              <option value="upi">UPI / Instant Pay</option>
              <option value="card">Credit / Debit Card</option>
              <option value="netbanking">Net Banking</option>
              <option value="razorpay">Razorpay</option>
            </select>
          </div>

          {/* Sort By */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Sort By
            </label>
            <div style={{ display: 'flex', gap: '6px' }}>
              <select
                className="admin-input"
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                style={{ flex: 1 }}
              >
                <option value="created_at">Order Date</option>
                <option value="grand_total">Total Amount</option>
                <option value="order_number">Order #</option>
                <option value="order_status">Order Status</option>
              </select>
              <button
                type="button"
                className="admin-btn admin-btn-secondary"
                style={{ padding: '0 12px' }}
                onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
                title={`Sort ${sortOrder === 'asc' ? 'Descending' : 'Ascending'}`}
              >
                {sortOrder === 'asc' ? '↑' : '↓'}
              </button>
            </div>
          </div>

          {/* Clear Option */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem', visibility: 'hidden' }}>
              Reset
            </label>
            <button
              type="button"
              onClick={handleClearFilters}
              className="admin-btn admin-btn-clear"
              style={{
                height: '40px',
                width: '100%',
                justifyContent: 'center',
                boxSizing: 'border-box'
              }}
              title="Clear all filters and searches"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              Clear Filters
            </button>
          </div>
        </div>

        {hasActiveFilters && (
          <div style={{ marginTop: '14px', paddingTop: '12px', borderTop: '1px solid #f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '8px' }}>
            <span style={{ fontSize: '0.82rem', color: '#6b7280' }}>
              Active filters applied • Showing <strong>{totalRecords}</strong> matching orders
            </span>
            <button
              type="button"
              onClick={handleClearFilters}
              style={{
                background: 'none',
                border: 'none',
                color: 'var(--primary-color, #6b21a8)',
                fontWeight: '700',
                fontSize: '0.82rem',
                cursor: 'pointer',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '4px'
              }}
            >
              ✕ Clear All Filters
            </button>
          </div>
        )}
      </div>

      {/* Error Message */}
      {error && (
        <div style={{ background: '#fee2e2', color: '#991b1b', padding: '12px 16px', borderRadius: '8px', marginBottom: '20px', fontSize: '0.9rem' }}>
          {error}
        </div>
      )}

      {/* Orders Data Table */}
      <div style={{ background: '#fff', borderRadius: '12px', border: '1px solid #e5e7eb', overflow: 'hidden', boxShadow: '0 1px 3px rgba(0,0,0,0.03)' }}>
        {isLoading ? (
          <div style={{ padding: '60px', textAlign: 'center', color: '#6b7280' }}>
            <div className="spinner" style={{ margin: '0 auto 14px' }}></div>
            Loading orders list...
          </div>
        ) : orders.length === 0 ? (
          <div style={{ padding: '60px 20px', textAlign: 'center', color: '#6b7280' }}>
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color, #6b21a8)" strokeWidth="1.5" style={{ marginBottom: '12px' }}>
              <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
              <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            <h3 style={{ margin: '0 0 6px 0', fontSize: '1.2rem', color: '#111827' }}>No Orders Found</h3>
            <p style={{ margin: 0, fontSize: '0.88rem' }}>
              {hasActiveFilters ? 'No orders match your search and filter criteria.' : 'There are no customer orders recorded yet.'}
            </p>
            {hasActiveFilters && (
              <button
                type="button"
                onClick={handleClearFilters}
                className="admin-btn admin-btn-secondary"
                style={{ marginTop: '16px' }}
              >
                Clear Filters
              </button>
            )}
          </div>
        ) : (
          <div style={{ overflowX: 'auto' }}>
            <table className="admin-table" style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: '0.88rem' }}>
              <thead>
                <tr style={{ background: '#f9fafb', borderBottom: '1px solid #e5e7eb', color: '#4b5563', textTransform: 'uppercase', fontSize: '0.72rem', letterSpacing: '0.05em' }}>
                  <th style={{ padding: '14px 18px' }}>Order Details</th>
                  <th style={{ padding: '14px 18px' }}>Customer</th>
                  <th style={{ padding: '14px 18px' }}>Items Summary</th>
                  <th style={{ padding: '14px 18px' }}>Payment</th>
                  <th style={{ padding: '14px 18px' }}>Order Status</th>
                  <th style={{ padding: '14px 18px' }}>Amount</th>
                  <th style={{ padding: '14px 18px', textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((order) => (
                  <tr key={order.id} style={{ borderBottom: '1px solid #f3f4f6', transition: 'background 0.15s ease' }}>
                    {/* Order Details */}
                    <td style={{ padding: '14px 18px' }}>
                      <div style={{ fontWeight: '800', color: 'var(--primary-color, #6b21a8)', fontSize: '0.92rem' }}>
                        #{order.order_number}
                      </div>
                      <div style={{ fontSize: '0.76rem', color: '#6b7280', marginTop: '2px' }}>
                        {order.placed_at || order.created_at ? new Date(order.placed_at || order.created_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '-'}
                      </div>
                      {order.address?.city && (
                        <div style={{ fontSize: '0.75rem', color: '#4b5563', marginTop: '4px', display: 'flex', alignItems: 'center', gap: '4px' }}>
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                          </svg>
                          {order.address.city}, {order.address.state}
                        </div>
                      )}
                    </td>

                    {/* Customer */}
                    <td style={{ padding: '14px 18px' }}>
                      <div style={{ fontWeight: '600', color: '#111827' }}>
                        {order.user?.name || 'Guest User'}
                      </div>
                      <div style={{ fontSize: '0.78rem', color: '#6b7280' }}>
                        +{order.user?.mobile || '-'}
                      </div>
                      {order.user?.email && (
                        <div style={{ fontSize: '0.75rem', color: '#9ca3af' }}>
                          {order.user.email}
                        </div>
                      )}
                    </td>

                    {/* Items Summary */}
                    <td style={{ padding: '14px 18px' }}>
                      {(() => {
                        const firstItem = order.items && order.items.length > 0 ? formatOrderItem(order.items[0]) : null;
                        return (
                          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            {firstItem?.imageUrl ? (
                              <img
                                src={firstItem.imageUrl}
                                alt={firstItem.productName || 'Product'}
                                style={{ width: '38px', height: '38px', borderRadius: '6px', objectFit: 'cover', background: '#f3f4f6' }}
                              />
                            ) : null}
                            <div>
                              <div style={{ fontWeight: '600', color: '#1f2937', fontSize: '0.85rem' }}>
                                {order.items_summary?.total_quantity || order.items?.reduce((sum, it) => sum + (it.snapshot?.pricing?.quantity || it.quantity || 1), 0) || 1} {((order.items_summary?.total_quantity || 1) === 1) ? 'item' : 'items'}
                              </div>
                              <div style={{ fontSize: '0.75rem', color: '#6b7280', maxWidth: '180px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                {firstItem?.productName || 'Products'}
                                {order.items && order.items.length > 1 ? ` +${order.items.length - 1} more` : ''}
                              </div>
                            </div>
                          </div>
                        );
                      })()}
                    </td>

                    {/* Payment Info */}
                    <td style={{ padding: '14px 18px' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '4px' }}>
                        <span style={{
                          padding: '2px 8px',
                          borderRadius: '4px',
                          fontSize: '0.72rem',
                          fontWeight: '700',
                          textTransform: 'uppercase',
                          background: order.payment?.method === 'cod' ? '#fef3c7' : '#ede9fe',
                          color: order.payment?.method === 'cod' ? '#92400e' : '#5b21b6'
                        }}>
                          {order.payment?.method === 'cod' ? 'Cash on Delivery' : (order.payment?.method || 'Prepaid').toUpperCase()}
                        </span>
                      </div>
                      <span className={`admin-badge ${getPaymentStatusBadge(order.payment?.status)}`} style={{ textTransform: 'uppercase', fontSize: '0.7rem' }}>
                        {order.payment?.status || 'Pending'}
                      </span>
                    </td>

                    {/* Order Status */}
                    <td style={{ padding: '14px 18px' }}>
                      <span className={`admin-badge ${getOrderStatusBadge(order.order_status)}`} style={{ textTransform: 'uppercase', fontSize: '0.74rem', padding: '4px 10px', borderRadius: '12px' }}>
                        {order.order_status}
                      </span>
                      {order.customer_note && (
                        <div style={{ fontSize: '0.72rem', color: '#b45309', marginTop: '4px', fontWeight: '500' }} title={order.customer_note}>
                          📝 Has delivery note
                        </div>
                      )}
                    </td>

                    {/* Amount */}
                    <td style={{ padding: '14px 18px' }}>
                      <div style={{ fontSize: '1rem', fontWeight: '800', color: '#111827' }}>
                        ₹{parseFloat(order.amounts?.grand_total || order.grand_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                      </div>
                      {parseFloat(order.amounts?.cod_charge || order.cod_charge || 0) > 0 && (
                        <div style={{ fontSize: '0.72rem', color: '#92400e' }}>
                          incl. ₹{parseFloat(order.amounts?.cod_charge || order.cod_charge).toFixed(0)} COD fee
                        </div>
                      )}
                    </td>

                    {/* Actions */}
                    <td style={{ padding: '14px 18px', textAlign: 'right' }}>
                      <div style={{ display: 'inline-flex', gap: '8px' }}>
                        <button
                          type="button"
                          onClick={() => openViewModal(order.id)}
                          className="admin-btn admin-btn-primary"
                          style={{ padding: '6px 12px', fontSize: '0.8rem', fontWeight: '600' }}
                        >
                          View Details
                        </button>
                        <button
                          type="button"
                          onClick={() => openStatusModal(order)}
                          className="admin-btn admin-btn-secondary"
                          style={{ padding: '6px 10px', fontSize: '0.8rem', fontWeight: '600', display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                          title="Update Order Status"
                        >
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                          </svg>
                          <span>Status</span>
                        </button>
                        {order.address && (
                          <button
                            type="button"
                            onClick={() => openAddressModal(order)}
                            className="admin-btn admin-btn-secondary"
                            style={{ padding: '6px 10px', fontSize: '0.8rem' }}
                            title="View Delivery Address"
                          >
                            📍 Address
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {!isLoading && totalPages > 1 && (
          <div className="admin-pagination-wrapper">
            <div className="admin-pagination-info">
              Showing {(page - 1) * 10 + 1} to {Math.min(page * 10, totalRecords)} of {totalRecords} orders
            </div>
            <AdminPagination
              currentPage={page}
              totalPages={totalPages}
              onPageChange={(newPage) => setPage(newPage)}
            />
          </div>
        )}
      </div>

      {/* ========== ORDER DETAILS & INVOICE MODAL ========== */}
      {isViewModalOpen && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '880px', maxHeight: '90vh', overflowY: 'auto' }}>
            <div className="admin-modal-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid #e5e7eb', padding: '20px 28px' }}>
              <div>
                <h2 style={{ margin: 0, fontSize: '1.3rem', color: '#111827', fontWeight: '800' }}>
                  Order Details: #{selectedOrder?.order_number || 'Loading...'}
                </h2>
                {selectedOrder && (
                  <span style={{ fontSize: '0.82rem', color: '#6b7280' }}>
                    Placed on {selectedOrder.timeline?.placed_at || selectedOrder.timeline?.created_at ? new Date(selectedOrder.timeline.placed_at || selectedOrder.timeline.created_at).toLocaleString('en-IN') : 'Recent'}
                  </span>
                )}
              </div>
              <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                <button
                  type="button"
                  onClick={() => window.print()}
                  className="admin-btn admin-btn-secondary"
                  style={{ fontSize: '0.8rem', padding: '6px 12px' }}
                >
                  🖨️ Print Invoice
                </button>
                <button
                  type="button"
                  onClick={closeViewModal}
                  className="admin-modal-close"
                  title="Close"
                  aria-label="Close"
                >
                  &times;
                </button>
              </div>
            </div>

            <div className="admin-modal-body" style={{ padding: '26px 28px' }}>
              {isFetchingDetails ? (
                <div style={{ padding: '50px', textAlign: 'center', color: '#6b7280' }}>
                  <div className="spinner" style={{ margin: '0 auto 12px' }}></div>
                  Loading complete order details...
                </div>
              ) : selectedOrder ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                  {/* Status Banner */}
                  <div style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    padding: '16px 22px',
                    borderRadius: '10px',
                    background: '#f9fafb',
                    border: '1px solid #e5e7eb',
                    flexWrap: 'wrap',
                    gap: '14px'
                  }}>
                    <div>
                      <span style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: '#6b7280', display: 'block', fontWeight: '700', marginBottom: '4px' }}>
                        Order Status
                      </span>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span className={`admin-badge ${getOrderStatusBadge(selectedOrder.order_status)}`} style={{ fontSize: '0.82rem', textTransform: 'uppercase', padding: '4px 10px', borderRadius: '12px' }}>
                          {selectedOrder.order_status}
                        </span>
                        {selectedOrder.order_status !== 'delivered' && selectedOrder.order_status !== 'cancelled' && (
                          <button
                            type="button"
                            onClick={() => openStatusModal(selectedOrder)}
                            className="admin-btn admin-btn-secondary"
                            style={{ padding: '4px 10px', fontSize: '0.78rem', fontWeight: '600', borderRadius: '6px', display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                            title="Update Status"
                          >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            <span>Update</span>
                          </button>
                        )}
                      </div>
                    </div>

                    <div>
                      <span style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: '#6b7280', display: 'block', fontWeight: '700', marginBottom: '4px' }}>
                        Payment Method & Status
                      </span>
                      <strong style={{ fontSize: '0.9rem', color: '#111827' }}>
                        {selectedOrder.payment?.method === 'cod' ? 'Cash on Delivery' : (selectedOrder.payment?.method || 'Prepaid').toUpperCase()}
                      </strong>{' '}
                      <span className={`admin-badge ${getPaymentStatusBadge(selectedOrder.payment?.status)}`} style={{ textTransform: 'uppercase', fontSize: '0.7rem' }}>
                        {selectedOrder.payment?.status}
                      </span>
                    </div>

                    <div>
                      <span style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: '#6b7280', display: 'block', fontWeight: '700', marginBottom: '4px' }}>
                        Grand Total
                      </span>
                      <strong style={{ fontSize: '1.2rem', color: 'var(--primary-color, #6b21a8)' }}>
                        ₹{parseFloat(selectedOrder.amounts?.grand_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                      </strong>
                    </div>
                  </div>

                  {/* Customer & Address Row */}
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '20px' }}>
                    {/* Customer Info */}
                    <div style={{ padding: '18px 20px', borderRadius: '10px', border: '1px solid #e5e7eb', background: '#fff' }}>
                      <h4 style={{ margin: '0 0 12px 0', fontSize: '0.9rem', fontWeight: '700', color: '#111827', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                        Customer Information
                      </h4>
                      <div style={{ fontSize: '0.88rem', display: 'flex', flexDirection: 'column', gap: '8px', lineHeight: '1.5' }}>
                        <div><strong>Name:</strong> {selectedOrder.user?.name}</div>
                        <div><strong>Mobile:</strong> +{selectedOrder.user?.mobile}</div>
                        <div><strong>Email:</strong> {selectedOrder.user?.email || 'Not provided'}</div>
                        {selectedOrder.user?.date_of_birth && (
                          <div><strong>DOB:</strong> {selectedOrder.user.date_of_birth}</div>
                        )}
                        <div><strong>Account Status:</strong> <span className={`admin-badge ${selectedOrder.user?.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>{selectedOrder.user?.status}</span></div>
                      </div>
                    </div>

                    {/* Delivery Address */}
                    <div style={{ padding: '18px 20px', borderRadius: '10px', border: '1px solid #e5e7eb', background: '#fff' }}>
                      <h4 style={{ margin: '0 0 12px 0', fontSize: '0.9rem', fontWeight: '700', color: '#111827', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                        Shipping Address
                      </h4>
                      {selectedOrder.address ? (
                        <div style={{ fontSize: '0.88rem', color: '#374151', lineHeight: '1.6' }}>
                          <span style={{ fontSize: '0.72rem', background: '#e0e7ff', color: '#3730a3', padding: '2px 8px', borderRadius: '4px', fontWeight: '700', textTransform: 'uppercase', display: 'inline-block', marginBottom: '6px' }}>
                            {selectedOrder.address.address_type || 'HOME'}
                          </span>
                          <div>
                            {selectedOrder.address.door_no}, {selectedOrder.address.street}
                          </div>
                          <div>{selectedOrder.address.area}, {selectedOrder.address.city}</div>
                          <div>{selectedOrder.address.state} - <strong>{selectedOrder.address.pincode}</strong></div>
                          {selectedOrder.address.landmark && (
                            <div style={{ color: '#6b7280', fontSize: '0.82rem', marginTop: '6px' }}>
                              Landmark: {selectedOrder.address.landmark}
                            </div>
                          )}
                        </div>
                      ) : (
                        <span style={{ color: '#9ca3af', fontSize: '0.85rem' }}>No delivery address recorded.</span>
                      )}
                    </div>
                  </div>

                  {/* Ordered Items Table */}
                  <div>
                    <h4 style={{ margin: '0 0 12px 0', fontSize: '0.95rem', fontWeight: '700', color: '#111827' }}>
                      Ordered Items ({selectedOrder.items?.length || 0})
                    </h4>
                    <div style={{ border: '1px solid #e5e7eb', borderRadius: '10px', overflow: 'hidden' }}>
                      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.86rem' }}>
                        <thead>
                          <tr style={{ background: '#f9fafb', borderBottom: '1px solid #e5e7eb', color: '#4b5563', textAlign: 'left' }}>
                            <th style={{ padding: '12px 16px' }}>Product & Variant</th>
                            <th style={{ padding: '12px 16px' }}>SKU / HSN</th>
                            <th style={{ padding: '12px 16px' }}>Unit Price</th>
                            <th style={{ padding: '12px 16px' }}>Qty</th>
                            <th style={{ padding: '12px 16px' }}>GST Tax</th>
                            <th style={{ padding: '12px 16px', textAlign: 'right' }}>Line Total</th>
                          </tr>
                        </thead>
                        <tbody>
                          {selectedOrder.items?.map((rawItem, idx) => {
                            const item = formatOrderItem(rawItem);
                            return (
                              <tr key={idx} style={{ borderBottom: '1px solid #f3f4f6' }}>
                                <td style={{ padding: '14px 16px' }}>
                                  <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                    {item.imageUrl ? (
                                      <img
                                        src={item.imageUrl}
                                        alt={item.productName}
                                        style={{ width: '46px', height: '46px', borderRadius: '6px', objectFit: 'cover', background: '#f3f4f6' }}
                                      />
                                    ) : (
                                      <div style={{ width: '46px', height: '46px', borderRadius: '6px', background: '#f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9ca3af', fontSize: '10px' }}>
                                        No Img
                                      </div>
                                    )}
                                    <div>
                                      <div style={{ fontWeight: '700', color: '#111827' }}>{item.productName}</div>
                                      {item.categoryName && (
                                        <span style={{ fontSize: '0.72rem', color: 'var(--primary-color, #6b21a8)', fontWeight: '600', display: 'block' }}>
                                          {item.categoryName}
                                        </span>
                                      )}
                                      <div style={{ fontSize: '0.78rem', color: '#6b7280', marginTop: '2px' }}>
                                        {[
                                          item.variantName ? item.variantName : null,
                                          item.sizeName ? `Size: ${item.sizeName}` : null,
                                          item.colorName ? `Color: ${item.colorName}` : null
                                        ].filter(Boolean).join(' • ') || '-'}
                                      </div>
                                    </div>
                                  </div>
                                </td>
                                <td style={{ padding: '14px 16px', fontSize: '0.8rem', color: '#6b7280' }}>
                                  <div><strong>SKU:</strong> {item.sku}</div>
                                  <div style={{ marginTop: '2px' }}><strong>HSN:</strong> {item.hsnCode}</div>
                                </td>
                                <td style={{ padding: '14px 16px', fontWeight: '600' }}>
                                  <div>₹{item.sellingPrice.toFixed(2)}</div>
                                  {item.originalPrice && item.originalPrice > item.sellingPrice && (
                                    <div style={{ fontSize: '0.75rem', color: '#9ca3af', textDecoration: 'line-through' }}>
                                      ₹{item.originalPrice.toFixed(2)}
                                    </div>
                                  )}
                                </td>
                                <td style={{ padding: '14px 16px', fontWeight: '700', fontSize: '0.95rem' }}>
                                  {item.quantity}
                                </td>
                                <td style={{ padding: '14px 16px', fontSize: '0.8rem', color: '#4b5563' }}>
                                  <div>Rate: {item.gstRate}%</div>
                                  {item.cgstAmount > 0 || item.sgstAmount > 0 ? (
                                    <div style={{ color: '#6b7280', marginTop: '2px', fontSize: '0.75rem' }}>
                                      CGST: ₹{item.cgstAmount.toFixed(2)} | SGST: ₹{item.sgstAmount.toFixed(2)}
                                    </div>
                                  ) : item.igstAmount > 0 || item.taxAmount > 0 ? (
                                    <div style={{ color: '#6b7280', marginTop: '2px', fontSize: '0.75rem' }}>
                                      IGST: ₹{(item.igstAmount || item.taxAmount).toFixed(2)}
                                    </div>
                                  ) : (
                                    <div style={{ color: '#9ca3af', marginTop: '2px', fontSize: '0.75rem' }}>₹0.00</div>
                                  )}
                                </td>
                                <td style={{ padding: '14px 16px', textAlign: 'right', fontWeight: '800', color: '#111827', fontSize: '0.95rem' }}>
                                  ₹{item.lineTotal.toFixed(2)}
                                </td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    </div>
                  </div>

                  {/* Financial Breakdown & Coupon Snapshot */}
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '20px' }}>
                    {/* Coupon / Notes Card */}
                    <div style={{ padding: '18px 20px', borderRadius: '10px', border: '1px solid #e5e7eb', background: '#fff' }}>
                      <h4 style={{ margin: '0 0 12px 0', fontSize: '0.9rem', fontWeight: '700', color: '#111827', textTransform: 'uppercase' }}>
                        Coupons & Notes
                      </h4>
                      {selectedOrder.coupon ? (
                        <div style={{ padding: '12px 14px', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '8px', marginBottom: '12px', fontSize: '0.86rem' }}>
                          <span style={{ fontSize: '0.72rem', fontWeight: '700', color: '#166534', display: 'block', marginBottom: '2px' }}>COUPON APPLIED</span>
                          <strong style={{ color: 'var(--primary-color, #6b21a8)' }}>
                            {selectedOrder.coupon.order_snapshot_code || selectedOrder.coupon.code || selectedOrder.coupon.current_definition?.coupon_code || selectedOrder.coupon_code}
                          </strong>
                          {parseFloat(selectedOrder.amounts?.coupon_discount_amount || selectedOrder.coupon.discount_amount || 0) > 0 && (
                            <span style={{ fontSize: '0.8rem', color: '#166534', marginLeft: '6px' }}>
                              (-₹{parseFloat(selectedOrder.amounts?.coupon_discount_amount || selectedOrder.coupon.discount_amount || 0).toFixed(2)})
                            </span>
                          )}
                          {(selectedOrder.coupon.current_definition?.title || selectedOrder.coupon.title) && (
                            <div style={{ fontSize: '0.78rem', color: '#4b5563', marginTop: '4px' }}>
                              {selectedOrder.coupon.current_definition?.title || selectedOrder.coupon.title}
                            </div>
                          )}
                        </div>
                      ) : (
                        <div style={{ fontSize: '0.84rem', color: '#9ca3af', marginBottom: '12px' }}>No promotional coupon applied.</div>
                      )}

                      {selectedOrder.customer_note && (
                        <div style={{ padding: '12px 14px', background: '#fefce8', border: '1px solid #fef08a', borderRadius: '8px', fontSize: '0.84rem', color: '#713f12' }}>
                          <strong>Customer Note:</strong> {selectedOrder.customer_note}
                        </div>
                      )}
                    </div>

                    {/* Ledger / Totals Card */}
                    <div style={{ padding: '18px 20px', borderRadius: '10px', border: '1px solid #e5e7eb', background: '#f9fafb' }}>
                      <h4 style={{ margin: '0 0 12px 0', fontSize: '0.9rem', fontWeight: '700', color: '#111827', textTransform: 'uppercase' }}>
                        Financial Ledger
                      </h4>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', fontSize: '0.88rem' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', color: '#4b5563' }}>
                          <span>Subtotal</span>
                          <span>₹{parseFloat(selectedOrder.amounts?.subtotal || 0).toFixed(2)}</span>
                        </div>
                        {parseFloat(selectedOrder.amounts?.product_discount_amount || 0) > 0 && (
                          <div style={{ display: 'flex', justifyContent: 'space-between', color: '#16a34a' }}>
                            <span>Product Discounts</span>
                            <span>-₹{parseFloat(selectedOrder.amounts?.product_discount_amount).toFixed(2)}</span>
                          </div>
                        )}
                        {parseFloat(selectedOrder.amounts?.coupon_discount_amount || 0) > 0 && (
                          <div style={{ display: 'flex', justifyContent: 'space-between', color: '#16a34a' }}>
                            <span>Coupon Savings</span>
                            <span>-₹{parseFloat(selectedOrder.amounts?.coupon_discount_amount).toFixed(2)}</span>
                          </div>
                        )}
                        <div style={{ display: 'flex', justifyContent: 'space-between', color: '#4b5563' }}>
                          <span>GST / Taxes</span>
                          <span>₹{parseFloat(selectedOrder.amounts?.tax_amount || 0).toFixed(2)}</span>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', color: '#4b5563' }}>
                          <span>Shipping Fee</span>
                          <span>{parseFloat(selectedOrder.amounts?.shipping_charge || 0) > 0 ? `₹${parseFloat(selectedOrder.amounts?.shipping_charge).toFixed(2)}` : 'FREE'}</span>
                        </div>
                        {parseFloat(selectedOrder.amounts?.cod_charge || 0) > 0 && (
                          <div style={{ display: 'flex', justifyContent: 'space-between', color: '#b45309' }}>
                            <span>COD Handling Charge</span>
                            <span>+₹{parseFloat(selectedOrder.amounts?.cod_charge).toFixed(2)}</span>
                          </div>
                        )}
                        <div style={{ borderTop: '1px solid #e5e7eb', paddingTop: '10px', marginTop: '6px', display: 'flex', justifyContent: 'space-between', fontSize: '1.05rem', fontWeight: '800', color: '#111827' }}>
                          <span>Grand Total</span>
                          <span style={{ color: 'var(--primary-color, #6b21a8)' }}>
                            ₹{parseFloat(selectedOrder.amounts?.grand_total || 0).toFixed(2)}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Order Timeline */}
                  {selectedOrder.timeline && (
                    <div style={{ padding: '16px 20px', background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', fontSize: '0.82rem' }}>
                      <strong style={{ color: '#111827', display: 'block', marginBottom: '10px', textTransform: 'uppercase' }}>
                        Order Audit Timeline
                      </strong>
                      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '12px', color: '#6b7280' }}>
                        <div><strong>Created:</strong> {selectedOrder.timeline.created_at || '-'}</div>
                        <div><strong>Placed:</strong> {selectedOrder.timeline.placed_at || '-'}</div>
                        <div><strong>Confirmed:</strong> {selectedOrder.timeline.confirmed_at || '-'}</div>
                        <div><strong>Delivered:</strong> {selectedOrder.timeline.delivered_at || '-'}</div>
                        {selectedOrder.timeline.cancelled_at && (
                          <div style={{ color: '#dc2626' }}><strong>Cancelled:</strong> {selectedOrder.timeline.cancelled_at}</div>
                        )}
                      </div>
                      {selectedOrder.cancel_reason && (
                        <div style={{ marginTop: '10px', padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '6px', color: '#991b1b', fontSize: '0.82rem' }}>
                          <strong>Cancellation Reason:</strong> {selectedOrder.cancel_reason}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              ) : (
                <div style={{ padding: '40px', textAlign: 'center', color: '#ef4444' }}>
                  Unable to load order details.
                </div>
              )}
            </div>

            <div className="admin-modal-footer" style={{ borderTop: '1px solid #e5e7eb', padding: '18px 28px', display: 'flex', justifyContent: 'flex-end' }}>
              <button type="button" onClick={closeViewModal} className="admin-btn admin-btn-secondary">
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========== ORDER ADDRESS MODAL ========== */}
      {isAddressModalOpen && selectedOrderAddress && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '500px' }}>
            <div className="admin-modal-header">
              <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#111827', fontWeight: '700' }}>
                Delivery Address: #{selectedOrderAddress.order_number}
              </h2>
              <button type="button" onClick={closeAddressModal} className="admin-modal-close" title="Close" aria-label="Close">&times;</button>
            </div>

            <div className="admin-modal-body">
              {selectedOrderAddress.address ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '14px', fontSize: '0.9rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <span style={{ fontSize: '0.75rem', background: '#e0e7ff', color: '#3730a3', padding: '2px 8px', borderRadius: '4px', fontWeight: '700', textTransform: 'uppercase' }}>
                      {selectedOrderAddress.address.address_type || 'HOME'}
                    </span>
                    <strong style={{ color: '#111827' }}>{selectedOrderAddress.customer_name}</strong>
                  </div>

                  <div style={{ background: '#f9fafb', padding: '16px', borderRadius: '8px', border: '1px solid #e5e7eb', lineHeight: '1.6' }}>
                    <div><strong>Door / Flat:</strong> {selectedOrderAddress.address.door_no || '-'}</div>
                    <div><strong>Street:</strong> {selectedOrderAddress.address.street || '-'}</div>
                    <div><strong>Area:</strong> {selectedOrderAddress.address.area || '-'}</div>
                    <div><strong>City:</strong> {selectedOrderAddress.address.city || '-'}</div>
                    <div><strong>District:</strong> {selectedOrderAddress.address.district || '-'}</div>
                    <div><strong>State:</strong> {selectedOrderAddress.address.state || '-'}</div>
                    <div><strong>Pincode:</strong> <strong>{selectedOrderAddress.address.pincode || '-'}</strong></div>
                    {selectedOrderAddress.address.landmark && (
                      <div style={{ marginTop: '6px', color: '#6b7280' }}>
                        <strong>Landmark:</strong> {selectedOrderAddress.address.landmark}
                      </div>
                    )}
                  </div>
                </div>
              ) : (
                <div style={{ padding: '20px', textAlign: 'center', color: '#9ca3af' }}>
                  No delivery address recorded for this order.
                </div>
              )}
            </div>

            <div className="admin-modal-footer">
              <button type="button" onClick={closeAddressModal} className="admin-btn admin-btn-secondary">
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========== ORDER STATUS UPDATE MODAL ========== */}
      {isStatusModalOpen && statusOrder && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '520px' }}>
            <div className="admin-modal-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid #e5e7eb', padding: '18px 24px' }}>
              <div>
                <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#111827', fontWeight: '800' }}>
                  Update Order Status
                </h2>
                <span style={{ fontSize: '0.82rem', color: '#6b7280' }}>
                  Order #{statusOrder.order_number} • Customer: {statusOrder.user?.name || 'Customer'}
                </span>
              </div>
              <button
                type="button"
                onClick={closeStatusModal}
                className="admin-modal-close"
                title="Close"
                aria-label="Close"
                disabled={isUpdatingStatus}
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleStatusSubmit}>
              <div className="admin-modal-body" style={{ padding: '24px' }}>
                {/* Current Status Indicator */}
                <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: '8px', padding: '12px 16px', marginBottom: '20px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                  <span style={{ fontSize: '0.85rem', color: '#4b5563', fontWeight: '600' }}>Current Status:</span>
                  <span className={`admin-badge ${getOrderStatusBadge(statusOrder.order_status)}`} style={{ textTransform: 'uppercase', padding: '4px 10px', fontSize: '0.78rem', borderRadius: '12px' }}>
                    {statusOrder.order_status}
                  </span>
                </div>

                {/* Target Status Selection */}
                <div style={{ marginBottom: '20px' }}>
                  <label className="admin-label" style={{ display: 'block', marginBottom: '8px', fontWeight: '700', fontSize: '0.88rem', color: '#374151' }}>
                    Select New Status <span style={{ color: '#ef4444' }}>*</span>
                  </label>

                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '10px' }}>
                    {[
                      { value: 'confirmed', label: 'Confirmed', desc: 'Order confirmed & accepted', color: '#059669', bg: '#ecfdf5' },
                      { value: 'shipped', label: 'Shipped', desc: 'Dispatched with courier', color: '#0284c7', bg: '#f0f9ff' },
                      { value: 'delivered', label: 'Delivered', desc: 'Handed over to customer', color: '#16a34a', bg: '#f0fdf4' },
                      { value: 'cancelled', label: 'Cancelled', desc: 'Revoke order & restock', color: '#dc2626', bg: '#fef2f2' }
                    ].map(opt => {
                      const isSelected = selectedTargetStatus === opt.value;
                      const isCurrent = statusOrder.order_status === opt.value;
                      return (
                        <button
                          key={opt.value}
                          type="button"
                          disabled={isUpdatingStatus || isCurrent}
                          onClick={() => {
                            setSelectedTargetStatus(opt.value);
                            if (statusModalError) setStatusModalError('');
                          }}
                          style={{
                            padding: '12px 14px',
                            borderRadius: '10px',
                            border: isSelected ? `2px solid ${opt.color}` : '1.5px solid #e5e7eb',
                            background: isSelected ? opt.bg : isCurrent ? '#f3f4f6' : '#ffffff',
                            textAlign: 'left',
                            cursor: isCurrent ? 'not-allowed' : 'pointer',
                            opacity: isCurrent ? 0.6 : 1,
                            position: 'relative',
                            transition: 'all 0.15s ease'
                          }}
                        >
                          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '4px' }}>
                            <strong style={{ fontSize: '0.92rem', color: isSelected ? opt.color : '#1f2937' }}>
                              {opt.label}
                            </strong>
                            {isSelected && (
                              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={opt.color} strokeWidth="3">
                                <polyline points="20 6 9 17 4 12"></polyline>
                              </svg>
                            )}
                            {isCurrent && (
                              <span style={{ fontSize: '0.65rem', background: '#d1d5db', color: '#374151', padding: '1px 6px', borderRadius: '4px', textTransform: 'uppercase' }}>
                                Current
                              </span>
                            )}
                          </div>
                          <span style={{ fontSize: '0.75rem', color: '#6b7280', display: 'block', lineHeight: '1.3' }}>
                            {opt.desc}
                          </span>
                        </button>
                      );
                    })}
                  </div>
                </div>

                {/* Cancellation Reason (shown when Cancelled is selected) */}
                {selectedTargetStatus === 'cancelled' && (
                  <div style={{ marginBottom: '16px' }}>
                    <label htmlFor="cancel-reason-input" className="admin-label" style={{ display: 'block', marginBottom: '6px', fontWeight: '700', fontSize: '0.85rem', color: '#374151' }}>
                      Cancellation Reason <span style={{ color: '#6b7280', fontWeight: 'normal' }}>(Optional)</span>
                    </label>
                    <textarea
                      id="cancel-reason-input"
                      rows={3}
                      className="admin-input"
                      value={statusCancelReason}
                      onChange={(e) => setStatusCancelReason(e.target.value)}
                      placeholder="e.g. Customer requested cancellation, out of fabric/stock, unreachable address"
                      maxLength={500}
                      style={{ width: '100%', resize: 'vertical', fontSize: '0.88rem', padding: '10px 12px' }}
                      disabled={isUpdatingStatus}
                    />
                    <span style={{ fontSize: '0.74rem', color: '#6b7280', display: 'block', marginTop: '4px' }}>
                      If left blank, a standard note "Cancelled by administrator" will be recorded.
                    </span>
                  </div>
                )}

                {/* Status Modal Error */}
                {statusModalError && (
                  <div style={{ background: '#fee2e2', color: '#991b1b', border: '1px solid #fecaca', padding: '10px 14px', borderRadius: '8px', fontSize: '0.84rem', display: 'flex', alignItems: 'flex-start', gap: '8px' }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, marginTop: '2px' }}>
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="8" x2="12" y2="12"></line>
                      <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <span>{statusModalError}</span>
                  </div>
                )}
              </div>

              <div className="admin-modal-footer" style={{ borderTop: '1px solid #e5e7eb', padding: '16px 24px', display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                <button
                  type="button"
                  onClick={closeStatusModal}
                  className="admin-btn admin-btn-secondary"
                  disabled={isUpdatingStatus}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="admin-btn admin-btn-primary"
                  disabled={isUpdatingStatus || !selectedTargetStatus || selectedTargetStatus === statusOrder.order_status}
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
                >
                  {isUpdatingStatus ? (
                    <>
                      <div className="spinner" style={{ width: '14px', height: '14px', margin: 0 }}></div>
                      <span>Updating Status...</span>
                    </>
                  ) : (
                    <span>Confirm Status Change</span>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminOrders;
