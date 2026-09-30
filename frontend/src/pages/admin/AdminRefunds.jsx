import React, { useState, useEffect, useCallback } from 'react';
import Select from 'react-select';
import AsyncSelect from 'react-select/async';
import { useAdminAuth } from '../../context/AuthContext.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';
import AdminKpiCard from '../../components/admin/AdminKpiCard.jsx';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const STATUS_CONFIG = {
  pending: {
    label: 'Pending',
    badgeClass: 'admin-badge-warning',
    bg: '#fef3c7',
    color: '#92400e',
    borderColor: '#fde68a'
  },
  processing: {
    label: 'Processing',
    badgeClass: 'admin-badge-info',
    bg: '#e0f2fe',
    color: '#0369a1',
    borderColor: '#bae6fd'
  },
  refunded: {
    label: 'Refunded',
    badgeClass: 'admin-badge-active',
    bg: '#dcfce7',
    color: '#15803d',
    borderColor: '#bbf7d0'
  },
  rejected: {
    label: 'Rejected',
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
  }
};

const CONTACT_CONFIG = {
  not_contacted: { label: 'Not Contacted', color: '#6b7280', bg: '#f3f4f6' },
  contacted: { label: 'Contacted', color: '#2563eb', bg: '#dbeafe' },
  no_response: { label: 'No Response', color: '#d97706', bg: '#fef3c7' },
  confirmed: { label: 'Confirmed', color: '#16a34a', bg: '#dcfce7' }
};

const METHOD_LABELS = {
  upi: 'UPI',
  bank_transfer: 'Bank Transfer',
  cash: 'Cash',
  razorpay: 'Razorpay',
  other: 'Other'
};

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'processing', label: 'Processing' },
  { value: 'refunded', label: 'Refunded' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'cancelled', label: 'Cancelled' }
];

const METHOD_OPTIONS = [
  { value: '', label: 'All Methods' },
  { value: 'upi', label: 'UPI' },
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'razorpay', label: 'Razorpay' },
  { value: 'cash', label: 'Cash' },
  { value: 'other', label: 'Other' }
];

const CONTACT_STATUS_OPTIONS = [
  { value: '', label: 'All Contact Statuses' },
  { value: 'not_contacted', label: 'Not Contacted' },
  { value: 'contacted', label: 'Contacted' },
  { value: 'no_response', label: 'No Response' },
  { value: 'confirmed', label: 'Confirmed' }
];

const SORT_OPTIONS = [
  { value: 'created_at', label: 'Date Created' },
  { value: 'refunded_at', label: 'Date Refunded' },
  { value: 'refund_amount', label: 'Refund Amount' },
  { value: 'status', label: 'Status' }
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

const AdminRefunds = () => {
  const { token } = useAdminAuth();

  // Data & KPI states
  const [refunds, setRefunds] = useState([]);
  const [summary, setSummary] = useState(null);
  const [isInitialLoading, setIsInitialLoading] = useState(true);
  const [isFetching, setIsFetching] = useState(false);
  const [error, setError] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  // Filters & Pagination states
  const [searchQuery, setSearchQuery] = useState('');
  const [searchInputValue, setSearchInputValue] = useState('');
  const [selectedSearchOption, setSelectedSearchOption] = useState(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [methodFilter, setMethodFilter] = useState('');
  const [contactStatusFilter, setContactStatusFilter] = useState('');
  const [sortBy, setSortBy] = useState('created_at');
  const [sortOrder, setSortOrder] = useState('desc');
  const [page, setPage] = useState(1);
  const [limit] = useState(10);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);

  // Modals state
  const [viewRefund, setViewRefund] = useState(null);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);

  // Update Refund Modal
  const [updateRefundData, setUpdateRefundData] = useState(null);
  const [isUpdateModalOpen, setIsUpdateModalOpen] = useState(false);
  const [updateForm, setUpdateForm] = useState({
    status: '',
    refund_reference: '',
    admin_note: '',
    reason: '',
    contact_status: ''
  });
  const [isUpdating, setIsUpdating] = useState(false);
  const [updateError, setUpdateError] = useState('');

  // Issue New Refund Modal
  const [isIssueModalOpen, setIsIssueModalOpen] = useState(false);
  const [issueForm, setIssueForm] = useState({
    order_id: '',
    refund_amount: '',
    refund_method: 'upi',
    refund_reference: '',
    reason: '',
    admin_note: '',
    customer_mobile: ''
  });
  const [isIssuing, setIsIssuing] = useState(false);
  const [issueError, setIssueError] = useState('');

  // Fetch Refunds List
  const fetchRefunds = useCallback(async () => {
    if (!token) return;
    setIsFetching(true);
    setError('');

    try {
      const params = new URLSearchParams();
      if (searchQuery.trim()) params.append('q', searchQuery.trim());
      if (statusFilter) params.append('status', statusFilter);
      if (methodFilter) params.append('refund_method', methodFilter);
      if (contactStatusFilter) params.append('contact_status', contactStatusFilter);
      params.append('page', String(page));
      params.append('limit', String(limit));
      params.append('sort_by', sortBy);
      params.append('sort_order', sortOrder);

      const response = await fetch(`${API_BASE_URL}/refunds/list.php?${params.toString()}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      const result = await response.json();

      if (result.status && result.data) {
        setRefunds(result.data.refunds || []);
        setSummary(result.data.summary || null);
        if (result.data.pagination) {
          setTotalPages(result.data.pagination.total_pages || 1);
          setTotalRecords(result.data.pagination.total_records || 0);
        }
      } else {
        setError(result.message || 'Failed to fetch refunds.');
      }
    } catch (err) {
      console.error('Error fetching refunds:', err);
      setError('Unable to connect to server. Please verify backend connection.');
    } finally {
      setIsInitialLoading(false);
      setIsFetching(false);
    }
  }, [token, searchQuery, statusFilter, methodFilter, contactStatusFilter, page, limit, sortBy, sortOrder]);

  useEffect(() => {
    fetchRefunds();
  }, [fetchRefunds]);

  // Load refund options dynamically for React-Select Search
  const loadRefundOptions = async (inputValue) => {
    if (!inputValue || !inputValue.trim()) return [];
    try {
      const response = await fetch(
        `${API_BASE_URL}/refunds/list.php?q=${encodeURIComponent(inputValue.trim())}&limit=10`,
        { headers: { 'Authorization': `Bearer ${token}` } }
      );
      const result = await response.json();
      if (result.status && result.data?.refunds) {
        return result.data.refunds.map((r) => ({
          value: r.order?.order_number || r.id.toString(),
          label: `Refund #${r.id} • Order #${r.order?.order_number || r.order_id} • ${r.user?.name || 'Customer'} (₹${parseFloat(r.refund_amount || 0).toFixed(0)})`,
          refund: r
        }));
      }
      return [];
    } catch (err) {
      console.error('Error loading refund options:', err);
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

  const handleClearFilters = () => {
    setSelectedSearchOption(null);
    setSearchInputValue('');
    setSearchQuery('');
    setStatusFilter('');
    setMethodFilter('');
    setContactStatusFilter('');
    setSortBy('created_at');
    setSortOrder('desc');
    setPage(1);
  };

  // Open View Modal
  const handleOpenView = (refund) => {
    setViewRefund(refund);
    setIsViewModalOpen(true);
  };

  const handleCloseView = () => {
    setIsViewModalOpen(false);
    setViewRefund(null);
  };

  // Open Update Modal
  const handleOpenUpdate = (refund) => {
    setUpdateRefundData(refund);
    setUpdateForm({
      status: refund.refund?.status || 'pending',
      refund_reference: refund.refund?.reference || '',
      admin_note: refund.refund?.admin_note || '',
      reason: refund.refund?.reason || '',
      contact_status: refund.refund?.contact_status || 'not_contacted'
    });
    setUpdateError('');
    setIsUpdateModalOpen(true);
  };

  const handleCloseUpdate = () => {
    if (isUpdating) return;
    setIsUpdateModalOpen(false);
    setUpdateRefundData(null);
    setUpdateError('');
  };

  // Handle Update Submit
  const handleUpdateSubmit = async (e) => {
    e.preventDefault();
    if (!updateRefundData || !token) return;

    setIsUpdating(true);
    setUpdateError('');

    try {
      const payload = {
        id: updateRefundData.id
      };

      const originalRefund = updateRefundData.refund || {};

      // Only send modified or allowed fields
      if (updateForm.status && updateForm.status !== originalRefund.status) {
        payload.status = updateForm.status;
      }
      if (updateForm.refund_reference !== (originalRefund.reference || '')) {
        payload.refund_reference = updateForm.refund_reference.trim();
      }
      if (updateForm.admin_note !== (originalRefund.admin_note || '')) {
        payload.admin_note = updateForm.admin_note.trim();
      }
      if (updateForm.reason !== (originalRefund.reason || '')) {
        payload.reason = updateForm.reason.trim();
      }
      if (updateForm.contact_status !== (originalRefund.contact_status || '')) {
        payload.contact_status = updateForm.contact_status;
      }

      // Check if at least one field changed
      const changedKeys = Object.keys(payload).filter(k => k !== 'id');
      if (changedKeys.length === 0) {
        setUpdateError('No changes detected to update.');
        setIsUpdating(false);
        return;
      }

      const response = await fetch(`${API_BASE_URL}/refunds/update.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(payload)
      });

      const result = await response.json();

      if (result.status) {
        setSuccessMsg(result.message || 'Refund record updated successfully.');
        handleCloseUpdate();
        if (isViewModalOpen) handleCloseView();
        fetchRefunds();
      } else {
        setUpdateError(result.message || 'Failed to update refund.');
      }
    } catch (err) {
      console.error('Error updating refund:', err);
      setUpdateError('An error occurred while updating refund.');
    } finally {
      setIsUpdating(false);
    }
  };

  // Open Issue Refund Modal
  const handleOpenIssue = () => {
    setIssueForm({
      order_id: '',
      refund_amount: '',
      refund_method: 'upi',
      refund_reference: '',
      reason: '',
      admin_note: '',
      customer_mobile: ''
    });
    setIssueError('');
    setIsIssueModalOpen(true);
  };

  const handleCloseIssue = () => {
    if (isIssuing) return;
    setIsIssueModalOpen(false);
    setIssueError('');
  };

  // Handle Issue Refund Submit
  const handleIssueSubmit = async (e) => {
    e.preventDefault();
    if (!token) return;

    const orderIdNum = parseInt(issueForm.order_id, 10);
    if (!orderIdNum || orderIdNum <= 0) {
      setIssueError('Please enter a valid positive Order ID.');
      return;
    }

    const amountNum = parseFloat(issueForm.refund_amount);
    if (!amountNum || amountNum <= 0) {
      setIssueError('Please enter a valid refund amount greater than 0.');
      return;
    }

    if (!issueForm.reason.trim()) {
      setIssueError('Reason for refund is required.');
      return;
    }

    if (['upi', 'bank_transfer', 'razorpay'].includes(issueForm.refund_method) && !issueForm.refund_reference.trim()) {
      setIssueError(`Transaction / Reference ID is required for ${METHOD_LABELS[issueForm.refund_method] || issueForm.refund_method}.`);
      return;
    }

    setIsIssuing(true);
    setIssueError('');

    try {
      const payload = {
        order_id: orderIdNum,
        refund_amount: amountNum.toFixed(2),
        refund_method: issueForm.refund_method,
        refund_reference: issueForm.refund_reference.trim(),
        reason: issueForm.reason.trim()
      };

      if (issueForm.admin_note.trim()) {
        payload.admin_note = issueForm.admin_note.trim();
      }
      if (issueForm.customer_mobile.trim()) {
        payload.customer_mobile = issueForm.customer_mobile.trim();
      }

      const response = await fetch(`${API_BASE_URL}/refunds/refund.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(payload)
      });

      const result = await response.json();

      if (result.status) {
        setSuccessMsg(result.message || 'Refund issued successfully.');
        handleCloseIssue();
        fetchRefunds();
      } else {
        setIssueError(result.message || 'Failed to issue refund.');
      }
    } catch (err) {
      console.error('Error issuing refund:', err);
      setIssueError('An error occurred while communicating with the server.');
    } finally {
      setIsIssuing(false);
    }
  };

  const isTerminalStatus = (status) => ['refunded', 'rejected', 'cancelled'].includes(status);

  return (
    <div className="admin-page-container">
      {/* Page Header */}
      <div className="admin-header-row" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ margin: 0, fontSize: '1.75rem', fontWeight: '800', color: '#111827' }}>
            Returns & Refunds Management
          </h1>
          <p style={{ margin: '4px 0 0 0', color: '#6b7280', fontSize: '0.9rem' }}>
            Monitor refund claims, track reimbursement lifecycle, issue payouts, and manage reference records.
          </p>
        </div>

        <div style={{ display: 'flex', gap: '10px', alignItems: 'center', flexWrap: 'wrap' }}>
          <button
            type="button"
            onClick={fetchRefunds}
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

          <button
            type="button"
            onClick={handleOpenIssue}
            className="admin-btn admin-btn-primary"
            style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', background: 'linear-gradient(135deg, #A049A3 0%, #C86395 100%)', border: 'none', color: '#fff', fontWeight: '600' }}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Issue / Record Refund
          </button>
        </div>
      </div>

      {/* Success Notification Alert */}
      {successMsg && (
        <div style={{ background: '#dcfce7', color: '#166534', border: '1px solid #bbf7d0', padding: '12px 18px', borderRadius: '8px', marginBottom: '20px', fontSize: '0.9rem', display: 'flex', alignItems: 'center', justifyContent: 'space-between', animation: 'fadeIn 0.2s ease' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <span><strong>Success:</strong> {successMsg}</span>
          </div>
          <button
            type="button"
            onClick={() => setSuccessMsg('')}
            style={{ background: 'none', border: 'none', color: '#166534', cursor: 'pointer', fontSize: '1.2rem', fontWeight: 'bold' }}
            aria-label="Dismiss"
          >
            &times;
          </button>
        </div>
      )}

      {/* Error Notification Alert */}
      {error && (
        <div style={{ background: '#fee2e2', color: '#991b1b', border: '1px solid #fecaca', padding: '12px 18px', borderRadius: '8px', marginBottom: '20px', fontSize: '0.9rem', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span><strong>Error:</strong> {error}</span>
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

      {/* Aggregate KPI Summary Cards */}
      {summary && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px', marginBottom: '28px' }}>
          <AdminKpiCard
            variant="purple"
            title="Total Refunds"
            value={summary.total_refund_records?.toLocaleString('en-IN') || '0'}
            badgeText="All Records"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                <polyline points="1 4 1 10 7 10"></polyline>
                <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
              </svg>
            }
            footerLeft={<span>{summary.completed_refunds || 0} Completed</span>}
            footerRight={<span>{summary.full_refunds || 0} Full / {summary.partial_refunds || 0} Partial</span>}
          />

          <AdminKpiCard
            variant="pink"
            title="Total Refunded Amount"
            value={`₹${parseFloat(summary.total_refunded_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`}
            badgeText="Settled"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
              </svg>
            }
            footerLeft={<span>Average Refund</span>}
            footerRight={<span>₹{parseFloat(summary.average_refund_amount || 0).toFixed(2)}</span>}
          />

          <AdminKpiCard
            variant="orange"
            title="Pending Requests"
            value={summary.pending_refunds?.toLocaleString('en-IN') || '0'}
            badgeText="Needs Action"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            }
            footerLeft={<span>Pending Payout</span>}
            footerRight={<span>₹{parseFloat(summary.pending_refund_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>}
          />

          <AdminKpiCard
            variant="blue"
            title="In Processing"
            value={summary.processing_refunds?.toLocaleString('en-IN') || '0'}
            badgeText="Under Review"
            icon={
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
              </svg>
            }
            footerLeft={<span>In Progress</span>}
            footerRight={<span>₹{parseFloat(summary.processing_refund_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>}
          />
        </div>
      )}

      {/* Main Table Card */}
      <div className="admin-card" style={{ padding: '24px', borderRadius: '14px', background: '#fff', boxShadow: '0 2px 10px rgba(0,0,0,0.03)' }}>

        {/* Status Filter Tabs */}
        <div style={{ display: 'flex', gap: '8px', borderBottom: '1px solid #e5e7eb', paddingBottom: '14px', marginBottom: '20px', overflowX: 'auto' }}>
          {[
            { id: '', label: 'All Refunds', count: summary?.total_refund_records },
            { id: 'pending', label: 'Pending', count: summary?.pending_refunds },
            { id: 'processing', label: 'Processing', count: summary?.processing_refunds },
            { id: 'refunded', label: 'Refunded', count: summary?.completed_refunds },
            { id: 'rejected', label: 'Rejected', count: summary?.rejected_refunds },
            { id: 'cancelled', label: 'Cancelled', count: summary?.cancelled_refunds }
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

        {/* 1. REACT SELECT SEARCH FOR REFUNDS */}
        <div style={{ marginBottom: '20px' }}>
          <label className="admin-label" style={{ marginBottom: '8px', display: 'block', fontWeight: '700', fontSize: '0.86rem', color: '#374151' }}>
            Search Refunds (React Select)
          </label>
          <form onSubmit={handleSearchSubmit} style={{ display: 'flex', gap: '10px', maxWidth: '800px', width: '100%', alignItems: 'center' }}>
            <div style={{ flex: 1 }}>
              <AsyncSelect
                cacheOptions
                defaultOptions={false}
                loadOptions={loadRefundOptions}
                value={selectedSearchOption}
                onInputChange={(val, action) => {
                  if (action.action === 'input-change') {
                    setSearchInputValue(val);
                  }
                }}
                onChange={handleSearchSelectChange}
                placeholder="Search refunds by ID, Order #, Reference, Customer..."
                isClearable
                noOptionsMessage={({ inputValue }) =>
                  !inputValue ? 'Type refund ID, Order #, or customer name...' : 'No matching refunds found'
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
              Refund Status
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

          {/* Refund Method Filter */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Refund Method
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

          {/* Contact Status Filter */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Customer Contact Status
            </label>
            <Select
              styles={customSelectStyles}
              options={CONTACT_STATUS_OPTIONS}
              value={CONTACT_STATUS_OPTIONS.find(o => o.value === contactStatusFilter) || CONTACT_STATUS_OPTIONS[0]}
              onChange={(opt) => {
                setContactStatusFilter(opt ? opt.value : '');
                setPage(1);
              }}
              isSearchable={false}
            />
          </div>

          {/* Sort By & Order Toggle */}
          <div>
            <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.82rem' }}>
              Sort Records
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
        {(searchQuery || statusFilter || methodFilter || contactStatusFilter) && (
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
            {methodFilter && (
              <span style={{ background: '#fef3c7', color: '#92400e', padding: '2px 8px', borderRadius: '12px', fontSize: '0.76rem', fontWeight: '600' }}>
                Method: {METHOD_LABELS[methodFilter] || methodFilter}
              </span>
            )}
            {contactStatusFilter && (
              <span style={{ background: '#ecfdf5', color: '#065f46', padding: '2px 8px', borderRadius: '12px', fontSize: '0.76rem', fontWeight: '600' }}>
                Contact: {contactStatusFilter}
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
            <p style={{ margin: 0, fontSize: '0.9rem' }}>Loading refund records...</p>
          </div>
        )}

        {/* Empty State */}
        {!isInitialLoading && refunds.length === 0 && (
          <div style={{ padding: '60px 20px', textAlign: 'center', background: '#faf5ff', borderRadius: '12px', border: '1px dashed #d8b4fe' }}>
            <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: '#f3e8ff', color: '#9333ea', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', marginBottom: '14px' }}>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <polyline points="1 4 1 10 7 10"></polyline>
                <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
              </svg>
            </div>
            <h3 style={{ margin: '0 0 6px 0', color: '#1f2937', fontSize: '1.1rem' }}>No Refund Records Found</h3>
            <p style={{ margin: '0 0 16px 0', color: '#6b7280', fontSize: '0.88rem' }}>
              {searchQuery || statusFilter || methodFilter || contactStatusFilter
                ? 'Try adjusting your filters or search terms.'
                : 'No refunds have been requested or processed yet.'}
            </p>
            {(searchQuery || statusFilter || methodFilter || contactStatusFilter) && (
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

        {/* Refunds Data Table */}
        {!isInitialLoading && refunds.length > 0 && (
          <div style={{ opacity: isFetching ? 0.6 : 1, transition: 'opacity 0.2s ease' }}>
          <div className="admin-table-responsive" style={{ overflowX: 'auto' }}>
            <table className="admin-table" style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
              <thead>
                <tr style={{ background: '#f9fafb', borderBottom: '2px solid #e5e7eb', color: '#4b5563', fontSize: '0.8rem', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                  <th style={{ padding: '12px 16px' }}>Refund & Date</th>
                  <th style={{ padding: '12px 16px' }}>Order Details</th>
                  <th style={{ padding: '12px 16px' }}>Customer</th>
                  <th style={{ padding: '12px 16px' }}>Amount</th>
                  <th style={{ padding: '12px 16px' }}>Payout Method</th>
                  <th style={{ padding: '12px 16px' }}>Status</th>
                  <th style={{ padding: '12px 16px', textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {refunds.map((item) => {
                  const refStatus = item.refund?.status || 'pending';
                  const statusStyle = STATUS_CONFIG[refStatus] || STATUS_CONFIG.pending;
                  const contactStyle = CONTACT_CONFIG[item.refund?.contact_status] || CONTACT_CONFIG.not_contacted;

                  return (
                    <tr key={item.id} style={{ borderBottom: '1px solid #f3f4f6', transition: 'background 0.15s ease' }}>

                      {/* Refund ID & Date */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <span style={{ fontWeight: '800', color: '#111827', fontSize: '0.92rem' }}>
                            #{item.id}
                          </span>
                          <span style={{
                            padding: '2px 7px',
                            borderRadius: '4px',
                            fontSize: '0.7rem',
                            fontWeight: '700',
                            textTransform: 'uppercase',
                            background: item.refund?.type === 'full' ? '#ede9fe' : '#e0e7ff',
                            color: item.refund?.type === 'full' ? '#6b21a8' : '#3730a3'
                          }}>
                            {item.refund?.type || 'Full'}
                          </span>
                        </div>
                        <div style={{ fontSize: '0.76rem', color: '#6b7280', marginTop: '4px' }}>
                          {item.timeline?.created_at ? new Date(item.timeline.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'}
                        </div>
                      </td>

                      {/* Order Details */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <div style={{ fontWeight: '700', color: '#A049A3', fontSize: '0.88rem' }}>
                          #{item.order?.order_number || item.order?.id}
                        </div>
                        <div style={{ fontSize: '0.78rem', color: '#4b5563', marginTop: '2px' }}>
                          Order Total: ₹{parseFloat(item.order?.amounts?.grand_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </div>
                        <div style={{ fontSize: '0.74rem', color: '#6b7280', marginTop: '2px' }}>
                          Order Status: <span style={{ textTransform: 'capitalize', fontWeight: '600' }}>{item.order?.order_status || '-'}</span>
                        </div>
                      </td>

                      {/* Customer */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <div style={{ fontWeight: '600', color: '#111827', fontSize: '0.88rem' }}>
                          {item.customer?.name || 'Customer'}
                        </div>
                        <div style={{ fontSize: '0.78rem', color: '#6b7280', marginTop: '2px' }}>
                          {item.customer?.mobile || item.refund?.customer_mobile || '-'}
                        </div>
                        {item.customer?.email && (
                          <div style={{ fontSize: '0.74rem', color: '#9ca3af' }}>
                            {item.customer.email}
                          </div>
                        )}
                      </td>

                      {/* Amount Details */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <div style={{ fontWeight: '800', color: '#111827', fontSize: '0.98rem' }}>
                          ₹{parseFloat(item.refund?.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </div>
                        <div style={{ fontSize: '0.74rem', color: '#6b7280', marginTop: '2px' }}>
                          Paid: ₹{parseFloat(item.refund_calculation?.original_payment_amount || 0).toFixed(2)}
                        </div>
                        {parseFloat(item.refund_calculation?.remaining_refundable_amount || 0) > 0 && (
                          <div style={{ fontSize: '0.72rem', color: '#d97706' }}>
                            Rem: ₹{parseFloat(item.refund_calculation?.remaining_refundable_amount).toFixed(2)}
                          </div>
                        )}
                      </td>

                      {/* Payout Method & Reference */}
                      <td style={{ padding: '14px 16px', verticalAlign: 'top' }}>
                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
                          <span style={{
                            padding: '3px 8px',
                            borderRadius: '4px',
                            fontSize: '0.75rem',
                            fontWeight: '700',
                            textTransform: 'uppercase',
                            background: '#f3f4f6',
                            color: '#374151'
                          }}>
                            {METHOD_LABELS[item.refund?.method] || (item.refund?.method || '-').toUpperCase()}
                          </span>
                        </div>
                        {item.refund?.reference && (
                          <div style={{ fontSize: '0.76rem', color: '#4b5563', marginTop: '4px', fontFamily: 'monospace' }} title="Refund Reference">
                            Ref: {item.refund.reference}
                          </div>
                        )}
                        {item.refund?.contact_status && (
                          <div style={{ marginTop: '5px' }}>
                            <span style={{
                              padding: '2px 7px',
                              borderRadius: '10px',
                              fontSize: '0.7rem',
                              fontWeight: '600',
                              background: contactStyle.bg,
                              color: contactStyle.color
                            }}>
                              {contactStyle.label}
                            </span>
                          </div>
                        )}
                      </td>

                      {/* Status */}
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
                        {item.refund?.reason && (
                          <div style={{ fontSize: '0.75rem', color: '#6b7280', marginTop: '4px', maxWidth: '200px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }} title={item.refund.reason}>
                            "{item.refund.reason}"
                          </div>
                        )}
                      </td>

                      {/* Actions */}
                      <td style={{ padding: '14px 16px', textAlign: 'right', verticalAlign: 'top' }}>
                        <div style={{ display: 'inline-flex', gap: '8px' }}>
                          <button
                            type="button"
                            onClick={() => handleOpenView(item)}
                            className="admin-btn admin-btn-primary"
                            style={{ padding: '6px 12px', fontSize: '0.8rem', fontWeight: '600' }}
                          >
                            View
                          </button>

                          <button
                            type="button"
                            onClick={() => handleOpenUpdate(item)}
                            className="admin-btn admin-btn-secondary"
                            style={{ padding: '6px 10px', fontSize: '0.8rem', fontWeight: '600', display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                            title="Update Status / Note / Ref"
                          >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            <span>Update</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="admin-pagination-wrapper" style={{ marginTop: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '12px' }}>
              <div className="admin-pagination-info" style={{ fontSize: '0.85rem', color: '#6b7280' }}>
                Showing {(page - 1) * limit + 1} to {Math.min(page * limit, totalRecords)} of {totalRecords} refund records
              </div>
              <AdminPagination
                currentPage={page}
                totalPages={totalPages}
                onPageChange={(newPage) => setPage(newPage)}
              />
            </div>
          )}
          </div>
        )}
      </div>

      {/* ========================================================= */}
      {/* 1. VIEW REFUND DETAILS MODAL                              */}
      {/* ========================================================= */}
      {isViewModalOpen && viewRefund && (
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
            maxWidth: '680px',
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
                  Refund Details #{viewRefund.id}
                </h2>
                <p style={{ margin: '2px 0 0 0', fontSize: '0.82rem', color: '#6b7280' }}>
                  Order #{viewRefund.order?.order_number || viewRefund.order?.id} • Created on {viewRefund.timeline?.created_at ? new Date(viewRefund.timeline.created_at).toLocaleString('en-IN') : '-'}
                </p>
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
                  <span style={{ fontSize: '0.75rem', color: '#6b7280', textTransform: 'uppercase', fontWeight: '600' }}>Refund Amount</span>
                  <div style={{ fontSize: '1.25rem', fontWeight: '800', color: '#A049A3', marginTop: '2px' }}>
                    ₹{parseFloat(viewRefund.refund?.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                  </div>
                  <span style={{ fontSize: '0.72rem', color: '#6b7280' }}>Type: {viewRefund.refund?.type?.toUpperCase()}</span>
                </div>

                <div>
                  <span style={{ fontSize: '0.75rem', color: '#6b7280', textTransform: 'uppercase', fontWeight: '600' }}>Paid Amount</span>
                  <div style={{ fontSize: '1.25rem', fontWeight: '800', color: '#1f2937', marginTop: '2px' }}>
                    ₹{parseFloat(viewRefund.refund_calculation?.original_payment_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                  </div>
                  <span style={{ fontSize: '0.72rem', color: '#6b7280' }}>Method: {METHOD_LABELS[viewRefund.refund?.method] || viewRefund.refund?.method}</span>
                </div>

                <div>
                  <span style={{ fontSize: '0.75rem', color: '#6b7280', textTransform: 'uppercase', fontWeight: '600' }}>Refund Status</span>
                  <div style={{ marginTop: '4px' }}>
                    <span style={{
                      padding: '4px 10px',
                      borderRadius: '10px',
                      fontSize: '0.78rem',
                      fontWeight: '700',
                      textTransform: 'uppercase',
                      background: STATUS_CONFIG[viewRefund.refund?.status]?.bg || '#f3f4f6',
                      color: STATUS_CONFIG[viewRefund.refund?.status]?.color || '#4b5563',
                      border: `1px solid ${STATUS_CONFIG[viewRefund.refund?.status]?.borderColor || '#e5e7eb'}`
                    }}>
                      {STATUS_CONFIG[viewRefund.refund?.status]?.label || viewRefund.refund?.status}
                    </span>
                  </div>
                  <div style={{ fontSize: '0.72rem', color: '#6b7280', marginTop: '4px' }}>
                    Contact: {CONTACT_CONFIG[viewRefund.refund?.contact_status]?.label || viewRefund.refund?.contact_status}
                  </div>
                </div>
              </div>

              {/* Order & Payment Context */}
              <div style={{ marginBottom: '20px' }}>
                <h4 style={{ margin: '0 0 10px 0', fontSize: '0.9rem', color: '#374151', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                  Order & Payment Context
                </h4>
                <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: '10px', padding: '14px', fontSize: '0.85rem' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '10px' }}>
                    <div>
                      <span style={{ color: '#6b7280' }}>Order Number:</span> <strong>#{viewRefund.order?.order_number}</strong>
                    </div>
                    <div>
                      <span style={{ color: '#6b7280' }}>Order Status:</span> <strong style={{ textTransform: 'capitalize' }}>{viewRefund.order?.order_status}</strong>
                    </div>
                    <div>
                      <span style={{ color: '#6b7280' }}>Order Grand Total:</span> <strong>₹{parseFloat(viewRefund.order?.amounts?.grand_total || 0).toFixed(2)}</strong>
                    </div>
                    <div>
                      <span style={{ color: '#6b7280' }}>Payment Status:</span> <strong style={{ textTransform: 'capitalize' }}>{viewRefund.payment?.status || viewRefund.order?.payment_status}</strong>
                    </div>
                    {viewRefund.payment?.razorpay_payment_id && (
                      <div style={{ gridColumn: 'span 2' }}>
                        <span style={{ color: '#6b7280' }}>Razorpay Payment ID:</span> <code style={{ background: '#f3f4f6', padding: '2px 6px', borderRadius: '4px' }}>{viewRefund.payment.razorpay_payment_id}</code>
                      </div>
                    )}
                    {viewRefund.refund?.reference && (
                      <div style={{ gridColumn: 'span 2' }}>
                        <span style={{ color: '#6b7280' }}>Refund Reference / UTR:</span> <code style={{ background: '#e0e7ff', color: '#3730a3', padding: '2px 6px', borderRadius: '4px', fontWeight: 'bold' }}>{viewRefund.refund.reference}</code>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Customer Details */}
              <div style={{ marginBottom: '20px' }}>
                <h4 style={{ margin: '0 0 10px 0', fontSize: '0.9rem', color: '#374151', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                  Customer Information
                </h4>
                <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: '10px', padding: '14px', fontSize: '0.85rem' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '10px' }}>
                    <div>
                      <span style={{ color: '#6b7280' }}>Name:</span> <strong>{viewRefund.customer?.name || 'Customer'}</strong>
                    </div>
                    <div>
                      <span style={{ color: '#6b7280' }}>Mobile:</span> <strong>{viewRefund.customer?.mobile || viewRefund.refund?.customer_mobile || '-'}</strong>
                    </div>
                    <div>
                      <span style={{ color: '#6b7280' }}>Email:</span> <strong>{viewRefund.customer?.email || '-'}</strong>
                    </div>
                    <div>
                      <span style={{ color: '#6b7280' }}>Customer Status:</span> <strong style={{ textTransform: 'capitalize' }}>{viewRefund.customer?.status || 'Active'}</strong>
                    </div>
                  </div>
                </div>
              </div>

              {/* Reason & Admin Notes */}
              <div style={{ marginBottom: '20px' }}>
                <h4 style={{ margin: '0 0 10px 0', fontSize: '0.9rem', color: '#374151', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                  Notes & Reasons
                </h4>
                <div style={{ background: '#fff', border: '1px solid #e5e7eb', borderRadius: '10px', padding: '14px', fontSize: '0.85rem' }}>
                  <div style={{ marginBottom: '10px' }}>
                    <div style={{ fontSize: '0.75rem', fontWeight: '700', color: '#4b5563', textTransform: 'uppercase' }}>Reason Provided:</div>
                    <div style={{ color: '#1f2937', marginTop: '2px', background: '#f9fafb', padding: '8px 12px', borderRadius: '6px' }}>
                      {viewRefund.refund?.reason || 'No specific reason entered.'}
                    </div>
                  </div>
                  <div>
                    <div style={{ fontSize: '0.75rem', fontWeight: '700', color: '#4b5563', textTransform: 'uppercase' }}>Admin Internal Note:</div>
                    <div style={{ color: '#1f2937', marginTop: '2px', background: '#f9fafb', padding: '8px 12px', borderRadius: '6px' }}>
                      {viewRefund.refund?.admin_note || 'No internal note recorded.'}
                    </div>
                  </div>
                </div>
              </div>

              {/* Lifecycle Timelines */}
              <div>
                <h4 style={{ margin: '0 0 10px 0', fontSize: '0.9rem', color: '#374151', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                  Event Timeline
                </h4>
                <div style={{ background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: '10px', padding: '14px', fontSize: '0.82rem' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '8px' }}>
                    <div>
                      <span style={{ color: '#6b7280' }}>Requested / Created:</span> <div>{viewRefund.timeline?.created_at ? new Date(viewRefund.timeline.created_at).toLocaleString('en-IN') : '-'}</div>
                    </div>
                    <div>
                      <span style={{ color: '#6b7280' }}>Contacted Customer:</span> <div>{viewRefund.timeline?.contacted_at ? new Date(viewRefund.timeline.contacted_at).toLocaleString('en-IN') : '-'}</div>
                    </div>
                    <div>
                      <span style={{ color: '#6b7280' }}>Moved to Processing:</span> <div>{viewRefund.timeline?.processing_at ? new Date(viewRefund.timeline.processing_at).toLocaleString('en-IN') : '-'}</div>
                    </div>
                    <div>
                      <span style={{ color: '#6b7280' }}>Refund Executed:</span> <div>{viewRefund.timeline?.refunded_at ? new Date(viewRefund.timeline.refunded_at).toLocaleString('en-IN') : '-'}</div>
                    </div>
                  </div>
                </div>
              </div>

            </div>

            {/* Modal Footer */}
            <div style={{
              padding: '16px 24px',
              borderTop: '1px solid #e5e7eb',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              background: '#f9fafb'
            }}>
              <button
                type="button"
                onClick={handleCloseView}
                className="admin-btn admin-btn-secondary"
              >
                Close
              </button>

              <button
                type="button"
                onClick={() => {
                  const r = viewRefund;
                  handleCloseView();
                  handleOpenUpdate(r);
                }}
                className="admin-btn admin-btn-primary"
                style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <span>Update This Refund</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================= */}
      {/* 2. UPDATE REFUND MODAL                                    */}
      {/* ========================================================= */}
      {isUpdateModalOpen && updateRefundData && (
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
            maxWidth: '560px',
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
                <h2 style={{ margin: 0, fontSize: '1.2rem', fontWeight: '800', color: '#111827' }}>
                  Update Refund Record #{updateRefundData.id}
                </h2>
                <p style={{ margin: '2px 0 0 0', fontSize: '0.82rem', color: '#6b7280' }}>
                  Order #{updateRefundData.order?.order_number || updateRefundData.order?.id} • ₹{parseFloat(updateRefundData.refund?.amount || 0).toFixed(2)}
                </p>
              </div>
              <button
                type="button"
                onClick={handleCloseUpdate}
                disabled={isUpdating}
                style={{
                  background: 'none',
                  border: 'none',
                  fontSize: '1.4rem',
                  cursor: isUpdating ? 'not-allowed' : 'pointer',
                  color: '#9ca3af',
                  lineHeight: 1
                }}
              >
                &times;
              </button>
            </div>

            {/* Modal Form */}
            <form onSubmit={handleUpdateSubmit} style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
              <div style={{ padding: '24px', overflowY: 'auto' }}>

                {updateError && (
                  <div style={{ background: '#fee2e2', color: '#991b1b', border: '1px solid #fecaca', padding: '10px 14px', borderRadius: '8px', marginBottom: '18px', fontSize: '0.86rem' }}>
                    {updateError}
                  </div>
                )}

                {/* Status Notice if terminal */}
                {isTerminalStatus(updateRefundData.refund?.status) && (
                  <div style={{ background: '#fef3c7', color: '#92400e', border: '1px solid #fde68a', padding: '10px 14px', borderRadius: '8px', marginBottom: '16px', fontSize: '0.82rem' }}>
                    <strong>Note:</strong> This refund has reached a terminal status (<strong>{updateRefundData.refund?.status}</strong>). Its financial status cannot be transitioned back, but you can update internal notes and reference numbers.
                  </div>
                )}

                {/* Status Select */}
                <div style={{ marginBottom: '16px' }}>
                  <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                    Refund Status
                  </label>
                  {isTerminalStatus(updateRefundData.refund?.status) ? (
                    <input
                      type="text"
                      className="admin-input"
                      value={updateRefundData.refund?.status?.toUpperCase()}
                      disabled
                      style={{ background: '#f3f4f6', cursor: 'not-allowed', fontWeight: 'bold' }}
                    />
                  ) : (
                    <select
                      className="admin-input"
                      value={updateForm.status}
                      onChange={(e) => setUpdateForm({ ...updateForm, status: e.target.value })}
                      disabled={isUpdating}
                    >
                      {updateRefundData.refund?.status === 'pending' && (
                        <>
                          <option value="pending">Pending</option>
                          <option value="processing">Processing</option>
                          <option value="refunded">Refunded (Completed)</option>
                          <option value="rejected">Rejected</option>
                          <option value="cancelled">Cancelled</option>
                        </>
                      )}
                      {updateRefundData.refund?.status === 'processing' && (
                        <>
                          <option value="processing">Processing</option>
                          <option value="refunded">Refunded (Completed)</option>
                          <option value="rejected">Rejected</option>
                          <option value="cancelled">Cancelled</option>
                        </>
                      )}
                    </select>
                  )}
                  <small style={{ color: '#6b7280', fontSize: '0.74rem' }}>
                    Marking as "Refunded" confirms that the payment has been reimbursed.
                  </small>
                </div>

                {/* Contact Status */}
                <div style={{ marginBottom: '16px' }}>
                  <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                    Customer Contact Status
                  </label>
                  <select
                    className="admin-input"
                    value={updateForm.contact_status}
                    onChange={(e) => setUpdateForm({ ...updateForm, contact_status: e.target.value })}
                    disabled={isUpdating}
                  >
                    <option value="not_contacted">Not Contacted</option>
                    <option value="contacted">Contacted</option>
                    <option value="no_response">No Response</option>
                    <option value="confirmed">Confirmed</option>
                  </select>
                </div>

                {/* Refund Reference / UTR */}
                <div style={{ marginBottom: '16px' }}>
                  <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                    Refund Reference / Transaction ID / UTR
                  </label>
                  <input
                    type="text"
                    className="admin-input"
                    placeholder="e.g. UPI Ref / Bank UTR / Razorpay Refund ID"
                    value={updateForm.refund_reference}
                    onChange={(e) => setUpdateForm({ ...updateForm, refund_reference: e.target.value })}
                    maxLength={150}
                    disabled={isUpdating}
                  />
                  <small style={{ color: '#6b7280', fontSize: '0.74rem' }}>
                    Maximum 150 characters.
                  </small>
                </div>

                {/* Reason */}
                <div style={{ marginBottom: '16px' }}>
                  <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                    Refund Reason
                  </label>
                  <input
                    type="text"
                    className="admin-input"
                    placeholder="Reason for refund or cancellation..."
                    value={updateForm.reason}
                    onChange={(e) => setUpdateForm({ ...updateForm, reason: e.target.value })}
                    maxLength={500}
                    disabled={isUpdating}
                  />
                </div>

                {/* Admin Note */}
                <div style={{ marginBottom: '10px' }}>
                  <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                    Internal Admin Note
                  </label>
                  <textarea
                    className="admin-input"
                    rows="3"
                    placeholder="Add payout notes, customer conversation records, or bank remarks..."
                    value={updateForm.admin_note}
                    onChange={(e) => setUpdateForm({ ...updateForm, admin_note: e.target.value })}
                    maxLength={1000}
                    disabled={isUpdating}
                    style={{ resize: 'vertical' }}
                  ></textarea>
                  <small style={{ color: '#6b7280', fontSize: '0.74rem' }}>
                    Maximum 1000 characters. Visible only to boutique administrators.
                  </small>
                </div>

              </div>

              {/* Modal Footer */}
              <div style={{
                padding: '16px 24px',
                borderTop: '1px solid #e5e7eb',
                display: 'flex',
                justifyContent: 'flex-end',
                gap: '10px',
                background: '#f9fafb'
              }}>
                <button
                  type="button"
                  onClick={handleCloseUpdate}
                  disabled={isUpdating}
                  className="admin-btn admin-btn-secondary"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isUpdating}
                  className="admin-btn admin-btn-primary"
                  style={{ minWidth: '120px' }}
                >
                  {isUpdating ? 'Saving...' : 'Save Changes'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================= */}
      {/* 3. ISSUE NEW REFUND MODAL                                 */}
      {/* ========================================================= */}
      {isIssueModalOpen && (
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
            maxWidth: '560px',
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
                <h2 style={{ margin: 0, fontSize: '1.2rem', fontWeight: '800', color: '#111827' }}>
                  Issue / Record Payout Refund
                </h2>
                <p style={{ margin: '2px 0 0 0', fontSize: '0.82rem', color: '#6b7280' }}>
                  Process a manual or automated refund against an eligible paid order.
                </p>
              </div>
              <button
                type="button"
                onClick={handleCloseIssue}
                disabled={isIssuing}
                style={{
                  background: 'none',
                  border: 'none',
                  fontSize: '1.4rem',
                  cursor: isIssuing ? 'not-allowed' : 'pointer',
                  color: '#9ca3af',
                  lineHeight: 1
                }}
              >
                &times;
              </button>
            </div>

            {/* Modal Form */}
            <form onSubmit={handleIssueSubmit} style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
              <div style={{ padding: '24px', overflowY: 'auto' }}>

                {issueError && (
                  <div style={{ background: '#fee2e2', color: '#991b1b', border: '1px solid #fecaca', padding: '10px 14px', borderRadius: '8px', marginBottom: '18px', fontSize: '0.86rem' }}>
                    {issueError}
                  </div>
                )}

                {/* Order ID & Refund Amount Row */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '14px', marginBottom: '16px' }}>
                  <div>
                    <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                      Order ID (Numeric) <span style={{ color: '#e11d48' }}>*</span>
                    </label>
                    <input
                      type="number"
                      min="1"
                      className="admin-input"
                      placeholder="e.g. 15"
                      value={issueForm.order_id}
                      onChange={(e) => setIssueForm({ ...issueForm, order_id: e.target.value })}
                      required
                      disabled={isIssuing}
                    />
                    <small style={{ color: '#6b7280', fontSize: '0.74rem' }}>
                      Database integer ID of the order.
                    </small>
                  </div>

                  <div>
                    <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                      Refund Amount (₹) <span style={{ color: '#e11d48' }}>*</span>
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      min="0.01"
                      className="admin-input"
                      placeholder="e.g. 1499.00"
                      value={issueForm.refund_amount}
                      onChange={(e) => setIssueForm({ ...issueForm, refund_amount: e.target.value })}
                      required
                      disabled={isIssuing}
                    />
                    <small style={{ color: '#6b7280', fontSize: '0.74rem' }}>
                      Maximum 2 decimal places.
                    </small>
                  </div>
                </div>

                {/* Method & Reference Row */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '14px', marginBottom: '16px' }}>
                  <div>
                    <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                      Refund Method <span style={{ color: '#e11d48' }}>*</span>
                    </label>
                    <select
                      className="admin-input"
                      value={issueForm.refund_method}
                      onChange={(e) => setIssueForm({ ...issueForm, refund_method: e.target.value })}
                      disabled={isIssuing}
                    >
                      <option value="upi">UPI</option>
                      <option value="bank_transfer">Bank Transfer (NEFT/IMPS)</option>
                      <option value="razorpay">Razorpay</option>
                      <option value="cash">Cash</option>
                      <option value="other">Other</option>
                    </select>
                  </div>

                  <div>
                    <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                      Transaction / Reference ID
                      {['upi', 'bank_transfer', 'razorpay'].includes(issueForm.refund_method) && (
                        <span style={{ color: '#e11d48' }}> *</span>
                      )}
                    </label>
                    <input
                      type="text"
                      className="admin-input"
                      placeholder="e.g. UTR / UPI Ref"
                      value={issueForm.refund_reference}
                      onChange={(e) => setIssueForm({ ...issueForm, refund_reference: e.target.value })}
                      maxLength={150}
                      disabled={isIssuing}
                      required={['upi', 'bank_transfer', 'razorpay'].includes(issueForm.refund_method)}
                    />
                  </div>
                </div>

                {/* Reason */}
                <div style={{ marginBottom: '16px' }}>
                  <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                    Reason for Refund <span style={{ color: '#e11d48' }}>*</span>
                  </label>
                  <input
                    type="text"
                    className="admin-input"
                    placeholder="e.g. Customer cancelled order / Out of stock / Return approved"
                    value={issueForm.reason}
                    onChange={(e) => setIssueForm({ ...issueForm, reason: e.target.value })}
                    maxLength={500}
                    required
                    disabled={isIssuing}
                  />
                  <small style={{ color: '#6b7280', fontSize: '0.74rem' }}>
                    Required. Maximum 500 characters.
                  </small>
                </div>

                {/* Customer Mobile (Optional) */}
                <div style={{ marginBottom: '16px' }}>
                  <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                    Customer Contact Mobile (Optional)
                  </label>
                  <input
                    type="text"
                    className="admin-input"
                    placeholder="e.g. 9876543210"
                    value={issueForm.customer_mobile}
                    onChange={(e) => setIssueForm({ ...issueForm, customer_mobile: e.target.value })}
                    disabled={isIssuing}
                  />
                </div>

                {/* Admin Note */}
                <div style={{ marginBottom: '10px' }}>
                  <label className="admin-label" style={{ marginBottom: '6px', display: 'block', fontWeight: '600', fontSize: '0.84rem' }}>
                    Admin Internal Note (Optional)
                  </label>
                  <textarea
                    className="admin-input"
                    rows="2"
                    placeholder="Internal reference notes regarding this reimbursement..."
                    value={issueForm.admin_note}
                    onChange={(e) => setIssueForm({ ...issueForm, admin_note: e.target.value })}
                    maxLength={1000}
                    disabled={isIssuing}
                    style={{ resize: 'vertical' }}
                  ></textarea>
                </div>

              </div>

              {/* Modal Footer */}
              <div style={{
                padding: '16px 24px',
                borderTop: '1px solid #e5e7eb',
                display: 'flex',
                justifyContent: 'flex-end',
                gap: '10px',
                background: '#f9fafb'
              }}>
                <button
                  type="button"
                  onClick={handleCloseIssue}
                  disabled={isIssuing}
                  className="admin-btn admin-btn-secondary"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isIssuing}
                  className="admin-btn admin-btn-primary"
                  style={{ minWidth: '140px', background: 'linear-gradient(135deg, #A049A3 0%, #C86395 100%)', border: 'none' }}
                >
                  {isIssuing ? 'Processing...' : 'Issue Refund'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminRefunds;

