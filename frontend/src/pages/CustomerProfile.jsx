import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';
import RefundRequestModal from '../components/RefundRequestModal.jsx';

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

  return {
    productName,
    variantName,
    sizeName,
    colorName,
    sku,
    hsnCode,
    sellingPrice,
    quantity,
    gstRate,
    cgstAmount,
    sgstAmount,
    igstAmount,
    taxAmount,
    lineTotal,
    imageUrl
  };
};

const CustomerProfile = ({ defaultTab = 'profile' }) => {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout, token, isAuthenticated, updateUser } = useAuth();

  const [activeTab, setActiveTab] = useState(() => {
    if (location.pathname === '/addresses') return 'addresses';
    if (location.pathname === '/orders') return 'orders';
    return defaultTab || 'profile';
  });

  useEffect(() => {
    if (location.pathname === '/addresses') {
      setActiveTab('addresses');
    } else if (location.pathname === '/orders') {
      setActiveTab('orders');
    } else if (location.pathname === '/profile' || location.pathname === '/dashboard') {
      setActiveTab('profile');
    }
  }, [location.pathname]);

  const [isEditingProfile, setIsEditingProfile] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  const [profileData, setProfileData] = useState({
    fullName: user?.name || '',
    email: user?.email || '',
    mobile: user?.phone || '',
    dob: ''
  });

  const [editFormData, setEditFormData] = useState({ ...profileData });

  // Address State
  const [savedAddresses, setSavedAddresses] = useState([]);
  const [isAddressLoading, setIsAddressLoading] = useState(false);
  const [isAddressDrawerOpen, setIsAddressDrawerOpen] = useState(false);
  const [editingAddressId, setEditingAddressId] = useState(null);
  const [addressError, setAddressError] = useState('');
  const [addressSuccess, setAddressSuccess] = useState('');

  const [addressForm, setAddressForm] = useState({
    address_type: 'home',
    door_no: '',
    street: '',
    area: '',
    city: '',
    district: '',
    state: '',
    pincode: '',
    landmark: '',
    is_default: 0
  });

  // Orders State
  const [orders, setOrders] = useState([]);
  const [isOrdersLoading, setIsOrdersLoading] = useState(false);
  const [orderFilterStatus, setOrderFilterStatus] = useState('all');
  const [orderSearchQuery, setOrderSearchQuery] = useState('');
  const [selectedOrderDetails, setSelectedOrderDetails] = useState(null);
  const [isOrderModalOpen, setIsOrderModalOpen] = useState(false);
  const [isOrderDetailsLoading, setIsOrderDetailsLoading] = useState(false);

  // Refund State
  const [isRefundModalOpen, setIsRefundModalOpen] = useState(false);
  const [selectedRefundOrder, setSelectedRefundOrder] = useState(null);
  const [refundRequests, setRefundRequests] = useState(() => {
    try {
      const saved = localStorage.getItem('vivisha_customer_refund_requests');
      return saved ? JSON.parse(saved) : {};
    } catch {
      return {};
    }
  });

  // Backend Refund Eligibility Checker
  const isOrderEligibleForRefund = (order) => {
    if (!order) return false;
    const paymentStatus = (order.payment?.status || order.payment_status || '').toLowerCase();
    const orderStatus = (order.order_status || '').toLowerCase();

    // 1. Cannot be already refunded (backend rule from backend/api/refunds/refund.php)
    if (paymentStatus === 'refunded' || orderStatus === 'refunded') {
      return false;
    }

    // 2. Payment status must be 'paid' or 'partially_refunded' (backend rule)
    if (paymentStatus !== 'paid' && paymentStatus !== 'partially_refunded') {
      return false;
    }

    return true;
  };

  const handleOpenRefundModal = (order) => {
    setSelectedRefundOrder(order);
    setIsRefundModalOpen(true);
  };

  const handleRefundSubmitted = (orderId, requestData) => {
    setRefundRequests(prev => {
      const updated = { ...prev, [orderId]: requestData };
      try {
        localStorage.setItem('vivisha_customer_refund_requests', JSON.stringify(updated));
      } catch (e) {
        console.error('Failed to save refund request to localStorage:', e);
      }
      return updated;
    });
  };

  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/login');
      return;
    }

    const fetchProfile = async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/users/view.php`, {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        });
        const data = await response.json();

        if (data.status) {
          const u = data.data.user;
          const pData = {
            fullName: u.name || '',
            email: u.email || '',
            mobile: u.mobile || '',
            dob: u.date_of_birth || ''
          };
          setProfileData(pData);
          setEditFormData(pData);
        } else {
          setErrorMsg(data.message || 'Failed to load profile');
        }
      } catch (err) {
        setErrorMsg('Network error loading profile');
        console.error(err);
      } finally {
        setIsLoading(false);
      }
    };

    fetchProfile();
  }, [isAuthenticated, token, navigate]);

  const fetchAddresses = async () => {
    if (!token) return;
    setIsAddressLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/address/list.php`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const data = await response.json();
      if (data.status) {
        setSavedAddresses(data.data.addresses || []);
      }
    } catch (err) {
      console.error('Error fetching addresses:', err);
    } finally {
      setIsAddressLoading(false);
    }
  };

  const fetchOrders = async () => {
    if (!token) return;
    setIsOrdersLoading(true);
    try {
      let url = `${API_BASE_URL}/orders/list.php?limit=50`;
      if (orderFilterStatus && orderFilterStatus !== 'all') {
        url += `&order_status=${encodeURIComponent(orderFilterStatus)}`;
      }
      if (orderSearchQuery.trim()) {
        url += `&q=${encodeURIComponent(orderSearchQuery.trim())}`;
      }

      const response = await fetch(url, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const data = await response.json();
      if (data.status && data.data && Array.isArray(data.data.orders)) {
        setOrders(data.data.orders);
      } else {
        setOrders([]);
      }
    } catch (err) {
      console.error('Error fetching orders:', err);
      setOrders([]);
    } finally {
      setIsOrdersLoading(false);
    }
  };

  useEffect(() => {
    if (isAuthenticated && token) {
      fetchAddresses();
      fetchOrders();
    }
  }, [isAuthenticated, token]);

  useEffect(() => {
    if (isAuthenticated && token && (activeTab === 'orders' || activeTab === 'track')) {
      fetchOrders();
    }
  }, [orderFilterStatus, orderSearchQuery, activeTab]);

  const handleLogout = () => {
    logout();
    navigate('/');
  };

  const handleUpdateProfile = async (e) => {
    e.preventDefault();
    setErrorMsg('');
    setSuccessMsg('');

    const trimmedName = (editFormData.fullName || '').trim();
    if (!trimmedName) {
      setErrorMsg('Please enter your full name.');
      return;
    }
    if (trimmedName.length < 2) {
      setErrorMsg('Name must contain at least 2 characters.');
      return;
    }
    if (trimmedName.length > 100) {
      setErrorMsg('Name must not exceed 100 characters.');
      return;
    }
    if (!/^[\p{L}\s.'-]+$/u.test(trimmedName)) {
      setErrorMsg('Name contains invalid characters.');
      return;
    }

    const trimmedMobile = (editFormData.mobile || '').trim();
    if (!trimmedMobile) {
      setErrorMsg('Please enter your mobile number.');
      return;
    }
    if (!/^[6-9][0-9]{9}$/.test(trimmedMobile)) {
      setErrorMsg('Mobile number must be a valid 10-digit Indian mobile number.');
      return;
    }

    const trimmedEmail = (editFormData.email || '').trim();
    if (trimmedEmail) {
      if (!/^\S+@\S+\.\S+$/.test(trimmedEmail)) {
        setErrorMsg('Please enter a valid email address.');
        return;
      }
      if (trimmedEmail.length > 150) {
        setErrorMsg('Email must not exceed 150 characters.');
        return;
      }
    }

    setIsSaving(true);

    try {
      const payload = {
        name: trimmedName,
        mobile: trimmedMobile
      };

      if (trimmedEmail) payload.email = trimmedEmail;
      // Note: date_of_birth is protected by backend and cannot be updated

      const response = await fetch(`${API_BASE_URL}/users/update.php`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(payload)
      });
      const data = await response.json();

      if (data.status) {
        const u = data.data.user;
        const newProfileData = {
          fullName: u.name || '',
          email: u.email || '',
          mobile: u.mobile || '',
          dob: u.date_of_birth || ''
        };
        setProfileData(newProfileData);
        setEditFormData(newProfileData);
        setIsEditingProfile(false);
        setSuccessMsg('Profile updated successfully!');

        updateUser({
          name: u.name,
          email: u.email,
          phone: u.mobile
        });

        setTimeout(() => setSuccessMsg(''), 3000);
      } else {
        setErrorMsg(data.message || 'Failed to update profile');
      }
    } catch (err) {
      setErrorMsg('Network error updating profile');
      console.error(err);
    } finally {
      setIsSaving(false);
    }
  };

  // Address Handlers
  const handleOpenAddNewAddress = () => {
    setAddressForm({
      address_type: 'home',
      door_no: '',
      street: '',
      area: '',
      city: '',
      district: '',
      state: '',
      pincode: '',
      landmark: '',
      is_default: savedAddresses.length === 0 ? 1 : 0
    });
    setEditingAddressId(null);
    setAddressError('');
    setIsAddressDrawerOpen(true);
  };

  const handleEditAddress = (addr, e) => {
    if (e) e.stopPropagation();
    setAddressForm({
      address_type: addr.address_type || 'home',
      door_no: addr.door_no || '',
      street: addr.street || '',
      area: addr.area || '',
      city: addr.city || '',
      district: addr.district || '',
      state: addr.state || '',
      pincode: addr.pincode || '',
      landmark: addr.landmark || '',
      is_default: addr.is_default || 0
    });
    setEditingAddressId(addr.id);
    setAddressError('');
    setIsAddressDrawerOpen(true);
  };

  const handleSaveAddressSubmit = async (e) => {
    e.preventDefault();
    setAddressError('');

    const doorNo = addressForm.door_no ? addressForm.door_no.trim() : '';
    const street = addressForm.street ? addressForm.street.trim() : '';
    const area = addressForm.area ? addressForm.area.trim() : '';
    const city = addressForm.city ? addressForm.city.trim() : '';
    const district = addressForm.district ? addressForm.district.trim() : '';
    const state = addressForm.state ? addressForm.state.trim() : '';
    const pincode = addressForm.pincode ? addressForm.pincode.trim() : '';
    const landmark = addressForm.landmark ? addressForm.landmark.trim() : '';

    if (!doorNo) {
      setAddressError('Door number is required.');
      return;
    }
    if (doorNo.length < 1 || doorNo.length > 100) {
      setAddressError('Door number must be between 1 and 100 characters.');
      return;
    }

    if (!street) {
      setAddressError('Street is required.');
      return;
    }
    if (street.length < 2 || street.length > 150) {
      setAddressError('Street must be between 2 and 150 characters.');
      return;
    }

    if (!area) {
      setAddressError('Area is required.');
      return;
    }
    if (area.length < 2 || area.length > 150) {
      setAddressError('Area must be between 2 and 150 characters.');
      return;
    }

    if (!city) {
      setAddressError('City is required.');
      return;
    }
    if (city.length < 2 || city.length > 100) {
      setAddressError('City must be between 2 and 100 characters.');
      return;
    }

    if (district && district.length > 100) {
      setAddressError('District must not exceed 100 characters.');
      return;
    }

    if (!state) {
      setAddressError('State is required.');
      return;
    }
    if (state.length < 2 || state.length > 100) {
      setAddressError('State must be between 2 and 100 characters.');
      return;
    }

    if (!pincode) {
      setAddressError('Pincode is required.');
      return;
    }
    if (!/^[1-9][0-9]{5}$/.test(pincode)) {
      setAddressError('Please enter a valid 6-digit Indian pincode.');
      return;
    }

    if (landmark && landmark.length > 150) {
      setAddressError('Landmark must not exceed 150 characters.');
      return;
    }

    const isUpdate = Boolean(editingAddressId);
    const endpoint = isUpdate ? `${API_BASE_URL}/address/update.php` : `${API_BASE_URL}/address/create.php`;
    const httpMethod = isUpdate ? 'PUT' : 'POST';

    const payload = {
      address_type: addressForm.address_type,
      door_no: doorNo,
      street,
      area,
      city,
      district: district || null,
      state,
      pincode,
      landmark: landmark || null,
      is_default: addressForm.is_default ? 1 : 0
    };

    if (isUpdate) {
      payload.address_id = editingAddressId;
    }

    try {
      const response = await fetch(endpoint, {
        method: httpMethod,
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(payload)
      });
      const data = await response.json();
      if (data.status) {
        setIsAddressDrawerOpen(false);
        setEditingAddressId(null);
        setAddressSuccess(isUpdate ? 'Address updated successfully!' : 'Address added successfully!');
        setTimeout(() => setAddressSuccess(''), 3500);
        fetchAddresses();
      } else {
        setAddressError(data.message || 'Error saving address');
      }
    } catch (err) {
      console.error('Error saving address:', err);
      setAddressError('Network error saving address');
    }
  };

  const handleSelectDefaultAddress = async (id) => {
    try {
      const response = await fetch(`${API_BASE_URL}/address/select_address.php`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ address_id: id })
      });
      const data = await response.json();
      if (data.status) {
        setAddressSuccess('Default delivery address updated!');
        setTimeout(() => setAddressSuccess(''), 3000);
        fetchAddresses();
      } else {
        setAddressError(data.message || 'Error setting default address');
        setTimeout(() => setAddressError(''), 4000);
      }
    } catch (err) {
      console.error('Error setting default address:', err);
      setAddressError('Network error setting default address');
      setTimeout(() => setAddressError(''), 4000);
    }
  };

  const handleDeleteAddress = async (id, e) => {
    if (e) e.stopPropagation();
    if (!window.confirm('Are you sure you want to remove this delivery address?')) {
      return;
    }
    try {
      const response = await fetch(`${API_BASE_URL}/address/update.php`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          address_id: id,
          status: 'inactive'
        })
      });
      const data = await response.json();
      if (data.status) {
        setAddressSuccess('Address removed.');
        setTimeout(() => setAddressSuccess(''), 3000);
        fetchAddresses();
      } else {
        setAddressError(data.message || 'Error deleting address');
        setTimeout(() => setAddressError(''), 4000);
      }
    } catch (err) {
      console.error('Error deleting address:', err);
      setAddressError('Network error deleting address');
      setTimeout(() => setAddressError(''), 4000);
    }
  };

  // View Order Details
  const handleOpenOrderDetails = async (orderId) => {
    setIsOrderDetailsLoading(true);
    setIsOrderModalOpen(true);
    try {
      const response = await fetch(`${API_BASE_URL}/orders/view.php?id=${orderId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const data = await response.json();
      if (data.status && data.data?.order) {
        setSelectedOrderDetails(data.data.order);
      } else {
        setSelectedOrderDetails(null);
      }
    } catch (err) {
      console.error('Error fetching order view:', err);
      setSelectedOrderDetails(null);
    } finally {
      setIsOrderDetailsLoading(false);
    }
  };

  const getStatusBadgeClass = (status) => {
    switch (status) {
      case 'confirmed':
      case 'delivered':
        return 'badge-success';
      case 'shipped':
        return 'badge-info';
      case 'cancelled':
        return 'badge-danger';
      default:
        return 'badge-warning';
    }
  };

  const getStatusBadgeStyle = (status) => {
    switch (status) {
      case 'confirmed':
      case 'delivered':
        return { background: '#dcfce7', color: '#166534', border: '1px solid #86efac' };
      case 'shipped':
        return { background: '#e0f2fe', color: '#0369a1', border: '1px solid #7dd3fc' };
      case 'cancelled':
        return { background: '#fee2e2', color: '#991b1b', border: '1px solid #fca5a5' };
      case 'refunded':
        return { background: '#f3e8ff', color: '#6b21a8', border: '1px solid #d8b4fe' };
      case 'partially_refunded':
        return { background: '#fdf4ff', color: '#86198f', border: '1px solid #f0abfc' };
      default:
        return { background: '#fef3c7', color: '#92400e', border: '1px solid #fde68a' };
    }
  };

  const avatarInitial = (profileData.fullName || user?.name || 'U').charAt(0).toUpperCase();
  const firstName = (profileData.fullName || user?.name || 'Customer').split(' ')[0];

  const accountMenuItems = [
    {
      label: 'Personal Information',
      subtitle: 'Edit your name, phone, email & birthday',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
      ),
      path: '/profile',
      active: activeTab === 'profile'
    },
    {
      label: 'My Orders & Tracking',
      subtitle: `${orders.length} order${orders.length === 1 ? '' : 's'} placed`,
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
          <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
        </svg>
      ),
      path: '/orders',
      active: activeTab === 'orders' || activeTab === 'track'
    },
    {
      label: 'Saved Addresses',
      subtitle: `${savedAddresses.length} delivery address${savedAddresses.length === 1 ? '' : 'es'} saved`,
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
          <circle cx="12" cy="10" r="3"></circle>
        </svg>
      ),
      path: '/addresses',
      active: activeTab === 'addresses'
    },
    {
      label: 'My Wishlist',
      subtitle: 'View your saved favorite pieces',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
        </svg>
      ),
      path: '/wishlist',
      hasArrow: true
    },
    {
      label: 'Shopping Cart',
      subtitle: 'Review items in your bag',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <circle cx="9" cy="21" r="1"></circle>
          <circle cx="20" cy="21" r="1"></circle>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
        </svg>
      ),
      path: '/cart',
      hasArrow: true
    }
  ];

  // Render Saved Addresses Block
  const renderSavedAddressesView = () => (
    <div className="profile-section-block">
      <div className="section-block-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
        <div>
          <h3 className="section-block-title" style={{ margin: 0 }}>Saved Delivery Addresses</h3>
          <p style={{ margin: '4px 0 0 0', color: '#6b7280', fontSize: '0.85rem' }}>
            Manage and set your primary shipping destinations
          </p>
        </div>
        <button
          type="button"
          className="btn-add-address-action"
          onClick={handleOpenAddNewAddress}
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Add New Address
        </button>
      </div>

      {addressSuccess && <div className="profile-toast profile-toast-success">{addressSuccess}</div>}
      {addressError && <div className="profile-toast profile-toast-error">{addressError}</div>}

      {isAddressLoading ? (
        <div style={{ textAlign: 'center', padding: '30px', color: '#6b7280' }}>Loading addresses...</div>
      ) : savedAddresses.length === 0 ? (
        <div className="empty-address-box">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="1.5">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
          <h4>No Addresses Saved</h4>
          <p>Add a delivery address to speed up your checkout process.</p>
          <button
            type="button"
            className="btn-add-address-empty"
            onClick={handleOpenAddNewAddress}
          >
            + Add First Address
          </button>
        </div>
      ) : (
        <div className="addresses-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '24px' }}>
          {savedAddresses.map((addr) => (
            <div
              key={addr.id}
              className={`address-card ${addr.is_default === 1 ? 'is-default' : ''}`}
              style={{
                background: addr.is_default === 1 ? '#fdfafd' : '#ffffff',
                border: addr.is_default === 1 ? '1.5px solid var(--primary-color, #6b21a8)' : '1px solid #ebd7ed',
                borderRadius: '16px',
                padding: '24px',
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'space-between',
                gap: '18px',
                boxShadow: '0 2px 10px rgba(0,0,0,0.03)'
              }}
            >
              <div className="address-card-top" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '10px' }}>
                <span className="address-type-badge" style={{
                  background: '#F6EDF6',
                  color: 'var(--primary-color, #6b21a8)',
                  fontSize: '0.74rem',
                  fontWeight: '700',
                  padding: '4px 10px',
                  borderRadius: '6px',
                  letterSpacing: '0.05em'
                }}>
                  {(addr.address_type || 'home').toUpperCase()}
                </span>
                {addr.is_default === 1 && (
                  <span className="default-address-badge" style={{
                    background: '#ecfdf5',
                    color: '#059669',
                    border: '1px solid #a7f3d0',
                    fontSize: '0.74rem',
                    fontWeight: '700',
                    padding: '4px 10px',
                    borderRadius: '6px',
                    letterSpacing: '0.05em'
                  }}>
                    DEFAULT
                  </span>
                )}
              </div>

              <div className="address-card-body" style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                <h4 className="address-card-name" style={{ fontSize: '1.1rem', fontWeight: '700', color: '#111827', margin: '0 0 2px 0' }}>
                  {addr.user_name || profileData.fullName}
                </h4>

                <div style={{ color: '#374151', fontSize: '0.92rem', lineHeight: '1.65', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                  <div>{addr.door_no}, {addr.street}</div>
                  <div>{addr.area}, {addr.city}{addr.district ? `, ${addr.district}` : ''}</div>
                  <div>{addr.state} — <strong style={{ color: '#111827' }}>{addr.pincode}</strong></div>
                </div>

                {addr.landmark && (
                  <div style={{
                    fontSize: '0.84rem',
                    color: '#6b7280',
                    background: '#f9fafb',
                    border: '1px solid #f3f4f6',
                    padding: '8px 12px',
                    borderRadius: '6px',
                    marginTop: '4px',
                    lineHeight: '1.5'
                  }}>
                    <strong style={{ color: '#4b5563' }}>Landmark:</strong> {addr.landmark}
                  </div>
                )}

                <div style={{
                  fontSize: '0.88rem',
                  color: '#111827',
                  marginTop: '6px',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '8px'
                }}>
                  <span style={{ color: '#6b7280', fontSize: '0.82rem' }}>Phone:</span>
                  <strong style={{ letterSpacing: '0.02em' }}>+{addr.user_mobile || profileData.mobile}</strong>
                </div>
              </div>

              <div className="address-card-actions" style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                borderTop: '1px solid #f3e8f3',
                paddingTop: '16px',
                flexWrap: 'wrap'
              }}>
                <button
                  type="button"
                  className="btn-address-edit"
                  onClick={(e) => handleEditAddress(addr, e)}
                  style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '6px',
                    padding: '7px 16px',
                    borderRadius: '8px',
                    fontSize: '0.84rem',
                    fontWeight: '600'
                  }}
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                  </svg>
                  Edit
                </button>
                {addr.is_default !== 1 && (
                  <button
                    type="button"
                    className="btn-address-default"
                    onClick={() => handleSelectDefaultAddress(addr.id)}
                    style={{
                      padding: '7px 16px',
                      borderRadius: '8px',
                      fontSize: '0.84rem',
                      fontWeight: '600'
                    }}
                  >
                    Set as Default
                  </button>
                )}
                <button
                  type="button"
                  className="btn-address-delete"
                  style={{
                    background: 'none',
                    border: 'none',
                    color: '#dc2626',
                    fontSize: '0.84rem',
                    fontWeight: '600',
                    cursor: 'pointer',
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '4px',
                    padding: '6px 10px',
                    borderRadius: '6px',
                    marginLeft: 'auto'
                  }}
                  onClick={(e) => handleDeleteAddress(addr.id, e)}
                  title="Remove address"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                  </svg>
                  Remove
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );

  // Render Orders View
  const renderOrdersView = () => (
    <div className="profile-section-block">
      <div className="section-block-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <h3 className="section-block-title" style={{ margin: 0 }}>My Orders & Tracking</h3>
          <p style={{ margin: '4px 0 0 0', color: '#6b7280', fontSize: '0.85rem' }}>
            Track deliveries, view past orders and invoices
          </p>
        </div>

        {/* Filter Pills */}
        <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
          {['all', 'pending', 'confirmed', 'shipped', 'delivered', 'cancelled'].map((status) => (
            <button
              key={status}
              type="button"
              onClick={() => setOrderFilterStatus(status)}
              style={{
                padding: '6px 12px',
                borderRadius: '20px',
                fontSize: '0.8rem',
                fontWeight: '600',
                border: '1px solid',
                borderColor: orderFilterStatus === status ? 'var(--primary-color, #6b21a8)' : '#e5e7eb',
                background: orderFilterStatus === status ? 'var(--primary-color, #6b21a8)' : '#fff',
                color: orderFilterStatus === status ? '#fff' : '#4b5563',
                cursor: 'pointer',
                textTransform: 'capitalize'
              }}
            >
              {status}
            </button>
          ))}
        </div>
      </div>

      {/* Quick Courier Tracking Banner */}
      <div
        style={{
          background: 'linear-gradient(135deg, #FDF7FD 0%, #F8EEF8 100%)',
          border: '1px solid #e9d5eb',
          borderRadius: '12px',
          padding: '14px 18px',
          marginBottom: '20px',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: '12px'
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          <div
            style={{
              width: '36px',
              height: '36px',
              borderRadius: '8px',
              background: 'var(--primary-color, #A049A3)',
              color: '#ffffff',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0
            }}
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <rect x="1" y="3" width="15" height="13"></rect>
              <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
              <circle cx="5.5" cy="18.5" r="2.5"></circle>
              <circle cx="18.5" cy="18.5" r="2.5"></circle>
            </svg>
          </div>
          <div>
            <h4 style={{ margin: 0, fontSize: '0.92rem', color: '#1f2937', fontWeight: '600' }}>
              Have a Tracking ID from WhatsApp?
            </h4>
            <p style={{ margin: '2px 0 0 0', fontSize: '0.8rem', color: '#6b7280' }}>
              Track shipment progress instantly using the unique ID shared by our team.
            </p>
          </div>
        </div>

        <Link
          to="/track-order"
          style={{
            background: 'var(--primary-color, #A049A3)',
            color: '#ffffff',
            padding: '8px 16px',
            borderRadius: '8px',
            fontSize: '0.85rem',
            fontWeight: '600',
            textDecoration: 'none',
            display: 'inline-flex',
            alignItems: 'center',
            gap: '6px'
          }}
        >
          <span>Track Order</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </Link>
      </div>

      {isOrdersLoading ? (
        <div style={{ textAlign: 'center', padding: '40px', color: '#6b7280' }}>
          <div className="spinner" style={{ margin: '0 auto 12px' }}></div>
          Loading orders...
        </div>
      ) : orders.length === 0 ? (
        <div className="empty-address-box" style={{ padding: '40px 20px', textAlign: 'center' }}>
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="1.5">
            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
          </svg>
          <h4 style={{ margin: '12px 0 6px 0', fontSize: '1.1rem' }}>No Orders Found</h4>
          <p style={{ color: '#6b7280', margin: '0 0 16px 0', fontSize: '0.9rem' }}>
            {orderFilterStatus !== 'all' ? `No ${orderFilterStatus} orders found.` : "You haven't placed any orders yet."}
          </p>
          <Link to="/collections" className="btn-primary-cart" style={{ display: 'inline-block', textDecoration: 'none' }}>
            Explore Boutique Collections
          </Link>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
          {orders.map((order) => (
            <div
              key={order.id}
              style={{
                background: '#fff',
                border: '1px solid #e5e7eb',
                borderRadius: '12px',
                padding: '20px',
                boxShadow: '0 1px 3px rgba(0,0,0,0.04)',
                transition: 'box-shadow 0.2s ease'
              }}
            >
              <div style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'flex-start',
                borderBottom: '1px solid #f3f4f6',
                paddingBottom: '14px',
                marginBottom: '14px',
                flexWrap: 'wrap',
                gap: '10px'
              }}>
                <div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <span style={{ fontWeight: '800', fontSize: '1.05rem', color: 'var(--primary-color, #6b21a8)' }}>
                      #{order.order_number}
                    </span>
                    <span style={{
                      padding: '3px 10px',
                      borderRadius: '12px',
                      fontSize: '0.75rem',
                      fontWeight: '700',
                      textTransform: 'uppercase',
                      ...getStatusBadgeStyle(order.order_status)
                    }}>
                      {order.order_status}
                    </span>
                  </div>
                  <span style={{ color: '#6b7280', fontSize: '0.8rem', display: 'block', marginTop: '4px' }}>
                    Placed on {order.placed_at || order.created_at ? new Date(order.placed_at || order.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Recent'}
                  </span>
                </div>

                <div style={{ textAlign: 'right' }}>
                  <span style={{ fontSize: '1.15rem', fontWeight: '800', color: '#111827', display: 'block' }}>
                    ₹{parseFloat(order.grand_total || order.amounts?.grand_total || 0).toLocaleString('en-IN')}.00
                  </span>
                  <span style={{ fontSize: '0.75rem', color: '#6b7280', textTransform: 'uppercase' }}>
                    {order.payment_method ? (order.payment_method === 'cod' ? 'Cash on Delivery' : order.payment_method.toUpperCase()) : 'Payment Pending'}
                  </span>
                </div>
              </div>

              {/* Items Preview */}
              {order.items && order.items.length > 0 && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginBottom: '16px' }}>
                  {order.items.map((rawItem, idx) => {
                    const item = formatOrderItem(rawItem);
                    return (
                      <div key={idx} style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <div style={{
                          width: '48px',
                          height: '48px',
                          borderRadius: '6px',
                          background: '#f3f4f6',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          overflow: 'hidden',
                          flexShrink: 0
                        }}>
                          {item.imageUrl ? (
                            <img
                              src={item.imageUrl}
                              alt={item.productName}
                              style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                            />
                          ) : (
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" strokeWidth="2">
                              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                              <circle cx="8.5" cy="8.5" r="1.5"></circle>
                              <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                          )}
                        </div>
                        <div style={{ flex: 1, minWidth: 0 }}>
                          <h5 style={{ margin: 0, fontSize: '0.9rem', fontWeight: '600', color: '#1f2937', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                            {item.productName}
                          </h5>
                          <p style={{ margin: '2px 0 0 0', fontSize: '0.8rem', color: '#6b7280' }}>
                            {[
                              item.variantName ? item.variantName : null,
                              item.sizeName ? `Size: ${item.sizeName}` : null,
                              item.colorName ? `Color: ${item.colorName}` : null
                            ].filter(Boolean).join(' • ') || null}
                            {item.variantName || item.sizeName || item.colorName ? ' • ' : ''}
                            Qty: {item.quantity} × ₹{item.sellingPrice.toFixed(2)}
                          </p>
                        </div>
                        <span style={{ fontSize: '0.9rem', fontWeight: '700', color: '#374151' }}>
                          ₹{item.lineTotal.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                        </span>
                      </div>
                    );
                  })}
                </div>
              )}

              {/* Order Footer Actions */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid #f3f4f6', paddingTop: '12px', flexWrap: 'wrap', gap: '8px' }}>
                <span style={{ fontSize: '0.8rem', color: '#4b5563' }}>
                  {order.address?.city ? `Shipping to: ${order.address.city}, ${order.address.state}` : 'Delivery Address Assigned'}
                </span>
                <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' }}>
                  {/* Order Tracking Button */}
                  <button
                    type="button"
                    onClick={() => {
                      window.open('https://www.indiapost.gov.in/_layouts/15/dop.portal.tracking/trackconsignment.aspx', '_blank', 'noopener,noreferrer');
                    }}
                    style={{
                      background: 'transparent',
                      border: '1px solid #7dd3fc',
                      color: '#0369a1',
                      padding: '6px 12px',
                      borderRadius: '6px',
                      fontSize: '0.82rem',
                      fontWeight: '600',
                      cursor: 'pointer',
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '5px',
                      transition: 'all 0.2s ease'
                    }}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.background = '#f0f9ff';
                      e.currentTarget.style.borderColor = '#0369a1';
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.background = 'transparent';
                      e.currentTarget.style.borderColor = '#7dd3fc';
                    }}
                    title="Track your order on the courier website using your Tracking ID (shared via WhatsApp)"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <rect x="1" y="3" width="15" height="13"></rect>
                      <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                      <circle cx="5.5" cy="18.5" r="2.5"></circle>
                      <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                    Track Order
                  </button>

                  <button
                    type="button"
                    onClick={() => handleOpenOrderDetails(order.id)}
                    style={{
                      background: 'transparent',
                      border: '1px solid var(--primary-color, #6b21a8)',
                      color: 'var(--primary-color, #6b21a8)',
                      padding: '6px 14px',
                      borderRadius: '6px',
                      fontSize: '0.82rem',
                      fontWeight: '700',
                      cursor: 'pointer'
                    }}
                  >
                    View Details & Invoice →
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );

  return (
    <div className="customer-profile-page container">

      {/* ========== MOBILE ACCOUNT HUB (Card-based navigation) ========== */}
      <div className="mobile-account-hub">
        {/* User Identity Card */}
        <div className="mobile-account-user-card">
          <div className="mobile-account-avatar">{avatarInitial}</div>
          <div className="mobile-account-user-info">
            <h2 className="mobile-account-name">Hello, {firstName}</h2>
            <p className="mobile-account-email">{profileData.email || profileData.mobile}</p>
          </div>
        </div>

        {/* Quick Navigation Cards */}
        <div className="mobile-account-menu-list">
          {accountMenuItems.map((item) => {
            const isTabAction = item.path === '/profile' || item.path === '/addresses' || item.path === '/orders';
            const content = (
              <div className={`mobile-account-menu-item ${item.active ? 'current' : ''}`}>
                <div className="mobile-account-menu-icon">{item.icon}</div>
                <div className="mobile-account-menu-text">
                  <span className="mobile-account-menu-label">{item.label}</span>
                  <span className="mobile-account-menu-subtitle">{item.subtitle}</span>
                </div>
                {item.hasArrow && (
                  <svg className="mobile-account-menu-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                )}
              </div>
            );

            if (isTabAction) {
              return (
                <div
                  key={item.path}
                  onClick={() => {
                    if (item.path === '/profile') setActiveTab('profile');
                    if (item.path === '/addresses') setActiveTab('addresses');
                    if (item.path === '/orders') setActiveTab('orders');
                  }}
                  style={{ cursor: 'pointer' }}
                >
                  {content}
                </div>
              );
            }

            return (
              <Link key={item.path} to={item.path} style={{ textDecoration: 'none' }}>
                {content}
              </Link>
            );
          })}
        </div>

        {/* Logout */}
        <button className="mobile-account-logout-btn" onClick={handleLogout}>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          <span>Logout</span>
        </button>

        {/* Mobile Active Tab Content */}
        {activeTab === 'addresses' ? (
          renderSavedAddressesView()
        ) : activeTab === 'orders' || activeTab === 'track' ? (
          renderOrdersView()
        ) : (
          <div className="mobile-profile-section">
            <div className="mobile-profile-section-header">
              <h3>Personal Information</h3>
              {!isEditingProfile && (
                <button
                  className="btn-edit-inline"
                  onClick={() => {
                    setEditFormData(profileData);
                    setIsEditingProfile(true);
                  }}
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                  </svg>
                  Edit
                </button>
              )}
            </div>

            {successMsg && <div className="profile-toast profile-toast-success">{successMsg}</div>}
            {errorMsg && <div className="profile-toast profile-toast-error">{errorMsg}</div>}

            {isEditingProfile ? (
              <form className="profile-edit-form" onSubmit={handleUpdateProfile}>
                <div className="form-group">
                  <label>Full Name</label>
                  <input
                    type="text"
                    value={editFormData.fullName}
                    onChange={(e) => setEditFormData({...editFormData, fullName: e.target.value})}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Email Address</label>
                  <input
                    type="email"
                    value={editFormData.email}
                    onChange={(e) => setEditFormData({...editFormData, email: e.target.value})}
                  />
                </div>
                <div className="form-group">
                  <label>Mobile Number</label>
                  <input
                    type="tel"
                    value={editFormData.mobile}
                    onChange={(e) => setEditFormData({...editFormData, mobile: e.target.value})}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Date of Birth</label>
                  <input
                    type="date"
                    value={editFormData.dob}
                    disabled
                    readOnly
                    title="Date of birth cannot be changed"
                    style={{ background: '#f3f4f6', cursor: 'not-allowed', color: '#6b7280' }}
                  />
                  <span style={{ fontSize: '0.72rem', color: '#9ca3af', marginTop: '3px', display: 'block' }}>
                    Date of birth cannot be changed.
                  </span>
                </div>
                <div className="form-action-row">
                  <button
                    type="button"
                    className="btn-cancel"
                    onClick={() => setIsEditingProfile(false)}
                    disabled={isSaving}
                  >
                    Cancel
                  </button>
                  <button type="submit" className="btn-save" disabled={isSaving}>
                    {isSaving ? 'Saving...' : 'Save Changes'}
                  </button>
                </div>
              </form>
            ) : (
              <div className="info-display-grid">
                <div className="info-display-item">
                  <span className="info-label">Full Name</span>
                  <span className="info-value">{profileData.fullName}</span>
                </div>
                <div className="info-display-item">
                  <span className="info-label">Email</span>
                  <span className="info-value">{profileData.email || 'Not provided'}</span>
                </div>
                <div className="info-display-item">
                  <span className="info-label">Mobile</span>
                  <span className="info-value">{profileData.mobile}</span>
                </div>
                <div className="info-display-item">
                  <span className="info-label">Date of Birth</span>
                  <span className="info-value">{profileData.dob || 'Not provided'}</span>
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      {/* ========== DESKTOP PROFILE VIEW ========== */}
      <div className="desktop-profile-view">
        {successMsg && <div className="profile-toast profile-toast-success">{successMsg}</div>}
        {errorMsg && <div className="profile-toast profile-toast-error">{errorMsg}</div>}

        {/* Tab Navigation for Desktop */}
        <div className="desktop-account-tab-nav">
          <button
            className={`desktop-tab-btn ${activeTab === 'profile' ? 'active' : ''}`}
            onClick={() => {
              setActiveTab('profile');
              navigate('/profile');
            }}
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Personal Info
          </button>
          <button
            className={`desktop-tab-btn ${activeTab === 'orders' || activeTab === 'track' ? 'active' : ''}`}
            onClick={() => {
              setActiveTab('orders');
              navigate('/orders');
            }}
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
              <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            My Orders ({orders.length})
          </button>
          <button
            className={`desktop-tab-btn ${activeTab === 'addresses' ? 'active' : ''}`}
            onClick={() => {
              setActiveTab('addresses');
              navigate('/addresses');
            }}
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
              <circle cx="12" cy="10" r="3"></circle>
            </svg>
            Saved Addresses ({savedAddresses.length})
          </button>
        </div>

        {activeTab === 'addresses' ? (
          renderSavedAddressesView()
        ) : activeTab === 'orders' || activeTab === 'track' ? (
          renderOrdersView()
        ) : (
          <>
            {/* Header Title Section */}
            <div className="profile-page-header">
              <h1 className="profile-main-title">My Profile</h1>
              <p className="profile-main-subtitle">
                Manage your personal information and account preferences.
              </p>
            </div>

            {/* Profile Summary Card */}
            <div className="profile-summary-card">
              <div className="profile-avatar-wrapper">
                <div className="profile-large-avatar">{avatarInitial}</div>
              </div>
              <div className="profile-summary-info">
                <h2 className="summary-name">{profileData.fullName}</h2>
                <p className="summary-detail">{profileData.email}</p>
                <p className="summary-detail">{profileData.mobile}</p>
              </div>
              <div className="profile-summary-action">
                {!isEditingProfile && (
                  <button
                    type="button"
                    className="btn-edit-profile-action"
                    onClick={() => {
                      setEditFormData(profileData);
                      setIsEditingProfile(true);
                    }}
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Edit Profile
                  </button>
                )}
              </div>
            </div>

            {/* Personal Information */}
            <div className="profile-section-block">
              <div className="section-block-header">
                <h3 className="section-block-title">Personal Information</h3>
              </div>

              <div className="profile-info-card">
                {isEditingProfile ? (
                  <form className="profile-edit-form" onSubmit={handleUpdateProfile}>
                    <div className="form-grid-2col">
                      <div className="form-group">
                        <label>Full Name</label>
                        <input
                          type="text"
                          value={editFormData.fullName}
                          onChange={(e) => setEditFormData({...editFormData, fullName: e.target.value})}
                          required
                        />
                      </div>
                      <div className="form-group">
                        <label>Email Address</label>
                        <input
                          type="email"
                          value={editFormData.email}
                          onChange={(e) => setEditFormData({...editFormData, email: e.target.value})}
                        />
                      </div>
                      <div className="form-group">
                        <label>Mobile Number</label>
                        <input
                          type="tel"
                          value={editFormData.mobile}
                          onChange={(e) => setEditFormData({...editFormData, mobile: e.target.value})}
                          required
                        />
                      </div>
                      <div className="form-group">
                        <label>Date of Birth</label>
                        <input
                          type="date"
                          value={editFormData.dob}
                          disabled
                          readOnly
                          title="Date of birth cannot be changed"
                          style={{ background: '#f3f4f6', cursor: 'not-allowed', color: '#6b7280' }}
                        />
                        <span style={{ fontSize: '0.72rem', color: '#9ca3af', marginTop: '3px', display: 'block' }}>
                          Date of birth cannot be changed.
                        </span>
                      </div>
                    </div>
                    <div className="form-action-row">
                      <button
                        type="button"
                        className="btn-cancel"
                        onClick={() => setIsEditingProfile(false)}
                        disabled={isSaving}
                      >
                        Cancel
                      </button>
                      <button type="submit" className="btn-save" disabled={isSaving}>
                        {isSaving ? 'Saving...' : 'Save Changes'}
                      </button>
                    </div>
                  </form>
                ) : (
                  <div className="info-display-grid">
                    <div className="info-display-item">
                      <span className="info-label">Full Name</span>
                      <span className="info-value">{profileData.fullName}</span>
                    </div>
                    <div className="info-display-item">
                      <span className="info-label">Email Address</span>
                      <span className="info-value">
                        {profileData.email ? <a href={`mailto:${profileData.email}`}>{profileData.email}</a> : 'Not provided'}
                      </span>
                    </div>
                    <div className="info-display-item">
                      <span className="info-label">Mobile Number</span>
                      <span className="info-value">{profileData.mobile}</span>
                    </div>
                    <div className="info-display-item">
                      <span className="info-label">Date of Birth</span>
                      <span className="info-value">{profileData.dob || 'Not provided'}</span>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </>
        )}
      </div>

      {/* ========== ORDER DETAILS MODAL / DRAWER ========== */}
      {isOrderModalOpen && (
        <div
          className="address-drawer-overlay open"
          onClick={() => setIsOrderModalOpen(false)}
        >
          <div
            className="address-drawer open"
            style={{ maxWidth: '600px' }}
            onClick={(e) => e.stopPropagation()}
          >
            <div className="drawer-header">
              <div className="drawer-title-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
                  <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                  <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
                <h3>Order #{selectedOrderDetails?.order_number || 'Details'}</h3>
              </div>
              <button
                className="drawer-close-btn"
                onClick={() => setIsOrderModalOpen(false)}
              >
                ✕
              </button>
            </div>

            <div className="drawer-body" style={{ padding: '20px' }}>
              {isOrderDetailsLoading ? (
                <div style={{ textAlign: 'center', padding: '40px' }}>Loading order details...</div>
              ) : selectedOrderDetails ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                  {/* Status Banner */}
                  <div style={{
                    padding: '12px 16px',
                    borderRadius: '8px',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    ...getStatusBadgeStyle(selectedOrderDetails.order_status)
                  }}>
                    <strong style={{ textTransform: 'uppercase' }}>Status: {selectedOrderDetails.order_status}</strong>
                    <span style={{ fontSize: '0.8rem' }}>Payment: {selectedOrderDetails.payment?.status || selectedOrderDetails.payment_status}</span>
                  </div>

                  {/* Refund Notice / Status if applicable */}
                  {(selectedOrderDetails.order_status === 'refunded' || selectedOrderDetails.payment?.status === 'refunded' || selectedOrderDetails.payment_status === 'refunded') && (
                    <div style={{
                      background: '#f3e8ff',
                      border: '1px solid #d8b4fe',
                      borderRadius: '8px',
                      padding: '12px 16px',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '10px'
                    }}>
                      <div style={{
                        width: '32px',
                        height: '32px',
                        borderRadius: '50%',
                        background: '#e9d5ff',
                        color: '#6b21a8',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontSize: '16px',
                        flexShrink: 0
                      }}>
                        ✓
                      </div>
                      <div>
                        <strong style={{ color: '#6b21a8', display: 'block', fontSize: '0.88rem' }}>Order Refunded</strong>
                        <p style={{ margin: '2px 0 0', fontSize: '0.8rem', color: '#7e22ce' }}>
                          This order has been marked as refunded in our system.
                        </p>
                      </div>
                    </div>
                  )}

                  {refundRequests[selectedOrderDetails.id] && (
                    <div style={{
                      background: '#fdf4ff',
                      border: '1px solid #f0abfc',
                      borderRadius: '8px',
                      padding: '12px 16px',
                      display: 'flex',
                      flexDirection: 'column',
                      gap: '6px'
                    }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <strong style={{ color: '#86198f', fontSize: '0.88rem', display: 'flex', alignItems: 'center', gap: '6px' }}>
                          <span style={{ width: '7px', height: '7px', borderRadius: '50%', background: '#a21caf', display: 'inline-block' }}></span>
                          Refund Request Submitted
                        </strong>
                        <span style={{ fontSize: '0.75rem', color: '#a21caf' }}>
                          {new Date(refundRequests[selectedOrderDetails.id].requestedAt).toLocaleDateString('en-IN', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric'
                          })}
                        </span>
                      </div>
                      <p style={{ margin: 0, fontSize: '0.82rem', color: '#4b5563' }}>
                        <strong>Reason:</strong> "{refundRequests[selectedOrderDetails.id].reason}"
                      </p>
                      <button
                        type="button"
                        onClick={() => handleOpenRefundModal(selectedOrderDetails)}
                        style={{
                          alignSelf: 'flex-start',
                          marginTop: '4px',
                          background: 'transparent',
                          border: 'none',
                          color: 'var(--primary-color, #A049A3)',
                          fontSize: '0.8rem',
                          fontWeight: '600',
                          cursor: 'pointer',
                          textDecoration: 'underline',
                          padding: 0
                        }}
                      >
                        View details & WhatsApp notification →
                      </button>
                    </div>
                  )}

                  {/* Order Items */}
                  <div>
                    <h4 style={{ margin: '0 0 10px 0', fontSize: '0.95rem', color: '#374151' }}>Ordered Items</h4>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                      {selectedOrderDetails.items?.map((rawItem, idx) => {
                        const item = formatOrderItem(rawItem);
                        return (
                          <div key={idx} style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '12px',
                            background: '#f9fafb',
                            padding: '10px',
                            borderRadius: '8px',
                            border: '1px solid #f3f4f6'
                          }}>
                            <div style={{
                              width: '50px',
                              height: '50px',
                              borderRadius: '6px',
                              background: '#e5e7eb',
                              overflow: 'hidden',
                              flexShrink: 0
                            }}>
                              {item.imageUrl ? (
                                <img
                                  src={item.imageUrl}
                                  alt={item.productName}
                                  style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                                />
                              ) : (
                                <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#9ca3af', fontSize: '10px' }}>
                                  No Img
                                </div>
                              )}
                            </div>
                            <div style={{ flex: 1 }}>
                              <h5 style={{ margin: 0, fontSize: '0.9rem', color: '#111827' }}>{item.productName}</h5>
                              <p style={{ margin: '2px 0 0 0', fontSize: '0.75rem', color: '#6b7280' }}>
                                {[
                                  item.variantName ? item.variantName : null,
                                  item.sizeName ? `Size: ${item.sizeName}` : null,
                                  item.colorName ? `Color: ${item.colorName}` : null
                                ].filter(Boolean).join(' • ') || null}
                                {item.variantName || item.sizeName || item.colorName ? ' • ' : ''}
                                Qty: {item.quantity} × ₹{item.sellingPrice.toFixed(2)}
                              </p>
                              {item.gstRate > 0 && (
                                <p style={{ margin: '2px 0 0 0', fontSize: '0.7rem', color: '#9ca3af' }}>
                                  GST: {item.gstRate}% {item.cgstAmount > 0 ? `(CGST ₹${item.cgstAmount.toFixed(2)} + SGST ₹${item.sgstAmount.toFixed(2)})` : `(IGST ₹${(item.igstAmount || item.taxAmount).toFixed(2)})`}
                                </p>
                              )}
                            </div>
                            <strong style={{ fontSize: '0.9rem' }}>₹{item.lineTotal.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong>
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* Delivery Address */}
                  {selectedOrderDetails.address && (
                    <div style={{ background: '#faf5ff', padding: '12px 16px', borderRadius: '8px', border: '1px solid #e9d5ff' }}>
                      <h4 style={{ margin: '0 0 6px 0', fontSize: '0.85rem', color: 'var(--primary-color)' }}>Delivery Address</h4>
                      <p style={{ margin: 0, fontSize: '0.85rem', color: '#374151' }}>
                        {selectedOrderDetails.address.door_no}, {selectedOrderDetails.address.street}, {selectedOrderDetails.address.area}, {selectedOrderDetails.address.city}, {selectedOrderDetails.address.state} - {selectedOrderDetails.address.pincode}
                      </p>
                      {selectedOrderDetails.address.landmark && (
                        <p style={{ margin: '2px 0 0 0', fontSize: '0.75rem', color: '#6b7280' }}>
                          Landmark: {selectedOrderDetails.address.landmark}
                        </p>
                      )}
                    </div>
                  )}

                  {/* Bill Breakdown */}
                  <div style={{ borderTop: '1px solid #e5e7eb', paddingTop: '12px' }}>
                    <h4 style={{ margin: '0 0 10px 0', fontSize: '0.95rem' }}>Price Breakdown</h4>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', fontSize: '0.85rem' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                        <span style={{ color: '#6b7280' }}>Subtotal</span>
                        <span>₹{parseFloat(selectedOrderDetails.amounts?.subtotal || selectedOrderDetails.subtotal || 0).toLocaleString('en-IN')}.00</span>
                      </div>
                      {parseFloat(selectedOrderDetails.amounts?.product_discount_amount || selectedOrderDetails.product_discount_amount || 0) > 0 && (
                        <div style={{ display: 'flex', justifyContent: 'space-between', color: '#16a34a' }}>
                          <span>Product Discount</span>
                          <span>-₹{parseFloat(selectedOrderDetails.amounts?.product_discount_amount || selectedOrderDetails.product_discount_amount || 0).toLocaleString('en-IN')}.00</span>
                        </div>
                      )}
                      {parseFloat(selectedOrderDetails.amounts?.coupon_discount_amount || selectedOrderDetails.coupon_discount_amount || 0) > 0 && (
                        <div style={{ display: 'flex', justifyContent: 'space-between', color: '#16a34a' }}>
                          <span>Coupon Savings ({selectedOrderDetails.coupon_code || 'COUPON'})</span>
                          <span>-₹{parseFloat(selectedOrderDetails.amounts?.coupon_discount_amount || selectedOrderDetails.coupon_discount_amount || 0).toLocaleString('en-IN')}.00</span>
                        </div>
                      )}
                      {parseFloat(selectedOrderDetails.amounts?.tax_amount || selectedOrderDetails.tax_amount || 0) > 0 && (
                        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                          <span style={{ color: '#6b7280' }}>Estimated GST / Taxes</span>
                          <span>₹{parseFloat(selectedOrderDetails.amounts?.tax_amount || selectedOrderDetails.tax_amount || 0).toLocaleString('en-IN')}.00</span>
                        </div>
                      )}
                      {parseFloat(selectedOrderDetails.amounts?.cod_charge || selectedOrderDetails.cod_charge || 0) > 0 && (
                        <div style={{ display: 'flex', justifyContent: 'space-between', color: '#92400e' }}>
                          <span>Cash on Delivery Handling Charge</span>
                          <span>+₹{parseFloat(selectedOrderDetails.amounts?.cod_charge || selectedOrderDetails.cod_charge || 0).toLocaleString('en-IN')}.00</span>
                        </div>
                      )}
                      <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: '800', fontSize: '1rem', borderTop: '1px solid #e5e7eb', paddingTop: '8px', marginTop: '4px' }}>
                        <span>Grand Total</span>
                        <span style={{ color: 'var(--primary-color)' }}>₹{parseFloat(selectedOrderDetails.amounts?.grand_total || selectedOrderDetails.grand_total || 0).toLocaleString('en-IN')}.00</span>
                      </div>
                    </div>
                  </div>

                  {selectedOrderDetails.customer_note && (
                    <div style={{ background: '#f9fafb', padding: '10px 14px', borderRadius: '6px', fontSize: '0.8rem', color: '#4b5563' }}>
                      <strong>Delivery Instructions:</strong> {selectedOrderDetails.customer_note}
                    </div>
                  )}
                </div>
              ) : (
                <div style={{ textAlign: 'center', padding: '30px', color: '#dc2626' }}>
                  Unable to load order details.
                </div>
              )}
            </div>

            <div className="drawer-footer" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              {isOrderEligibleForRefund(selectedOrderDetails) && !refundRequests[selectedOrderDetails.id] && (
                <button
                  type="button"
                  onClick={() => handleOpenRefundModal(selectedOrderDetails)}
                  style={{
                    background: '#faf5fa',
                    border: '1px solid var(--primary-color, #A049A3)',
                    color: 'var(--primary-color, #A049A3)',
                    padding: '8px 16px',
                    borderRadius: '8px',
                    fontSize: '0.85rem',
                    fontWeight: '600',
                    cursor: 'pointer',
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '6px',
                    transition: 'all 0.2s ease'
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.background = 'var(--primary-color, #A049A3)';
                    e.currentTarget.style.color = '#ffffff';
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.background = '#faf5fa';
                    e.currentTarget.style.color = 'var(--primary-color, #A049A3)';
                  }}
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                    <path d="M3 3v5h5" />
                  </svg>
                  Request Refund
                </button>
              )}
              <button
                type="button"
                className="btn-drawer-done"
                onClick={() => setIsOrderModalOpen(false)}
                style={{ marginLeft: 'auto' }}
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========== REUSABLE ADDRESS SIDE DRAWER ========== */}
      <div
        className={`address-drawer-overlay ${isAddressDrawerOpen ? 'open' : ''}`}
        onClick={() => setIsAddressDrawerOpen(false)}
      >
        <div
          className={`address-drawer ${isAddressDrawerOpen ? 'open' : ''}`}
          onClick={(e) => e.stopPropagation()}
        >
          <div className="drawer-header">
            <div className="drawer-title-box">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <h3>{editingAddressId ? 'Edit Address' : 'Add New Address'}</h3>
            </div>
            <button
              className="drawer-close-btn"
              onClick={() => setIsAddressDrawerOpen(false)}
            >
              ✕
            </button>
          </div>

          <div className="drawer-body">
            <form onSubmit={handleSaveAddressSubmit} className="drawer-address-form">
              {addressError && (
                <div className="profile-toast profile-toast-error" style={{ marginBottom: '14px', fontSize: '0.85rem' }}>
                  {addressError}
                </div>
              )}
              <div className="form-group">
                <label htmlFor="drawerAddressType">Address Type *</label>
                <select
                  id="drawerAddressType"
                  value={addressForm.address_type}
                  onChange={(e) => setAddressForm({ ...addressForm, address_type: e.target.value })}
                  className="form-select"
                  style={{
                    padding: '10px 14px',
                    border: '1px solid #d1d5db',
                    borderRadius: '6px',
                    fontSize: '0.9rem',
                    background: '#fff',
                    color: '#1f2937',
                    cursor: 'pointer',
                    outline: 'none',
                    width: '100%'
                  }}
                >
                  <option value="home">Home (Delivery all day)</option>
                  <option value="work">Work (Delivery between 10 AM - 6 PM)</option>
                  <option value="other">Other</option>
                </select>
              </div>

              <div className="form-group">
                <label htmlFor="drawerDoorNo">Door / House No. *</label>
                <input
                  type="text"
                  id="drawerDoorNo"
                  value={addressForm.door_no}
                  onChange={(e) => setAddressForm({ ...addressForm, door_no: e.target.value })}
                  placeholder="House / Door No."
                  required
                />
              </div>
              <div className="form-group">
                <label htmlFor="drawerStreet">Street Name *</label>
                <input
                  type="text"
                  id="drawerStreet"
                  value={addressForm.street}
                  onChange={(e) => setAddressForm({ ...addressForm, street: e.target.value })}
                  placeholder="Street Name"
                  required
                />
              </div>
              <div className="form-group">
                <label htmlFor="drawerArea">Area / Locality *</label>
                <input
                  type="text"
                  id="drawerArea"
                  value={addressForm.area}
                  onChange={(e) => setAddressForm({ ...addressForm, area: e.target.value })}
                  placeholder="Area / Locality"
                  required
                />
              </div>
              <div className="form-group">
                <label htmlFor="drawerCity">City *</label>
                <input
                  type="text"
                  id="drawerCity"
                  value={addressForm.city}
                  onChange={(e) => setAddressForm({ ...addressForm, city: e.target.value })}
                  placeholder="City"
                  required
                />
              </div>
              <div className="form-group">
                <label htmlFor="drawerDistrict">District</label>
                <input
                  type="text"
                  id="drawerDistrict"
                  value={addressForm.district}
                  onChange={(e) => setAddressForm({ ...addressForm, district: e.target.value })}
                  placeholder="District (Optional)"
                />
              </div>
              <div className="form-group">
                <label htmlFor="drawerState">State *</label>
                <input
                  type="text"
                  id="drawerState"
                  value={addressForm.state}
                  onChange={(e) => setAddressForm({ ...addressForm, state: e.target.value })}
                  placeholder="State"
                  required
                />
              </div>
              <div className="form-group">
                <label htmlFor="drawerPincode">Pincode *</label>
                <input
                  type="text"
                  id="drawerPincode"
                  value={addressForm.pincode}
                  onChange={(e) => setAddressForm({ ...addressForm, pincode: e.target.value })}
                  placeholder="6-digit pincode"
                  required
                />
              </div>
              <div className="form-group">
                <label htmlFor="drawerLandmark">Landmark</label>
                <input
                  type="text"
                  id="drawerLandmark"
                  value={addressForm.landmark}
                  onChange={(e) => setAddressForm({ ...addressForm, landmark: e.target.value })}
                  placeholder="Nearby landmark (Optional)"
                />
              </div>

              <div className="checkbox-inline-row" style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', gap: '10px', marginTop: '6px', marginBottom: '8px' }}>
                <input
                  type="checkbox"
                  id="drawerIsDefault"
                  checked={addressForm.is_default === 1}
                  onChange={(e) => setAddressForm({ ...addressForm, is_default: e.target.checked ? 1 : 0 })}
                  style={{ width: '18px', height: '18px', cursor: 'pointer', margin: 0, padding: 0, flexShrink: 0 }}
                />
                <label htmlFor="drawerIsDefault" style={{ cursor: 'pointer', margin: 0, fontSize: '0.88rem', fontWeight: '500', color: '#374151', display: 'inline' }}>
                  Set as default delivery address
                </label>
              </div>

              <div className="form-buttons-row">
                <button
                  type="button"
                  className="btn-cancel-form"
                  onClick={() => setIsAddressDrawerOpen(false)}
                >
                  Cancel
                </button>
                <button type="submit" className="btn-save-address">
                  Save Address
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      {/* ========== REFUND REQUEST MODAL ========== */}
      <RefundRequestModal
        isOpen={isRefundModalOpen}
        onClose={() => setIsRefundModalOpen(false)}
        order={selectedRefundOrder}
        currentUser={user}
        existingRequest={selectedRefundOrder ? refundRequests[selectedRefundOrder.id] : null}
        onRefundSubmitted={handleRefundSubmitted}
      />
    </div>
  );
};

export default CustomerProfile;
