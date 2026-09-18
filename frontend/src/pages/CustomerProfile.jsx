import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

const API_BASE_URL = 'http://localhost/vivisha_boutique/backend/api';

const CustomerProfile = ({ defaultTab = 'profile' }) => {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout, token, isAuthenticated, updateUser } = useAuth();

  const [activeTab, setActiveTab] = useState(() => {
    if (location.pathname === '/addresses') return 'addresses';
    return defaultTab || 'profile';
  });

  useEffect(() => {
    if (location.pathname === '/addresses') {
      setActiveTab('addresses');
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

  useEffect(() => {
    if (isAuthenticated && token) {
      fetchAddresses();
    }
  }, [isAuthenticated, token]);

  const handleLogout = () => {
    logout();
    navigate('/');
  };

  const handleUpdateProfile = async (e) => {
    e.preventDefault();
    setErrorMsg('');
    setSuccessMsg('');

    // Full Name Validation
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

    // Mobile Number Validation
    const trimmedMobile = (editFormData.mobile || '').trim();
    if (!trimmedMobile) {
      setErrorMsg('Please enter your mobile number.');
      return;
    }
    if (!/^[6-9][0-9]{9}$/.test(trimmedMobile)) {
      setErrorMsg('Mobile number must be a valid 10-digit Indian mobile number.');
      return;
    }

    // Email Validation
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

    // Date of Birth Validation
    const dob = (editFormData.dob || '').trim();
    if (dob) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(dob)) {
        setErrorMsg('date_of_birth must be in YYYY-MM-DD format.');
        return;
      }
      const selectedDate = new Date(dob + 'T00:00:00');
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      if (isNaN(selectedDate.getTime()) || selectedDate > today) {
        setErrorMsg('date_of_birth cannot be a future date.');
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
      if (dob) payload.date_of_birth = dob;

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
    setIsAddressDrawerOpen(true);
  };

  const handleSaveAddressSubmit = async (e) => {
    e.preventDefault();

    const doorNo = addressForm.door_no ? addressForm.door_no.trim() : '';
    const street = addressForm.street ? addressForm.street.trim() : '';
    const area = addressForm.area ? addressForm.area.trim() : '';
    const city = addressForm.city ? addressForm.city.trim() : '';
    const district = addressForm.district ? addressForm.district.trim() : '';
    const state = addressForm.state ? addressForm.state.trim() : '';
    const pincode = addressForm.pincode ? addressForm.pincode.trim() : '';
    const landmark = addressForm.landmark ? addressForm.landmark.trim() : '';

    if (!doorNo) {
      alert('Door number is required.');
      return;
    }
    if (doorNo.length < 1 || doorNo.length > 100) {
      alert('Door number must be between 1 and 100 characters.');
      return;
    }

    if (!street) {
      alert('Street is required.');
      return;
    }
    if (street.length < 2 || street.length > 150) {
      alert('Street must be between 2 and 150 characters.');
      return;
    }

    if (!area) {
      alert('Area is required.');
      return;
    }
    if (area.length < 2 || area.length > 150) {
      alert('Area must be between 2 and 150 characters.');
      return;
    }

    if (!city) {
      alert('City is required.');
      return;
    }
    if (city.length < 2 || city.length > 100) {
      alert('City must be between 2 and 100 characters.');
      return;
    }

    if (district && district.length > 100) {
      alert('District must not exceed 100 characters.');
      return;
    }

    if (!state) {
      alert('State is required.');
      return;
    }
    if (state.length < 2 || state.length > 100) {
      alert('State must be between 2 and 100 characters.');
      return;
    }

    if (!pincode) {
      alert('Pincode is required.');
      return;
    }
    if (!/^[1-9][0-9]{5}$/.test(pincode)) {
      alert('Please enter a valid 6-digit Indian pincode.');
      return;
    }

    if (landmark && landmark.length > 150) {
      alert('Landmark must not exceed 150 characters.');
      return;
    }

    const endpoint = editingAddressId ? '/address/update.php' : '/address/create.php';
    const payload = {
      ...addressForm,
      door_no: doorNo,
      street,
      area,
      city,
      district,
      state,
      pincode,
      landmark
    };
    if (editingAddressId) {
      payload.address_id = editingAddressId;
    }

    try {
      const response = await fetch(`${API_BASE_URL}${endpoint}`, {
        method: 'POST',
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
        fetchAddresses();
      } else {
        alert(data.message || 'Error saving address');
      }
    } catch (err) {
      console.error('Error saving address:', err);
      alert('Network error saving address');
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
        fetchAddresses();
      } else {
        alert(data.message || 'Error setting default address');
      }
    } catch (err) {
      console.error('Error setting default address:', err);
    }
  };

  const firstName = profileData.fullName ? profileData.fullName.split(' ')[0] : 'User';
  const avatarInitial = profileData.fullName ? profileData.fullName.charAt(0).toUpperCase() : 'U';

  const accountMenuItems = [
    {
      path: '/profile',
      label: 'My Profile',
      subtitle: 'Personal info, mobile & email',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
      ),
      hasArrow: false,
      active: activeTab === 'profile'
    },
    {
      path: '/orders',
      label: 'My Orders',
      subtitle: 'Track, return or buy again',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <path d="M16 10a4 4 0 0 1-8 0"></path>
        </svg>
      ),
      hasArrow: true,
      active: activeTab === 'orders'
    },
    {
      path: '/wishlist',
      label: 'Wishlist',
      subtitle: 'Your saved favourite items',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
        </svg>
      ),
      hasArrow: true,
      active: activeTab === 'wishlist'
    },
    {
      path: '/addresses',
      label: 'Saved Addresses',
      subtitle: 'Manage delivery addresses',
      icon: (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
          <circle cx="12" cy="10" r="3"></circle>
        </svg>
      ),
      hasArrow: true,
      active: activeTab === 'addresses'
    }
  ];

  if (isLoading) {
    return (
      <div className="customer-profile-page container">
        <div className="profile-loading-state">
          <div className="profile-loading-spinner"></div>
          <p>Loading your profile...</p>
        </div>
      </div>
    );
  }

  // Render Saved Addresses UI Component
  const renderSavedAddressesView = () => (
    <div className="saved-addresses-wrapper">
      <div className="addresses-page-header">
        <div>
          <h2 className="addresses-section-title">Saved Addresses</h2>
          <p className="addresses-section-subtitle">Manage your delivery addresses for quick & easy checkout</p>
        </div>
        <button
          type="button"
          className="btn-add-address-card"
          onClick={handleOpenAddNewAddress}
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Add New Address
        </button>
      </div>

      {isAddressLoading ? (
        <p className="loading-text">Loading addresses...</p>
      ) : savedAddresses.length === 0 ? (
        <div className="no-addresses-card">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="1.5">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
          <h3>No Saved Addresses Yet</h3>
          <p>Add a delivery address to complete your orders faster.</p>
          <button type="button" className="btn-add-address-card" onClick={handleOpenAddNewAddress}>
            + Add Delivery Address
          </button>
        </div>
      ) : (
        <div className="addresses-grid">
          {savedAddresses.map((addr) => (
            <div key={addr.id} className={`address-card ${addr.is_default === 1 ? 'is-default' : ''}`}>
              <div className="address-card-top">
                <span className="address-type-badge">{addr.address_type.toUpperCase()}</span>
                {addr.is_default === 1 && (
                  <span className="default-address-badge">DEFAULT</span>
                )}
              </div>
              <div className="address-card-body">
                <h4 className="address-card-name">{addr.user_name || profileData.fullName}</h4>
                <p className="address-card-text">
                  {addr.door_no}, {addr.street}, {addr.area}, {addr.city}{addr.district ? `, ${addr.district}` : ''}, {addr.state} - <strong>{addr.pincode}</strong>
                </p>
                {addr.landmark && (
                  <p className="address-card-landmark">Landmark: {addr.landmark}</p>
                )}
                <p className="address-card-phone">Phone: +{addr.user_mobile || profileData.mobile}</p>
              </div>
              <div className="address-card-actions">
                <button
                  type="button"
                  className="btn-address-edit"
                  onClick={(e) => handleEditAddress(addr, e)}
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
                  >
                    Set as Default
                  </button>
                )}
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
            const isProfileItem = item.path === '/profile';
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

            if (isProfileItem) {
              return (
                <div key={item.path} onClick={() => setActiveTab('profile')}>
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

        {/* Content Section below menu: Profile Info or Saved Addresses */}
        {activeTab === 'addresses' ? (
          renderSavedAddressesView()
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
                    onChange={(e) => setEditFormData({...editFormData, dob: e.target.value})}
                  />
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

      {/* ========== DESKTOP PROFILE VIEW (Clean, focused) ========== */}
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
            Saved Addresses
          </button>
        </div>

        {activeTab === 'addresses' ? (
          renderSavedAddressesView()
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
                          onChange={(e) => setEditFormData({...editFormData, dob: e.target.value})}
                        />
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
              <div className="form-group">
                <label>Address Type</label>
                <select
                  value={addressForm.address_type}
                  onChange={(e) => setAddressForm({ ...addressForm, address_type: e.target.value })}
                >
                  <option value="home">Home</option>
                  <option value="work">Work</option>
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
    </div>
  );
};

export default CustomerProfile;
