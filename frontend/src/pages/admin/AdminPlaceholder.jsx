import React from 'react';

const moduleMetaData = {
  dashboard: {
    title: 'Dashboard',
    category: 'Overview',
    description: 'Real-time overview of sales, orders, customer activity, and store performance metrics.',
    stats: [
      { label: 'Total Revenue', value: '₹1,24,500', change: '+14.2%', isPositive: true },
      { label: 'Total Orders', value: '342', change: '+8.5%', isPositive: true },
      { label: 'Active Customers', value: '1,280', change: '+5.1%', isPositive: true },
      { label: 'Pending Shipments', value: '18', change: '-2.4%', isPositive: false },
    ]
  },
  reports: {
    title: 'Reports & Analytics',
    category: 'Overview',
    description: 'Detailed analytics, sales breakdown, inventory flow, and financial summaries.',
    stats: [
      { label: 'Monthly Sales', value: '₹3,45,200', change: '+12.4%', isPositive: true },
      { label: 'Avg Order Value', value: '₹2,850', change: '+3.1%', isPositive: true },
      { label: 'Return Rate', value: '1.4%', change: '-0.5%', isPositive: true },
    ]
  },
  products: {
    title: 'Products Catalogue',
    category: 'Catalogue',
    description: 'Manage boutique apparel, variants, stock inventory, pricing, and tags.',
    stats: [
      { label: 'Total Products', value: '154', change: '+12 new', isPositive: true },
      { label: 'In Stock', value: '138', change: '89%', isPositive: true },
      { label: 'Low Stock Alert', value: '16', change: 'Requires Action', isPositive: false },
    ]
  },
  categories: {
    title: 'Product Categories',
    category: 'Catalogue',
    description: 'Organize sarees, lehengas, kurtis, gowns, and custom boutique collections.',
    stats: [
      { label: 'Active Categories', value: '12', change: 'Main', isPositive: true },
      { label: 'Sub-categories', value: '48', change: 'Active', isPositive: true },
    ]
  },
  'banners-coupons': {
    title: 'Banners & Coupons',
    category: 'Catalogue',
    description: 'Manage promotional hero banners, seasonal discount codes, and offer popups.',
    stats: [
      { label: 'Active Banners', value: '4', change: 'Homepage', isPositive: true },
      { label: 'Active Coupons', value: '6', change: 'Promotions', isPositive: true },
      { label: 'Redemptions', value: '184', change: 'This Month', isPositive: true },
    ]
  },
  suppliers: {
    title: 'Suppliers & Vendors',
    category: 'Catalogue',
    description: 'Vendor details, fabric suppliers, artisan contacts, and supply chain records.',
    stats: [
      { label: 'Active Suppliers', value: '18', change: 'Verified', isPositive: true },
      { label: 'Pending POs', value: '5', change: 'Processing', isPositive: true },
    ]
  },
  purchases: {
    title: 'Purchase Orders',
    category: 'Catalogue',
    description: 'Track incoming fabric stock, artisan orders, manufacturing invoices, and stock receipts.',
    stats: [
      { label: 'Total Purchases', value: '₹84,500', change: 'This Month', isPositive: true },
      { label: 'Received Orders', value: '24', change: 'Completed', isPositive: true },
    ]
  },
  pos: {
    title: 'POS Sale (In-Store)',
    category: 'Sales',
    description: 'Point of sale billing terminal for boutique walk-in customers, instant invoices, and barcode scanner integration.',
    stats: [
      { label: 'Today POS Sales', value: '₹18,400', change: '6 Receipts', isPositive: true },
      { label: 'Cash Coll.', value: '₹7,200', change: 'Terminal 1', isPositive: true },
      { label: 'UPI / Card', value: '₹11,200', change: 'Terminal 1', isPositive: true },
    ]
  },
  orders: {
    title: 'Customer Orders',
    category: 'Sales',
    description: 'Manage online orders, payment verification, fulfillment status, and courier dispatches.',
    stats: [
      { label: 'New Orders', value: '28', change: 'To Process', isPositive: true },
      { label: 'In Transit', value: '42', change: 'Shipped', isPositive: true },
      { label: 'Delivered', value: '272', change: 'This Month', isPositive: true },
    ]
  },
  customers: {
    title: 'Customers Directory',
    category: 'Sales',
    description: 'Customer profiles, measurement records, order histories, and VIP loyalty tiers.',
    stats: [
      { label: 'Total Registered', value: '1,420', change: '+45 this week', isPositive: true },
      { label: 'VIP Members', value: '185', change: 'Gold Tier', isPositive: true },
    ]
  },
  payments: {
    title: 'Payments & Transactions',
    category: 'Sales',
    description: 'Razorpay / UPI payment logs, gateway settlements, failed transactions, and cash ledgers.',
    stats: [
      { label: 'Successful Payments', value: '₹3,82,400', change: '99.2%', isPositive: true },
      { label: 'Pending Settlement', value: '₹14,200', change: 'T+1', isPositive: true },
    ]
  },
  returns: {
    title: 'Returns & Refunds',
    category: 'Sales',
    description: 'Manage customer return requests, quality inspection, store credits, and refund approvals.',
    stats: [
      { label: 'Open Return Requests', value: '3', change: 'Pending', isPositive: false },
      { label: 'Refunds Processed', value: '₹4,500', change: 'This Month', isPositive: true },
    ]
  },
  whatsapp: {
    title: 'WhatsApp Engagement',
    category: 'Engagement',
    description: 'Send automated order notifications, WhatsApp catalog shares, promotional broadcasts, and support chats.',
    stats: [
      { label: 'Messages Sent', value: '1,840', change: 'This Month', isPositive: true },
      { label: 'Broadcast Campaign', value: 'Active', change: 'Festive Offer', isPositive: true },
      { label: 'Delivery Rate', value: '98.5%', change: 'High', isPositive: true },
    ]
  }
};

const AdminPlaceholder = ({ moduleKey = 'dashboard' }) => {
  const info = moduleMetaData[moduleKey] || {
    title: 'Admin Module',
    category: 'System',
    description: 'Vivisha Boutique Admin Module Shell.',
    stats: []
  };

  return (
    <div className="admin-module-container">
      {/* Page Header */}
      <div className="admin-page-header">
        <div>
          <div className="admin-breadcrumb">
            <span>Admin</span> &gt; <span>{info.category}</span> &gt; <span className="active">{info.title}</span>
          </div>
          <h1 className="admin-module-title">{info.title}</h1>
          <p className="admin-module-desc">{info.description}</p>
        </div>
        <div className="admin-header-actions">
          <button className="admin-btn admin-btn-secondary" onClick={() => alert('Export report action (Mock)')}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Export
          </button>
          <button className="admin-btn admin-btn-primary" onClick={() => alert(`Add New item in ${info.title} (Mock)`)}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add New
          </button>
        </div>
      </div>

      {/* Summary Stat Cards */}
      {info.stats && info.stats.length > 0 && (
        <div className="admin-stats-grid">
          {info.stats.map((stat, idx) => (
            <div className="admin-stat-card" key={idx}>
              <div className="stat-label">{stat.label}</div>
              <div className="stat-value">{stat.value}</div>
              <div className={`stat-change ${stat.isPositive ? 'positive' : 'neutral'}`}>
                {stat.change}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Placeholder Shell Card */}
      <div className="admin-card admin-placeholder-card">
        <div className="placeholder-icon-badge">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#A049A3" strokeWidth="1.8">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
        </div>
        <h3 className="placeholder-heading">{info.title} UI Shell Ready</h3>
        <p className="placeholder-text">
          The layout, routing, and navigation state for <strong>{info.title}</strong> are configured. 
          Ready for backend PHP API integration and component feature development.
        </p>
        <div className="placeholder-tags">
          <span className="badge">Route: /admin/{moduleKey}</span>
          <span className="badge">Status: UI Shell Ready</span>
          <span className="badge">Module: {info.category}</span>
        </div>
      </div>
    </div>
  );
};

export default AdminPlaceholder;
