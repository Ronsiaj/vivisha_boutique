import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAdminAuth } from '../../context/AuthContext.jsx';
import AdminKpiCard from '../../components/admin/AdminKpiCard.jsx';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const AdminDashboard = () => {
  const { token } = useAdminAuth();
  const navigate = useNavigate();

  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState('');

  // Metrics Data
  const [orderSummary, setOrderSummary] = useState(null);
  const [recentOrders, setRecentOrders] = useState([]);
  const [totalCustomers, setTotalCustomers] = useState(0);
  const [recentCustomers, setRecentCustomers] = useState([]);
  const [totalProducts, setTotalProducts] = useState(0);
  const [totalCategories, setTotalCategories] = useState(0);
  const [orderStatusCounts, setOrderStatusCounts] = useState({
    pending: 0,
    confirmed: 0,
    shipped: 0,
    delivered: 0,
    cancelled: 0
  });

  const fetchDashboardData = async (showRefreshSpinner = false) => {
    if (showRefreshSpinner) setIsRefreshing(true);
    else setIsLoading(true);
    setError('');

    try {
      // 1. Fetch Orders and Summary
      const ordersPromise = fetch(`${API_BASE_URL}/orders/list.php?limit=8&sort_by=created_at&sort_order=desc`, {
        headers: { 'Authorization': `Bearer ${token}` }
      }).then(res => res.json()).catch(() => ({ status: false }));

      // 2. Fetch Customers
      const usersPromise = fetch(`${API_BASE_URL}/users/list.php?limit=5`, {
        headers: { 'Authorization': `Bearer ${token}` }
      }).then(res => res.json()).catch(() => ({ status: false }));

      // 3. Fetch Products Count
      const productsPromise = fetch(`${API_BASE_URL}/product/list.php?limit=1`, {
        headers: { 'Authorization': `Bearer ${token}` }
      }).then(res => res.json()).catch(() => ({ status: false }));

      // 4. Fetch Categories Count
      const categoriesPromise = fetch(`${API_BASE_URL}/category/list.php?limit=100`, {
        headers: { 'Authorization': `Bearer ${token}` }
      }).then(res => res.json()).catch(() => ({ status: false }));

      const [ordersRes, usersRes, productsRes, categoriesRes] = await Promise.all([
        ordersPromise,
        usersPromise,
        productsPromise,
        categoriesPromise
      ]);

      // Process Orders
      if (ordersRes.status && ordersRes.data) {
        setOrderSummary(ordersRes.data.summary || null);
        const ordersList = ordersRes.data.orders || [];
        setRecentOrders(ordersList);

        // Count statuses
        const counts = { pending: 0, confirmed: 0, shipped: 0, delivered: 0, cancelled: 0 };
        ordersList.forEach(o => {
          const st = (o.order_status || '').toLowerCase();
          if (counts[st] !== undefined) counts[st]++;
        });
        setOrderStatusCounts(counts);
      }

      // Process Users
      if (usersRes.status && usersRes.data) {
        setTotalCustomers(usersRes.data.pagination?.total_records || (usersRes.data.users?.length || 0));
        setRecentCustomers(usersRes.data.users || []);
      }

      // Process Products
      if (productsRes.status && productsRes.data) {
        setTotalProducts(productsRes.data.pagination?.total_records || 0);
      }

      // Process Categories
      if (categoriesRes.status && categoriesRes.data) {
        setTotalCategories(categoriesRes.data.categories?.length || 0);
      }

    } catch (err) {
      console.error('Error loading dashboard metrics:', err);
      setError('Failed to fetch some real-time metrics. Please refresh.');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  };

  useEffect(() => {
    fetchDashboardData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const getOrderStatusBadge = (status) => {
    switch ((status || '').toLowerCase()) {
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
    switch ((status || '').toLowerCase()) {
      case 'paid':
      case 'success':
      case 'completed':
        return 'admin-badge-active';
      case 'failed':
      case 'cancelled':
      case 'refunded':
        return 'admin-badge-danger';
      case 'pending':
      default:
        return 'admin-badge-warning';
    }
  };

  const grandTotal = parseFloat(orderSummary?.grand_total || 0);
  const totalOrdersCount = orderSummary?.total_orders || recentOrders.length || 0;
  const discountsGiven = (parseFloat(orderSummary?.product_discount_amount || 0) + parseFloat(orderSummary?.coupon_discount_amount || 0));

  return (
    <div className="admin-page-container" style={{ animation: 'fadeIn 0.3s ease-in-out' }}>
      
      {/* Top Header & Refresh Bar */}
      <div className="admin-header-row" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '28px', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <span style={{ 
              background: 'linear-gradient(135deg, #A049A3 0%, #C86395 100%)', 
              color: '#fff', 
              fontSize: '0.74rem', 
              fontWeight: '700', 
              padding: '3px 10px', 
              borderRadius: '20px', 
              letterSpacing: '0.04em' 
            }}>
              LIVE OVERVIEW
            </span>
            <span style={{ fontSize: '0.82rem', color: '#6b7280' }}>Boutique Analytics</span>
          </div>
          <h1 style={{ margin: '6px 0 0 0', fontSize: '1.85rem', fontWeight: '800', color: '#1f2937', letterSpacing: '-0.02em' }}>
            Executive Dashboard
          </h1>
          <p style={{ margin: '4px 0 0 0', color: '#6b7280', fontSize: '0.92rem' }}>
            Real-time performance insights, orders fulfillment, customer activity, and revenue tracking
          </p>
        </div>

        <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
          <button
            type="button"
            onClick={() => fetchDashboardData(true)}
            className="admin-btn admin-btn-secondary"
            disabled={isRefreshing}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', cursor: 'pointer', padding: '10px 18px', borderRadius: '10px' }}
          >
            <svg 
              width="16" 
              height="16" 
              viewBox="0 0 24 24" 
              fill="none" 
              stroke="currentColor" 
              strokeWidth="2"
              style={{ animation: isRefreshing ? 'spin 1s linear infinite' : 'none' }}
            >
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
            </svg>
            Refresh Metrics
          </button>

          <Link
            to="/admin/orders"
            className="admin-btn admin-btn-primary"
            style={{ padding: '10px 20px', borderRadius: '10px', textDecoration: 'none', fontWeight: '700' }}
          >
            Manage Orders →
          </Link>
        </div>
      </div>

      {error && (
        <div style={{ background: '#fee2e2', color: '#991b1b', padding: '14px 18px', borderRadius: '10px', marginBottom: '24px', fontSize: '0.9rem', border: '1px solid #fecdd3' }}>
          {error}
        </div>
      )}

      {/* ==================== 4 HERO KPI CARDS (Inspired by Reference Design) ==================== */}
      <div style={{ 
        display: 'grid', 
        gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', 
        gap: '22px', 
        marginBottom: '28px' 
      }}>
        
        {/* KPI 1: Total Revenue */}
        <AdminKpiCard
          variant="purple"
          title="Total Revenue"
          value={`₹${grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`}
          badgeText="Live Sales"
          icon={<span style={{ fontSize: '1.25rem', fontWeight: '800' }}>₹</span>}
          footerLeft={<span>✓ All Paid Invoices</span>}
          footerRight={<span>Subtotal: ₹{parseFloat(orderSummary?.subtotal || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })}</span>}
        />

        {/* KPI 2: Total Orders */}
        <AdminKpiCard
          variant="blue"
          title="Total Orders"
          value={`${totalOrdersCount.toLocaleString('en-IN')} Orders`}
          subtitle={`${orderStatusCounts.confirmed + orderStatusCounts.shipped} Active in Pipeline`}
          badgeText="Fulfillment"
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
              <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
          }
          footerLeft={<span>Pending: <strong>{orderStatusCounts.pending}</strong></span>}
          footerRight={<span>Delivered: <strong>{orderStatusCounts.delivered}</strong></span>}
          onClick={() => navigate('/admin/orders')}
        />

        {/* KPI 3: Registered Customers */}
        <AdminKpiCard
          variant="pink"
          title="Boutique Customers"
          value={`${totalCustomers.toLocaleString('en-IN')} Patrons`}
          badgeText="VIP Members"
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
          }
          footerLeft={<span>Registered Clients</span>}
          footerRight={
            <Link to="/admin/customers" style={{ color: '#ffffff', textDecoration: 'underline', fontWeight: '700' }} onClick={(e) => e.stopPropagation()}>
              Directory →
            </Link>
          }
        />

        {/* KPI 4: Active Catalog & Inventory */}
        <AdminKpiCard
          variant="orange"
          title="Store Products"
          value={`${totalProducts.toLocaleString('en-IN')} Styles`}
          badgeText={`${totalCategories} Categories`}
          icon={
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
          }
          footerLeft={<span>Active Inventory</span>}
          footerRight={
            <Link to="/admin/products" style={{ color: '#ffffff', textDecoration: 'underline', fontWeight: '700' }} onClick={(e) => e.stopPropagation()}>
              Catalog →
            </Link>
          }
        />

      </div>

      {/* ==================== MIDDLE SECTION: FINANCIAL & OPERATIONS CARDS ==================== */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '24px', marginBottom: '28px' }}>
        
        {/* Financial Highlights Card */}
        <div style={{
          background: '#ffffff',
          borderRadius: '18px',
          border: '1.5px solid #ebd7ed',
          padding: '24px',
          boxShadow: '0 4px 20px -2px rgba(160, 73, 163, 0.08)'
        }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '18px' }}>
            <h3 style={{ margin: 0, fontSize: '1.1rem', fontWeight: '800', color: '#111827' }}>
              Financial Highlights
            </h3>
            <span style={{ fontSize: '0.76rem', color: '#6b7280', background: '#f3f4f6', padding: '3px 8px', borderRadius: '6px' }}>
              Audit Breakdown
            </span>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '0.92rem' }}>
              <span style={{ color: '#4b5563' }}>Product Discounts Given</span>
              <strong style={{ color: '#ea580c' }}>-₹{parseFloat(orderSummary?.product_discount_amount || 0).toFixed(2)}</strong>
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '0.92rem' }}>
              <span style={{ color: '#4b5563' }}>Coupons Savings Granted</span>
              <strong style={{ color: '#16a34a' }}>-₹{parseFloat(orderSummary?.coupon_discount_amount || 0).toFixed(2)}</strong>
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '0.92rem' }}>
              <span style={{ color: '#4b5563' }}>Taxes & GST Collected</span>
              <strong style={{ color: '#1f2937' }}>₹{parseFloat(orderSummary?.tax_amount || 0).toFixed(2)}</strong>
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '0.92rem' }}>
              <span style={{ color: '#4b5563' }}>COD Handling Fees</span>
              <strong style={{ color: '#b45309' }}>+₹{parseFloat(orderSummary?.cod_charge || 0).toFixed(2)}</strong>
            </div>

            <div style={{
              marginTop: '8px',
              paddingTop: '14px',
              borderTop: '1.5px dashed #e5e7eb',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center'
            }}>
              <div>
                <span style={{ fontSize: '0.78rem', textTransform: 'uppercase', fontWeight: '700', color: '#6b7280', display: 'block' }}>
                  Net Realized Sales
                </span>
                <span style={{ fontSize: '1.25rem', fontWeight: '800', color: 'var(--primary-color, #6b21a8)' }}>
                  ₹{grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                </span>
              </div>
              <span style={{ fontSize: '0.8rem', color: '#059669', background: '#ecfdf5', padding: '4px 10px', borderRadius: '8px', fontWeight: '700' }}>
                ₹{discountsGiven.toFixed(0)} saved by buyers
              </span>
            </div>
          </div>
        </div>

        {/* Quick Operations & Navigation Card */}
        <div style={{
          background: '#ffffff',
          borderRadius: '18px',
          border: '1.5px solid #ebd7ed',
          padding: '24px',
          boxShadow: '0 4px 20px -2px rgba(160, 73, 163, 0.08)'
        }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '18px' }}>
            <h3 style={{ margin: 0, fontSize: '1.1rem', fontWeight: '800', color: '#111827' }}>
              Operations & Shortcuts
            </h3>
            <span style={{ fontSize: '0.76rem', color: '#6b7280', background: '#f3f4f6', padding: '3px 8px', borderRadius: '6px' }}>
              Management
            </span>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '12px' }}>
            <button
              type="button"
              onClick={() => navigate('/admin/orders')}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                padding: '14px 16px',
                borderRadius: '12px',
                background: '#fdfafd',
                border: '1.5px solid #ebd7ed',
                cursor: 'pointer',
                textAlign: 'left',
                transition: 'all 0.2s ease'
              }}
            >
              <div style={{ width: '36px', height: '36px', borderRadius: '8px', background: '#F6EDF6', color: '#A049A3', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '700' }}>
                📦
              </div>
              <div>
                <strong style={{ display: 'block', fontSize: '0.88rem', color: '#111827' }}>Orders Desk</strong>
                <span style={{ fontSize: '0.75rem', color: '#6b7280' }}>Track and fulfill</span>
              </div>
            </button>

            <button
              type="button"
              onClick={() => navigate('/admin/products')}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                padding: '14px 16px',
                borderRadius: '12px',
                background: '#fdfafd',
                border: '1.5px solid #ebd7ed',
                cursor: 'pointer',
                textAlign: 'left',
                transition: 'all 0.2s ease'
              }}
            >
              <div style={{ width: '36px', height: '36px', borderRadius: '8px', background: '#fef3c7', color: '#b45309', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '700' }}>
                👗
              </div>
              <div>
                <strong style={{ display: 'block', fontSize: '0.88rem', color: '#111827' }}>Catalogue</strong>
                <span style={{ fontSize: '0.75rem', color: '#6b7280' }}>Dresses & Sarees</span>
              </div>
            </button>

            <button
              type="button"
              onClick={() => navigate('/admin/variants')}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                padding: '14px 16px',
                borderRadius: '12px',
                background: '#fdfafd',
                border: '1.5px solid #ebd7ed',
                cursor: 'pointer',
                textAlign: 'left',
                transition: 'all 0.2s ease'
              }}
            >
              <div style={{ width: '36px', height: '36px', borderRadius: '8px', background: '#ecfdf5', color: '#059669', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '700' }}>
                📊
              </div>
              <div>
                <strong style={{ display: 'block', fontSize: '0.88rem', color: '#111827' }}>Stock & Sizes</strong>
                <span style={{ fontSize: '0.75rem', color: '#6b7280' }}>Inventory control</span>
              </div>
            </button>

            <button
              type="button"
              onClick={() => navigate('/admin/customers')}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                padding: '14px 16px',
                borderRadius: '12px',
                background: '#fdfafd',
                border: '1.5px solid #ebd7ed',
                cursor: 'pointer',
                textAlign: 'left',
                transition: 'all 0.2s ease'
              }}
            >
              <div style={{ width: '36px', height: '36px', borderRadius: '8px', background: '#fdf2f8', color: '#db2777', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '700' }}>
                👥
              </div>
              <div>
                <strong style={{ display: 'block', fontSize: '0.88rem', color: '#111827' }}>Customers</strong>
                <span style={{ fontSize: '0.75rem', color: '#6b7280' }}>Profiles & addresses</span>
              </div>
            </button>
          </div>

          <div style={{ marginTop: '16px', padding: '12px 14px', background: '#f8fafc', borderRadius: '10px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <span style={{ fontSize: '0.82rem', color: '#475569' }}>
              Need to add a new dress or variant?
            </span>
            <Link to="/admin/products" style={{ fontSize: '0.82rem', fontWeight: '700', color: 'var(--primary-color, #6b21a8)', textDecoration: 'none' }}>
              + Add Product
            </Link>
          </div>
        </div>

      </div>

      {/* ==================== BOTTOM SECTION: RECENT ORDERS & CUSTOMERS ==================== */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(360px, 1fr))', gap: '24px' }}>
        
        {/* Recent Orders List Card */}
        <div style={{
          background: '#ffffff',
          borderRadius: '18px',
          border: '1.5px solid #ebd7ed',
          boxShadow: '0 4px 20px -2px rgba(160, 73, 163, 0.08)',
          overflow: 'hidden'
        }}>
          <div style={{ padding: '20px 24px', borderBottom: '1px solid #f3f4f6', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <h3 style={{ margin: 0, fontSize: '1.1rem', fontWeight: '800', color: '#111827' }}>
                Recent Boutique Orders
              </h3>
              <p style={{ margin: '2px 0 0 0', color: '#6b7280', fontSize: '0.82rem' }}>
                Latest purchase transactions placed by customers
              </p>
            </div>
            <Link to="/admin/orders" className="admin-btn admin-btn-secondary" style={{ fontSize: '0.78rem', padding: '6px 12px', textDecoration: 'none' }}>
              View All Orders
            </Link>
          </div>

          <div style={{ overflowX: 'auto' }}>
            {isLoading ? (
              <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading orders...</div>
            ) : recentOrders.length === 0 ? (
              <div style={{ padding: '40px', textAlign: 'center', color: '#9ca3af' }}>No recent orders found.</div>
            ) : (
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.88rem' }}>
                <thead>
                  <tr style={{ background: '#f9fafb', borderBottom: '1px solid #e5e7eb', color: '#4b5563', textAlign: 'left' }}>
                    <th style={{ padding: '12px 18px' }}>Order #</th>
                    <th style={{ padding: '12px 18px' }}>Customer</th>
                    <th style={{ padding: '12px 18px' }}>Total</th>
                    <th style={{ padding: '12px 18px' }}>Status</th>
                    <th style={{ padding: '12px 18px' }}>Payment</th>
                  </tr>
                </thead>
                <tbody>
                  {recentOrders.slice(0, 6).map((order, idx) => (
                    <tr key={idx} style={{ borderBottom: '1px solid #f3f4f6' }}>
                      <td style={{ padding: '12px 18px' }}>
                        <Link to={`/admin/orders`} style={{ fontWeight: '700', color: 'var(--primary-color, #6b21a8)', textDecoration: 'none' }}>
                          #{order.order_number}
                        </Link>
                        <div style={{ fontSize: '0.74rem', color: '#9ca3af' }}>
                          {order.timeline?.placed_at || order.timeline?.created_at ? new Date(order.timeline.placed_at || order.timeline.created_at).toLocaleDateString('en-IN') : 'Recent'}
                        </div>
                      </td>
                      <td style={{ padding: '12px 18px' }}>
                        <div style={{ fontWeight: '600', color: '#111827' }}>{order.user?.name || 'Guest'}</div>
                        <div style={{ fontSize: '0.74rem', color: '#6b7280' }}>+{order.user?.mobile}</div>
                      </td>
                      <td style={{ padding: '12px 18px', fontWeight: '700', color: '#111827' }}>
                        ₹{parseFloat(order.amounts?.grand_total || order.grand_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                      </td>
                      <td style={{ padding: '12px 18px' }}>
                        <span className={`admin-badge ${getOrderStatusBadge(order.order_status)}`} style={{ textTransform: 'uppercase', fontSize: '0.7rem', padding: '3px 8px', borderRadius: '12px' }}>
                          {order.order_status}
                        </span>
                      </td>
                      <td style={{ padding: '12px 18px' }}>
                        <span className={`admin-badge ${getPaymentStatusBadge(order.payment?.status)}`} style={{ textTransform: 'uppercase', fontSize: '0.7rem' }}>
                          {order.payment?.method || 'Prepaid'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>

        {/* Recent Customers Card */}
        <div style={{
          background: '#ffffff',
          borderRadius: '18px',
          border: '1.5px solid #ebd7ed',
          boxShadow: '0 4px 20px -2px rgba(160, 73, 163, 0.08)',
          overflow: 'hidden'
        }}>
          <div style={{ padding: '20px 24px', borderBottom: '1px solid #f3f4f6', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <h3 style={{ margin: 0, fontSize: '1.1rem', fontWeight: '800', color: '#111827' }}>
                New Customers
              </h3>
              <p style={{ margin: '2px 0 0 0', color: '#6b7280', fontSize: '0.82rem' }}>
                Recently registered shoppers & accounts
              </p>
            </div>
            <Link to="/admin/customers" className="admin-btn admin-btn-secondary" style={{ fontSize: '0.78rem', padding: '6px 12px', textDecoration: 'none' }}>
              Directory
            </Link>
          </div>

          <div style={{ padding: '16px 20px' }}>
            {isLoading ? (
              <div style={{ padding: '30px', textAlign: 'center', color: '#6b7280' }}>Loading customers...</div>
            ) : recentCustomers.length === 0 ? (
              <div style={{ padding: '30px', textAlign: 'center', color: '#9ca3af' }}>No customers found.</div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                {recentCustomers.slice(0, 5).map((user, idx) => {
                  const initial = (user.name || user.email || 'U').charAt(0).toUpperCase();
                  return (
                    <div 
                      key={idx}
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '10px 14px',
                        borderRadius: '10px',
                        background: '#fdfafd',
                        border: '1px solid #f3e8f3'
                      }}
                    >
                      <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <div style={{
                          width: '38px',
                          height: '38px',
                          borderRadius: '50%',
                          background: 'linear-gradient(135deg, #A049A3 0%, #C86395 100%)',
                          color: '#fff',
                          fontWeight: '800',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          fontSize: '0.92rem'
                        }}>
                          {initial}
                        </div>
                        <div>
                          <div style={{ fontWeight: '700', fontSize: '0.9rem', color: '#111827' }}>
                            {user.name || 'Boutique Customer'}
                          </div>
                          <div style={{ fontSize: '0.78rem', color: '#6b7280' }}>
                            {user.mobile ? `+${user.mobile}` : (user.email || 'No phone')}
                          </div>
                        </div>
                      </div>

                      <span className={`admin-badge ${user.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`} style={{ fontSize: '0.72rem', textTransform: 'uppercase' }}>
                        {user.status || 'Active'}
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

      </div>

    </div>
  );
};

export default AdminDashboard;
