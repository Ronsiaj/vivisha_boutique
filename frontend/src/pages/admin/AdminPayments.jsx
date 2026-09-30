import React, { useState, useEffect, useCallback } from 'react';
import Select from 'react-select';
import AsyncSelect from 'react-select/async';
import { useAdminAuth } from '../../context/AuthContext.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';
import AdminKpiCard from '../../components/admin/AdminKpiCard.jsx';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const STATUS_CONFIG = {
  paid: {
    label: 'Paid',
    badgeClass: 'admin-badge-active',
    bg: '#dcfce7',
    color: '#15803d',
    borderColor: '#bbf7d0'
  },
  pending: {
    label: 'Pending',
    badgeClass: 'admin-badge-warning',
    bg: '#fef3c7',
    color: '#92400e',
    borderColor: '#fde68a'
  },
  initiated: {
    label: 'Initiated',
    badgeClass: 'admin-badge-info',
    bg: '#e0f2fe',
    color: '#0369a1',
    borderColor: '#bae6fd'
  },
  created: {
    label: 'Created',
    badgeClass: 'admin-badge-info',
    bg: '#f0f9ff',
    color: '#0284c7',
    borderColor: '#e0f2fe'
  },
  authorized: {
    label: 'Authorized',
    badgeClass: 'admin-badge-info',
    bg: '#ede9fe',
    color: '#6d28d9',
    borderColor: '#ddd6fe'
  },
  failed: {
    label: 'Failed',
    badgeClass: 'admin-badge-danger',
    bg: '#fee2e2',
    color: '#b91c1c',
    borderColor: '#fecaca'
  },
  cancelled: {
    label: 'Cancelled',
    badgeClass: 'admin-badge-secondary',
    bg: '#f3f4f6',
    color: '#4b5563',
    borderColor: '#e5e7eb'
  },
  refunded: {
    label: 'Refunded',
    badgeClass: 'admin-badge-secondary',
    bg: '#f5f3ff',
    color: '#5b21b6',
    borderColor: '#ddd6fe'
  },
  partially_refunded: {
    label: 'Partially Refunded',
    badgeClass: 'admin-badge-warning',
    bg: '#fffbeb',
    color: '#b45309',
    borderColor: '#fde68a'
  }
};

const METHOD_LABELS = {
  cod: 'Cash on Delivery',
  upi: 'UPI',
  card: 'Credit / Debit Card',
  netbanking: 'Net Banking',
  wallet: 'Wallet',
  emi: 'EMI',
  paylater: 'Pay Later',
  other: 'Other'
};

const PROVIDER_LABELS = {
  cod: 'COD',
  razorpay: 'Razorpay'
};

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'paid', label: 'Paid' },
  { value: 'pending', label: 'Pending' },
  { value: 'initiated', label: 'Initiated' },
  { value: 'failed', label: 'Failed' },
  { value: 'refunded', label: 'Refunded' },
  { value: 'partially_refunded', label: 'Partially Refunded' }
];

const PROVIDER_OPTIONS = [
  { value: '', label: 'All Providers' },
  { value: 'razorpay', label: 'Razorpay Gateway' },
  { value: 'cod', label: 'Cash on Delivery (COD)' }
];

const METHOD_OPTIONS = [
  { value: '', label: 'All Methods' },
  { value: 'upi', label: 'UPI' },
  { value: 'card', label: 'Credit / Debit Card' },
  { value: 'netbanking', label: 'Net Banking' },
  { value: 'wallet', label: 'Wallet' },
  { value: 'cod', label: 'COD' },
  { value: 'emi', label: 'EMI' },
  { value: 'paylater', label: 'Pay Later' },
  { value: 'other', label: 'Other' }
];

const SORT_OPTIONS = [
  { value: 'created_at', label: 'Date Created' },
  { value: 'paid_at', label: 'Date Paid' },
  { value: 'amount', label: 'Amount' },
  { value: 'status', label: 'Status' },
  { value: 'attempt_no', label: 'Attempt #' }
];

const customSelectStyles = {
  control: (base, state) => ({
    ...base,
    borderColor: state.isFocused ? 'var(--primary-color, #A049A3)' : '#d1d5db',
    boxShadow: state.isFocused ? '0 0 0 1px var(--primary-color, #A049A3)' : 'none',
    borderRadius: '8px',
    fontSize: '0.86rem',
    minHeight: '40px',
    backgroundColor: '#fff',
    cursor: 'pointer',
    '&:hover': {
      borderColor: state.isFocused ? 'var(--primary-color, #A049A3)' : '#9ca3af'
    }
  }),
  valueContainer: (base) => ({
    ...base,
    padding: '2px 10px'
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
    fontSize: '0.86rem'
  }),
  menuPortal: (base) => ({
    ...base,
    zIndex: 99999
  }),
  option: (base, state) => ({
    ...base,
    fontSize: '0.85rem',
    backgroundColor: state.isSelected
      ? 'var(--primary-color, #A049A3)'
      : state.isFocused
      ? '#f3e8ff'
      : 'transparent',
    color: state.isSelected ? '#fff' : '#1f2937',
    cursor: 'pointer'
  })
};

const AdminPayments = () => {
  const { token } = useAdminAuth();

  // Data states
  const [payments, setPayments] = useState([]);
  const [summary, setSummary] = useState(null);
  const [isInitialLoading, setIsInitialLoading] = useState(true);
  const [isFetching, setIsFetching] = useState(false);
  const [error, setError] = useState('');

  // Filter states
  const [searchQuery, setSearchQuery] = useState('');
  const [searchInputValue, setSearchInputValue] = useState('');
  const [selectedSearchOption, setSelectedSearchOption] = useState(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [providerFilter, setProviderFilter] = useState('');
  const [methodFilter, setMethodFilter] = useState('');
  const [sortBy, setSortBy] = useState('created_at');
  const [sortOrder, setSortOrder] = useState('desc');
  const [page, setPage] = useState(1);
  const [limit] = useState(15);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);

  // View Modal state
  const [selectedPaymentId, setSelectedPaymentId] = useState(null);
  const [paymentDetails, setPaymentDetails] = useState(null);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isViewLoading, setIsViewLoading] = useState(false);
  const [viewError, setViewError] = useState('');
  const [showRawGatewayResponse, setShowRawGatewayResponse] = useState(false);

  // Fetch payments list using /payments/list.php
  const fetchPayments = useCallback(async () => {
    if (!token) return;
    setIsFetching(true);
    setError('');

    try {
      const params = new URLSearchParams();
      if (searchQuery.trim()) params.append('q', searchQuery.trim());
      if (statusFilter) params.append('status', statusFilter);
      if (providerFilter) params.append('provider', providerFilter);
      if (methodFilter) params.append('payment_method', methodFilter);
      params.append('page', String(page));
      params.append('limit', String(limit));
      params.append('sort_by', sortBy);
      params.append('sort_order', sortOrder);

      const response = await fetch(`${API_BASE_URL}/payments/list.php?${params.toString()}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      const result = await response.json();

      if (result.status && result.data) {
        setPayments(result.data.payments || []);
        setSummary(result.data.summary || null);
        if (result.data.pagination) {
          setTotalPages(result.data.pagination.total_pages || 1);
          setTotalRecords(result.data.pagination.total_records || 0);
        }
      } else {
        setPayments([]);
        setError(result.message || 'Failed to retrieve payments.');
      }
    } catch (err) {
      console.error('Error fetching payments:', err);
      setError('An error occurred while connecting to payment service.');
    } finally {
      setIsInitialLoading(false);
      setIsFetching(false);
    }
  }, [token, searchQuery, statusFilter, providerFilter, methodFilter, page, limit, sortBy, sortOrder]);

  useEffect(() => {
    fetchPayments();
  }, [fetchPayments]);

  // Load payment options dynamically for React-Select Search
  const loadPaymentOptions = async (inputValue) => {
    if (!inputValue || !inputValue.trim()) return [];
    try {
      const response = await fetch(
        `${API_BASE_URL}/payments/list.php?q=${encodeURIComponent(inputValue.trim())}&limit=10`,
        { headers: { 'Authorization': `Bearer ${token}` } }
      );
      const result = await response.json();
      if (result.status && result.data?.payments) {
        return result.data.payments.map((p) => ({
          value: p.order?.order_number || p.id.toString(),
          label: `#${p.id} • Order #${p.order?.order_number || p.order_id} • ${p.user?.name || 'Customer'} (₹${parseFloat(p.amount || 0).toFixed(0)})`,
          payment: p
        }));
      }
      return [];
    } catch (err) {
      console.error('Error loading payment options:', err);
      return [];
    }
  };

  const handleSearchSelectChange = (selectedOption) => {
    setSelectedSearchOption(selectedOption);
    if (selectedOption) {
      setSearchQuery(selectedOption.value);
      setSearchInputValue(selectedOption.value);
    } else {
      setSearchQuery('');
      setSearchInputValue('');
    }
    setPage(1);
  };

  const handleSearchSubmit = (e) => {
    if (e) e.preventDefault();
    setSearchQuery(searchInputValue.trim());
    setPage(1);
  };

  // Clear all filters
  const handleClearFilters = () => {
    setSelectedSearchOption(null);
    setSearchInputValue('');
    setSearchQuery('');
    setStatusFilter('');
    setProviderFilter('');
    setMethodFilter('');
    setSortBy('created_at');
    setSortOrder('desc');
    setPage(1);
  };

  // Open Payment Details Modal using /payments/view.php
  const handleOpenView = async (id) => {
    setSelectedPaymentId(id);
    setIsViewModalOpen(true);
    setIsViewLoading(true);
    setViewError('');
    setPaymentDetails(null);
    setShowRawGatewayResponse(false);

    try {
      const response = await fetch(`${API_BASE_URL}/payments/view.php?id=${id}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const result = await response.json();

      if (result.status && result.data?.payment) {
        setPaymentDetails(result.data.payment);
      } else {
        setViewError(result.message || 'Failed to load transaction details.');
      }
    } catch (err) {
      console.error('Error fetching payment view:', err);
      setViewError('Network error while retrieving transaction details.');
    } finally {
      setIsViewLoading(false);
    }
  };

  const handleCloseView = () => {
    setIsViewModalOpen(false);
    setSelectedPaymentId(null);
    setPaymentDetails(null);
    setViewError('');
  };

  const hasActiveFilters = Boolean(searchQuery || selectedSearchOption || statusFilter || providerFilter || methodFilter || sortBy !== 'created_at' || sortOrder !== 'desc');

  return (
    <div className="admin-page-container">
      {/* Page Header */}
      <div className="admin-header-row" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, fontSize: '1.75rem', fontWeight: '800', color: '#111827' }}>
            Payment Transactions
          </h1>
          <p style={{ margin: '4px 0 0 0', color: '#6b7280', fontSize: '0.9rem' }}>
            Audit payment receipts, track gateway authorizations, inspect settlement status, and diagnose failed transactions.
          </p>
        </div>

        <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
          <button
            type="button"
            onClick={fetchPayments}
            className="admin-btn admin-btn-secondary"
            style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
            </svg>
            Refresh
          </button>
        </div>
      </div>

      {/* Error Banner */}
      {error && (
        <div style={{ background: '#fee2e2', color: '#991b1b', border: '1px solid #fecaca', padding: '12px 18px', borderRadius: '8px', marginBottom: '20px', fontSize: '0.9rem', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span><strong>Notice:</strong> {error}</span>
          </div>
          <button
            type="button"
            onClick={() => setError('')}
            style={{ background: 'none', border: 'none', color: '#991b1b', cursor: 'pointer', fontSize: '1.2rem', fontWeight: 'bold' }}
          >
            &times;
          </button>
        </div>
      )}

      {/* KPI Cards */}
      {summary && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px', marginBottom: '28px' }}>
          <AdminKpiCard
            variant="blue"
            title="Total Paid Volume"
            value={`₹${parseFloat(summary.paid_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`}
            badgeText="Settled"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
              </svg>
            }
            footerLeft={<span>{summary.status_counts?.paid || 0} Successful Payouts</span>}
            footerRight={<span>Boutique Sales</span>}
          />

          <AdminKpiCard
            variant="pink"
            title="Total Transactions"
            value={summary.total_payments?.toLocaleString('en-IN') || '0'}
            badgeText="All Attempts"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                <line x1="1" y1="10" x2="23" y2="10"></line>
              </svg>
            }
            footerLeft={<span>Volume Total</span>}
            footerRight={<span>₹{parseFloat(summary.total_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>}
          />

          <AdminKpiCard
            variant="purple"
            title="Online vs COD"
            value={`₹${parseFloat(summary.online_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 0 })}`}
            badgeText="Online Share"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="2" y1="12" x2="22" y2="12"></line>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
              </svg>
            }
            footerLeft={<span>COD: ₹{parseFloat(summary.cod_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 0 })}</span>}
            footerRight={<span>Gateway Fee: ₹{parseFloat(summary.total_gateway_fee || 0).toFixed(2)}</span>}
          />

          <AdminKpiCard
            variant="orange"
            title="Failed / Pending"
            value={(summary.status_counts?.failed || 0) + (summary.status_counts?.pending || 0)}
            badgeText="Needs Attention"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
            }
            footerLeft={<span>Failed: {summary.status_counts?.failed || 0}</span>}
            footerRight={<span>₹{parseFloat(summary.failed_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>}
          />
        </div>
      )}

      {/* Main Table Card */}
      <div className="admin-card" style={{ padding: '24px', borderRadius: '14px', background: '#fff', boxShadow: '0 2px 10px rgba(0,0,0,0.03)' }}>
        
        {/* Status Filter Tabs */}
        <div style={{ display: 'flex', gap: '8px', borderBottom: '1px solid #e5e7eb', paddingBottom: '14px', marginBottom: '22px', overflowX: 'auto' }}>
          {[
            { id: '', label: 'All Transactions', count: summary?.total_payments },
            { id: 'paid', label: 'Paid', count: summary?.status_counts?.paid },
            { id: 'pending', label: 'Pending', count: summary?.status_counts?.pending },
            { id: 'initiated', label: 'Initiated', count: summary?.status_counts?.initiated },
            { id: 'failed', label: 'Failed', count: summary?.status_counts?.failed },
            { id: 'refunded', label: 'Refunded' },
            { id: 'partially_refunded', label: 'Partially Refunded' }
          ].map((tab) => {
            const isActive = statusFilter === tab.id;
            return (
              <button
                key={tab.id || 'all'}
                type="button"
                onClick={() => {
                  setStatusFilter(tab.id);
                  setPage(1);
                }}
                style={{
                  padding: '7px 16px',
                  borderRadius: '20px',
                  border: isActive ? '1px solid #A049A3' : '1px solid #e5e7eb',
                  background: isActive ? '#A049A3' : '#fff',
                  color: isActive ? '#fff' : '#4b5563',
                  fontSize: '0.85rem',
                  fontWeight: isActive ? '700' : '500',
                  cursor: 'pointer',
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '6px',
                  whiteSpace: 'nowrap',
                  transition: 'all 0.15s ease'
                }}
              >
                <span>{tab.label}</span>
                {tab.count !== undefined && (
                  <span style={{
                    fontSize: '0.74rem',
                    padding: '1px 6px',
                    borderRadius: '10px',
                    background: isActive ? 'rgba(255,255,255,0.25)' : '#f3f4f6',
                    color: isActive ? '#fff' : '#6b7280'
                  }}>
                    {tab.count}
                  </span>
                )}
              </button>
            );
          })}
        </div>

        {/* 1. REACT SELECT SEARCH FOR PAYMENTS */}
        <div style={{ marginBottom: '20px' }}>
          <label className="admin-label" style={{ marginBottom: '8px', display: 'block', fontWeight: '700', fontSize: '0.86rem', color: '#374151' }}>
            Search Payments (React Select)
          </label>
          <form onSubmit={handleSearchSubmit} style={{ display: 'flex', gap: '10px', maxWidth: '800px', width: '100%', alignItems: 'center' }}>
            <div style={{ flex: 1 }}>
              <AsyncSelect
                cacheOptions
                defaultOptions={false}
                loadOptions={loadPaymentOptions}
                value={selectedSearchOption}
                onInputChange={(val, action) => {
                  if (action.action === 'input-change') {
                    setSearchInputValue(val);
                  }
                }}
                onChange={handleSearchSelectChange}
                placeholder="Search payments by ID, Order #, Razorpay ID, customer..."
                isClearable
                noOptionsMessage={({ inputValue }) =>
                  !inputValue ? 'Type transaction ID, Order #, or customer name...' : 'No matching transactions found'
                }
                styles={customSelectStyles}
              />
            </div>
            <button
              type="submit"
              className="admin-btn admin-btn-primary"
              style={{
                height: '40px',
                padding: '0 20px',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '8px',
                background: 'linear-gradient(135deg, #A049A3 0%, #C86395 100%)',
                color: '#fff',
                fontWeight: '600',
                border: 'none',
                borderRadius: '8px',
                whiteSpace: 'nowrap',
                cursor: 'pointer'
              }}
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
              Search
            </button>
          </form>
        </div>

        {/* 2. REACT SELECT FILTER DROPDOWNS ROW */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '14px', marginBottom: '22px', alignItems: 'end' }}>
          
          {/* Status Filter */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Payment Status
            </label>
            <Select
              styles={customSelectStyles}
              options={STATUS_OPTIONS}
              value={STATUS_OPTIONS.find(o => o.value === statusFilter) || STATUS_OPTIONS[0]}
              onChange={(opt) => {
                setStatusFilter(opt ? opt.value : '');
                setPage(1);
              }}
              isSearchable={false}
            />
          </div>

          {/* Provider Filter */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Provider
            </label>
            <Select
              styles={customSelectStyles}
              options={PROVIDER_OPTIONS}
              value={PROVIDER_OPTIONS.find(o => o.value === providerFilter) || PROVIDER_OPTIONS[0]}
              onChange={(opt) => {
                setProviderFilter(opt ? opt.value : '');
                setPage(1);
              }}
              isSearchable={false}
            />
          </div>

          {/* Payment Method Filter */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Payment Method
            </label>
            <Select
              styles={customSelectStyles}
              options={METHOD_OPTIONS}
              value={METHOD_OPTIONS.find(o => o.value === methodFilter) || METHOD_OPTIONS[0]}
              onChange={(opt) => {
                setMethodFilter(opt ? opt.value : '');
                setPage(1);
              }}
              isSearchable={false}
            />
          </div>

          {/* Sort By & Order Toggle */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Sort Payments
            </label>
            <div style={{ display: 'flex', gap: '6px' }}>
              <div style={{ flex: 1 }}>
                <Select
                  styles={customSelectStyles}
                  options={SORT_OPTIONS}
                  value={SORT_OPTIONS.find(o => o.value === sortBy) || SORT_OPTIONS[0]}
                  onChange={(opt) => setSortBy(opt ? opt.value : 'created_at')}
                  isSearchable={false}
                />
              </div>
              <button
                type="button"
                className="admin-btn admin-btn-secondary"
                onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
                title={sortOrder === 'asc' ? 'Ascending' : 'Descending'}
                style={{ height: '40px', minWidth: '40px', padding: '0 12px', display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: '8px' }}
              >
                {sortOrder === 'asc' ? '↑' : '↓'}
              </button>
            </div>
          </div>
        </div>

        {/* Clear Filters Button if any active */}
        {hasActiveFilters && (
          <div style={{ marginBottom: '18px', display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
            <span style={{ fontSize: '0.82rem', color: '#6b7280' }}>Active filters applied:</span>
            {searchQuery && (
              <span style={{ background: '#f3e8ff', color: '#7e22ce', padding: '2px 8px', borderRadius: '12px', fontSize: '0.76rem', fontWeight: '600' }}>
                Search: "{searchQuery}"
              </span>
            )}
            {statusFilter && (
              <span style={{ background: '#e0f2fe', color: '#0369a1', padding: '2px 8px', borderRadius: '12px', fontSize: '0.76rem', fontWeight: '600', textTransform: 'capitalize' }}>
                Status: {statusFilter}
              </span>
            )}
            <button
              type="button"
              onClick={handleClearFilters}
              style={{
                background: 'none',
                border: 'none',
                color: '#e11d48',
                fontSize: '0.82rem',
                fontWeight: '600',
                cursor: 'pointer',
                textDecoration: 'underline'
              }}
            >
              Clear All Filters
            </button>
          </div>
        )}

        {/* Initial Loading Indicator */}
        {isInitialLoading && (
          <div style={{ padding: '60px 20px', textAlign: 'center', color: '#6b7280' }}>
            <div className="admin-loading-spinner" style={{ margin: '0 auto 12px' }}></div>
            <p style={{ margin: 0, fontSize: '0.9rem' }}>Loading transaction records...</p>
          </div>
        )}

        {/* Empty State */}
        {!isInitialLoading && payments.length === 0 && (
          <div style={{ padding: '60px 20px', textAlign: 'center', background: '#faf5ff', borderRadius: '12px', border: '1px dashed #d8b4fe' }}>
            <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: '#f3e8ff', color: '#9333ea', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', marginBottom: '14px' }}>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                <line x1="1" y1="10" x2="23" y2="10"></line>
              </svg>
            </div>
            <h3 style={{ margin: '0 0 6px 0', color: '#1f2937', fontSize: '1.1rem' }}>No Payment Records Found</h3>
            <p style={{ margin: '0 0 16px 0', color: '#6b7280', fontSize: '0.88rem' }}>
              {hasActiveFilters
                ? 'No transactions matched your search criteria. Try modifying your search or resetting filters.'
                : 'No payments have been processed through the store yet.'}
            </p>
            {hasActiveFilters && (
              <button
                type="button"
                className="admin-btn admin-btn-secondary"
                onClick={handleClearFilters}
              >
                Reset Filters
              </button>
            )}
          </div>
        )}

        {/* Payments Data Table */}
        {!isInitialLoading && payments.length > 0 && (
          <div className="admin-table-responsive" style={{ overflowX: 'auto', opacity: isFetching ? 0.65 : 1, transition: 'opacity 0.2s ease' }}>
            <table className="admin-table" style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
              <thead>
                <tr style={{ background: '#f9fafb', borderBottom: '2px solid #e5e7eb', color: '#4b5563', fontSize: '0.8rem', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                  <th style={{ padding: '12px 16px' }}>Transaction ID</th>
                  <th style={{ padding: '12px 16px' }}>Order Details</th>
                  <th style={{ padding: '12px 16px' }}>Customer</th>
                  <th style={{ padding: '12px 16px' }}>Amount</th>
                  <th style={{ padding: '12px 16px' }}>Method & Provider</th>
                  <th style={{ padding: '12px 16px' }}>Payment Status</th>
                  <th style={{ padding: '12px 16px', textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {payments.map((item) => {
                  const payStatus = item.status || 'pending';
                  const statusStyle = STATUS_CONFIG[payStatus] || STATUS_CONFIG.pending;

                  return (
                    <tr key={item.id} style={{ borderBottom: '1px solid #f3f4f6', transition: 'background 0.15s ease' }}>
                      
                      {/* Transaction ID & Attempt */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <span style={{ fontWeight: '800', color: '#111827', fontSize: '0.92rem' }}>
                            #{item.id}
                          </span>
                          {item.attempt_no && item.attempt_no > 1 && (
                            <span style={{
                              padding: '1px 6px',
                              borderRadius: '4px',
                              fontSize: '0.68rem',
                              fontWeight: '700',
                              background: '#fef3c7',
                              color: '#b45309'
                            }}>
                              Attempt #{item.attempt_no}
                            </span>
                          )}
                        </div>
                        <div style={{ fontSize: '0.76rem', color: '#6b7280', marginTop: '4px' }}>
                          {item.timeline?.created_at ? new Date(item.timeline.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'}
                        </div>
                        {item.gateway?.razorpay_payment_id && (
                          <div style={{ fontSize: '0.72rem', color: '#4b5563', marginTop: '3px', fontFamily: 'monospace' }} title="Razorpay Payment ID">
                            {item.gateway.razorpay_payment_id}
                          </div>
                        )}
                      </td>

                      {/* Order Details */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <div style={{ fontWeight: '700', color: '#A049A3', fontSize: '0.88rem' }}>
                          #{item.order?.order_number || item.order_id}
                        </div>
                        <div style={{ fontSize: '0.78rem', color: '#4b5563', marginTop: '2px' }}>
                          Grand Total: ₹{parseFloat(item.order?.amounts?.grand_total || item.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </div>
                        <div style={{ fontSize: '0.74rem', color: '#6b7280', marginTop: '2px' }}>
                          Order Status: <span style={{ textTransform: 'capitalize', fontWeight: '600' }}>{item.order?.order_status || '-'}</span>
                        </div>
                      </td>

                      {/* Customer */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <div style={{ fontWeight: '600', color: '#111827', fontSize: '0.88rem' }}>
                          {item.user?.name || 'Customer'}
                        </div>
                        <div style={{ fontSize: '0.78rem', color: '#6b7280', marginTop: '2px' }}>
                          {item.user?.mobile || '-'}
                        </div>
                        {item.user?.email && (
                          <div style={{ fontSize: '0.74rem', color: '#9ca3af' }}>
                            {item.user.email}
                          </div>
                        )}
                      </td>

                      {/* Amount Details */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <div style={{ fontWeight: '800', color: '#111827', fontSize: '0.98rem' }}>
                          ₹{parseFloat(item.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </div>
                        <div style={{ fontSize: '0.74rem', color: '#6b7280', marginTop: '2px' }}>
                          Currency: {item.currency || 'INR'}
                        </div>
                        {item.fee && parseFloat(item.fee) > 0 && (
                          <div style={{ fontSize: '0.72rem', color: '#6b7280' }}>
                            Fee: ₹{parseFloat(item.fee).toFixed(2)}
                          </div>
                        )}
                      </td>

                      {/* Method & Provider */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
                          <span style={{
                            padding: '3px 8px',
                            borderRadius: '4px',
                            fontSize: '0.75rem',
                            fontWeight: '700',
                            textTransform: 'uppercase',
                            background: item.provider === 'cod' ? '#fef3c7' : '#ede9fe',
                            color: item.provider === 'cod' ? '#92400e' : '#5b21b6'
                          }}>
                            {PROVIDER_LABELS[item.provider] || item.provider?.toUpperCase()}
                          </span>
                        </div>
                        <div style={{ fontSize: '0.78rem', color: '#374151', marginTop: '4px', fontWeight: '500' }}>
                          {METHOD_LABELS[item.payment_method] || item.payment_method?.toUpperCase()}
                        </div>
                        {item.gateway_payment_details?.vpa && (
                          <div style={{ fontSize: '0.72rem', color: '#6b7280', marginTop: '2px' }}>
                            VPA: {item.gateway_payment_details.vpa}
                          </div>
                        )}
                        {item.gateway_payment_details?.bank && (
                          <div style={{ fontSize: '0.72rem', color: '#6b7280', marginTop: '2px' }}>
                            Bank: {item.gateway_payment_details.bank}
                          </div>
                        )}
                      </td>

                      {/* Payment Status */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <span style={{
                          display: 'inline-block',
                          padding: '4px 10px',
                          borderRadius: '12px',
                          fontSize: '0.76rem',
                          fontWeight: '700',
                          textTransform: 'uppercase',
                          background: statusStyle.bg,
                          color: statusStyle.color,
                          border: `1px solid ${statusStyle.borderColor}`
                        }}>
                          {statusStyle.label}
                        </span>
                        {item.timeline?.paid_at && (
                          <div style={{ fontSize: '0.72rem', color: '#16a34a', marginTop: '4px' }}>
                            Paid on {new Date(item.timeline.paid_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })}
                          </div>
                        )}
                        {item.error?.description && (
                          <div style={{ fontSize: '0.72rem', color: '#dc2626', marginTop: '4px', maxWidth: '200px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }} title={item.error.description}>
                            Err: {item.error.description}
                          </div>
                        )}
                      </td>

                      {/* Actions */}
                      <td style={{ padding: '14px 16px', textAlign: 'right', verticalAlign: 'top' }}>
                        <button
                          type="button"
                          onClick={() => handleOpenView(item.id)}
                          className="admin-btn admin-btn-primary"
                          style={{ padding: '6px 12px', fontSize: '0.8rem', fontWeight: '600' }}
                        >
                          View Details
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {!isInitialLoading && totalPages > 1 && (
          <div className="admin-pagination-wrapper" style={{ marginTop: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '12px' }}>
            <div className="admin-pagination-info" style={{ fontSize: '0.85rem', color: '#6b7280' }}>
              Showing {(page - 1) * limit + 1} to {Math.min(page * limit, totalRecords)} of {totalRecords} transactions
            </div>
            <AdminPagination
              currentPage={page}
              totalPages={totalPages}
              onPageChange={(newPage) => setPage(newPage)}
            />
          </div>
        )}
      </div>

      {/* ========================================================= */}
      {/* PAYMENT DETAILS AUDIT MODAL                              */}
      {/* ========================================================= */}
      {isViewModalOpen && (
        <div className="admin-modal-overlay" style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.55)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 1000,
          padding: '20px'
        }}>
          <div className="admin-modal-container" style={{
            background: '#ffffff',
            borderRadius: '16px',
            width: '100%',
            maxWidth: '720px',
            maxHeight: '90vh',
            display: 'flex',
            flexDirection: 'column',
            boxShadow: '0 20px 40px rgba(0, 0, 0, 0.2)',
            overflow: 'hidden'
          }}>
            {/* Modal Header */}
            <div style={{
              padding: '18px 24px',
              borderBottom: '1px solid #e5e7eb',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              background: '#f9fafb'
            }}>
              <div>
                <h2 style={{ margin: 0, fontSize: '1.25rem', fontWeight: '800', color: '#111827' }}>
                  Transaction Details #{selectedPaymentId}
                </h2>
                {paymentDetails && (
                  <p style={{ margin: '2px 0 0 0', fontSize: '0.82rem', color: '#6b7280' }}>
                    Order #{paymentDetails.order?.order_number || paymentDetails.order_id} • {paymentDetails.provider?.toUpperCase()} ({METHOD_LABELS[paymentDetails.payment_method] || paymentDetails.payment_method})
                  </p>
                )}
              </div>
              <button
                type="button"
                onClick={handleCloseView}
                style={{
                  background: 'none',
                  border: 'none',
                  fontSize: '1.4rem',
                  cursor: 'pointer',
                  color: '#9ca3af',
                  lineHeight: 1
                }}
              >
                &times;
              </button>
            </div>

            {/* Modal Body */}
            <div style={{ padding: '24px', overflowY: 'auto' }}>
              {isViewLoading && (
                <div style={{ padding: '60px 20px', textAlign: 'center', color: '#6b7280' }}>
                  <div className="admin-loading-spinner" style={{ margin: '0 auto 12px' }}></div>
                  <p style={{ margin: 0, fontSize: '0.9rem' }}>Loading transaction payload...</p>
                </div>
              )}

              {viewError && (
                <div style={{ background: '#fee2e2', color: '#991b1b', border: '1px solid #fecaca', padding: '12px 16px', borderRadius: '8px', fontSize: '0.88rem' }}>
                  {viewError}
                </div>
              )}

              {!isViewLoading && paymentDetails && (
                <>
                  {/* Financial Snapshot */}
                  <div style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(3, 1fr)',
                    gap: '12px',
                    padding: '16px',
                    background: '#fcf4ff',
                    borderRadius: '12px',
                    border: '1px solid #f3e8ff',
                    marginBottom: '20px'
                  }}>
                    <div>
                      <span style={{ fontSize: '0.75rem', color: '#6b7280', textTransform: 'uppercase', fontWeight: '600' }}>Amount</span>
                      <div style={{ fontSize: '1.3rem', fontWeight: '800', color: '#A049A3', marginTop: '2px' }}>
                        ₹{parseFloat(paymentDetails.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                      </div>
                      <span style={{ fontSize: '0.72rem', color: '#6b7280' }}>{paymentDetails.currency || 'INR'}</span>
                    </div>

                    <div>
                      <span style={{ fontSize: '0.75rem', color: '#6b7280', textTransform: 'uppercase', fontWeight: '600' }}>Payment Status</span>
                      <div style={{ marginTop: '4px' }}>
                        <span style={{
                          padding: '4px 10px',
                          borderRadius: '10px',
                          fontSize: '0.78rem',
                          fontWeight: '700',
                          textTransform: 'uppercase',
                          background: STATUS_CONFIG[paymentDetails.status]?.bg || '#f3f4f6',
                          color: STATUS_CONFIG[paymentDetails.status]?.color || '#4b5563',
                          border: `1px solid ${STATUS_CONFIG[paymentDetails.status]?.borderColor || '#e5e7eb'}`
                        }}>
                          {STATUS_CONFIG[paymentDetails.status]?.label || paymentDetails.status}
                        </span>
                      </div>
                      {paymentDetails.attempt_no && (
                        <div style={{ fontSize: '0.72rem', color: '#6b7280', marginTop: '4px' }}>
                          Attempt #{paymentDetails.attempt_no}
                        </div>
                      )}
                    </div>

                    <div>
                      <span style={{ fontSize: '0.75rem', color: '#6b7280', textTransform: 'uppercase', fontWeight: '600' }}>Gateway Fee & Tax</span>
                      <div style={{ fontSize: '1rem', fontWeight: '700', color: '#1f2937', marginTop: '4px' }}>
                        ₹{parseFloat(paymentDetails.fee || 0).toFixed(2)}
                      </div>
                      <span style={{ fontSize: '0.72rem', color: '#6b7280' }}>
                        Tax: ₹{parseFloat(paymentDetails.tax || 0).toFixed(2)}
                      </span>
                    </div>
                  </div>

                  {/* Gateway & Provider Identifiers */}
                  <div style={{ marginBottom: '20px' }}>
                    <h4 style={{ margin: '0 0 10px 0', fontSize: '0.9rem', color: '#374151', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      Gateway & Provider Details
                    </h4>
                    <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: '10px', padding: '14px', fontSize: '0.85rem' }}>
                      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '10px' }}>
                        <div>
                          <span style={{ color: '#6b7280' }}>Provider:</span> <strong>{paymentDetails.provider?.toUpperCase()}</strong>
                        </div>
                        <div>
                          <span style={{ color: '#6b7280' }}>Method:</span> <strong>{METHOD_LABELS[paymentDetails.payment_method] || paymentDetails.payment_method}</strong>
                        </div>
                        {paymentDetails.gateway_status && (
                          <div>
                            <span style={{ color: '#6b7280' }}>Gateway Status:</span> <strong style={{ textTransform: 'capitalize' }}>{paymentDetails.gateway_status}</strong>
                          </div>
                        )}
                        {paymentDetails.receipt && (
                          <div>
                            <span style={{ color: '#6b7280' }}>Receipt No:</span> <code>{paymentDetails.receipt}</code>
                          </div>
                        )}
                        {paymentDetails.razorpay_payment_id && (
                          <div style={{ gridColumn: 'span 2' }}>
                            <span style={{ color: '#6b7280' }}>Razorpay Payment ID:</span> <code style={{ background: '#ede9fe', color: '#5b21b6', padding: '2px 6px', borderRadius: '4px', fontWeight: 'bold' }}>{paymentDetails.razorpay_payment_id}</code>
                          </div>
                        )}
                        {paymentDetails.razorpay_order_id && (
                          <div style={{ gridColumn: 'span 2' }}>
                            <span style={{ color: '#6b7280' }}>Razorpay Order ID:</span> <code>{paymentDetails.razorpay_order_id}</code>
                          </div>
                        )}
                        {paymentDetails.vpa && (
                          <div>
                            <span style={{ color: '#6b7280' }}>VPA / UPI Handle:</span> <code>{paymentDetails.vpa}</code>
                          </div>
                        )}
                        {paymentDetails.bank && (
                          <div>
                            <span style={{ color: '#6b7280' }}>Bank Name:</span> <strong>{paymentDetails.bank}</strong>
                          </div>
                        )}
                        {paymentDetails.wallet && (
                          <div>
                            <span style={{ color: '#6b7280' }}>Wallet Provider:</span> <strong>{paymentDetails.wallet}</strong>
                          </div>
                        )}
                        {paymentDetails.card_id && (
                          <div>
                            <span style={{ color: '#6b7280' }}>Card Token / ID:</span> <code>{paymentDetails.card_id}</code>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Failure / Diagnostic Information if Failed */}
                  {paymentDetails.status === 'failed' && paymentDetails.error_code && (
                    <div style={{ marginBottom: '20px' }}>
                      <h4 style={{ margin: '0 0 10px 0', fontSize: '0.9rem', color: '#b91c1c', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                        Gateway Failure Diagnostics
                      </h4>
                      <div style={{ background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '10px', padding: '14px', fontSize: '0.85rem' }}>
                        <div style={{ color: '#991b1b', fontWeight: '700', marginBottom: '4px' }}>
                          [{paymentDetails.error_code}] {paymentDetails.error_description || 'Payment Failed'}
                        </div>
                        <div style={{ color: '#6b7280', fontSize: '0.8rem' }}>
                          Source: {paymentDetails.error_source || 'Gateway'} • Step: {paymentDetails.error_step || 'Processing'} • Reason: {paymentDetails.error_reason || 'Unknown'}
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Order & Customer Snapshot */}
                  <div style={{ marginBottom: '20px' }}>
                    <h4 style={{ margin: '0 0 10px 0', fontSize: '0.9rem', color: '#374151', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      Associated Order & Customer
                    </h4>
                    <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: '10px', padding: '14px', fontSize: '0.85rem' }}>
                      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '10px' }}>
                        <div>
                          <span style={{ color: '#6b7280' }}>Order Number:</span> <strong>#{paymentDetails.order?.order_number}</strong>
                        </div>
                        <div>
                          <span style={{ color: '#6b7280' }}>Order Status:</span> <strong style={{ textTransform: 'capitalize' }}>{paymentDetails.order?.order_status}</strong>
                        </div>
                        <div>
                          <span style={{ color: '#6b7280' }}>Customer:</span> <strong>{paymentDetails.user?.name || 'Customer'}</strong>
                        </div>
                        <div>
                          <span style={{ color: '#6b7280' }}>Mobile:</span> <strong>{paymentDetails.user?.mobile || '-'}</strong>
                        </div>
                        <div style={{ gridColumn: 'span 2' }}>
                          <span style={{ color: '#6b7280' }}>Email:</span> <strong>{paymentDetails.user?.email || '-'}</strong>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Lifecycle Timelines */}
                  <div style={{ marginBottom: '20px' }}>
                    <h4 style={{ margin: '0 0 10px 0', fontSize: '0.9rem', color: '#374151', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                      Event Timeline
                    </h4>
                    <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: '10px', padding: '14px', fontSize: '0.82rem' }}>
                      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '8px' }}>
                        <div>
                          <span style={{ color: '#6b7280' }}>Created:</span> <div>{paymentDetails.timeline?.created_at ? new Date(paymentDetails.timeline.created_at).toLocaleString('en-IN') : '-'}</div>
                        </div>
                        <div>
                          <span style={{ color: '#6b7280' }}>Initiated:</span> <div>{paymentDetails.timeline?.initiated_at ? new Date(paymentDetails.timeline.initiated_at).toLocaleString('en-IN') : '-'}</div>
                        </div>
                        <div>
                          <span style={{ color: '#6b7280' }}>Authorized:</span> <div>{paymentDetails.timeline?.authorized_at ? new Date(paymentDetails.timeline.authorized_at).toLocaleString('en-IN') : '-'}</div>
                        </div>
                        <div>
                          <span style={{ color: '#6b7280' }}>Paid / Captured:</span> <div style={{ color: paymentDetails.timeline?.paid_at ? '#16a34a' : 'inherit', fontWeight: paymentDetails.timeline?.paid_at ? '600' : 'normal' }}>{paymentDetails.timeline?.paid_at ? new Date(paymentDetails.timeline.paid_at).toLocaleString('en-IN') : '-'}</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Raw Gateway Response Toggle */}
                  {paymentDetails.gateway_response && (
                    <div>
                      <button
                        type="button"
                        onClick={() => setShowRawGatewayResponse(!showRawGatewayResponse)}
                        style={{
                          background: 'none',
                          border: 'none',
                          color: '#A049A3',
                          cursor: 'pointer',
                          fontSize: '0.82rem',
                          fontWeight: '600',
                          padding: '4px 0',
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: '4px'
                        }}
                      >
                        <span>{showRawGatewayResponse ? '▼ Hide' : '▶ Show'} Raw Gateway Response Payload</span>
                      </button>
                      {showRawGatewayResponse && (
                        <pre style={{
                          background: '#1e293b',
                          color: '#f8fafc',
                          padding: '12px',
                          borderRadius: '8px',
                          fontSize: '0.74rem',
                          overflowX: 'auto',
                          maxHeight: '220px',
                          marginTop: '8px'
                        }}>
                          {JSON.stringify(paymentDetails.gateway_response, null, 2)}
                        </pre>
                      )}
                    </div>
                  )}

                </>
              )}
            </div>

            {/* Modal Footer */}
            <div style={{
              padding: '16px 24px',
              borderTop: '1px solid #e5e7eb',
              display: 'flex',
              justifyContent: 'flex-end',
              background: '#f9fafb'
            }}>
              <button
                type="button"
                onClick={handleCloseView}
                className="admin-btn admin-btn-secondary"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

    </div>
  );
};

export default AdminPayments;
