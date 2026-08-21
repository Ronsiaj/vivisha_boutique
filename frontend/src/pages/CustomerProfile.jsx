import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

const API_BASE_URL = 'http://localhost/vivisha_boutique/backend/api';

const CustomerProfile = ({ defaultTab = 'profile' }) => {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout, token, isAuthenticated, updateUser } = useAuth();

  const getInitialTab = () => {
    if (location.pathname === '/orders') return 'orders';
    if (location.pathname === '/notifications') return 'notifications';
    if (location.pathname === '/wishlist') return 'wishlist';
    if (location.pathname === '/addresses') return 'addresses';
    if (location.pathname === '/settings') return 'settings';
    return defaultTab;
  };

  const [activeTab, setActiveTab] = useState(getInitialTab());
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

  const handleLogout = () => {
    logout();
    navigate('/');
  };

  const handleUpdateProfile = async (e) => {
    e.preventDefault();
    setIsSaving(true);
    setErrorMsg('');
    setSuccessMsg('');

    try {
      const payload = {
        name: editFormData.fullName,
        mobile: editFormData.mobile
      };

      if (editFormData.email) payload.email = editFormData.email;
      if (editFormData.dob) payload.date_of_birth = editFormData.dob;

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
        
        // Update context if needed
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

  if (isLoading) {
    return <div className="customer-profile-page container"><p>Loading profile...</p></div>;
  }

  return (
    <div className="customer-profile-page container">
      <div className="profile-wrapper">
        {/* Left Side — Profile Navigation Panel */}
        <aside className="profile-sidebar-card">
          <div className="user-profile-summary">
            <div className="avatar-circle">
              {profileData.fullName ? profileData.fullName.charAt(0).toUpperCase() : 'U'}
            </div>
            <h3 className="user-name">{profileData.fullName}</h3>
            <p className="user-email">{profileData.email}</p>
            <span className="user-role-badge">Vivisha Member</span>
          </div>

          <nav className="profile-nav-menu">
            <button
              className={`profile-nav-item ${activeTab === 'profile' ? 'active' : ''}`}
              onClick={() => setActiveTab('profile')}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              <span>My Profile</span>
            </button>

            <button
              className={`profile-nav-item ${activeTab === 'orders' ? 'active' : ''}`}
              onClick={() => setActiveTab('orders')}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
              </svg>
              <span>My Orders</span>
            </button>

            <button
              className={`profile-nav-item ${activeTab === 'wishlist' ? 'active' : ''}`}
              onClick={() => setActiveTab('wishlist')}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
              <span>Wishlist</span>
            </button>

            <button
              className={`profile-nav-item ${activeTab === 'notifications' ? 'active' : ''}`}
              onClick={() => setActiveTab('notifications')}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
              <span>Notifications</span>
            </button>

            <button
              className={`profile-nav-item ${activeTab === 'addresses' ? 'active' : ''}`}
              onClick={() => setActiveTab('addresses')}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <span>Saved Addresses</span>
            </button>

            <button
              className={`profile-nav-item ${activeTab === 'settings' ? 'active' : ''}`}
              onClick={() => setActiveTab('settings')}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
              </svg>
              <span>Account Settings</span>
            </button>

            <button className="profile-nav-item logout-btn" onClick={handleLogout}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
              </svg>
              <span>Logout</span>
            </button>
          </nav>
        </aside>

        {/* Right Side — Main Profile Content */}
        <main className="profile-main-content">
          {successMsg && <div style={{ color: 'green', marginBottom: '10px' }}>{successMsg}</div>}
          {errorMsg && <div style={{ color: 'red', marginBottom: '10px' }}>{errorMsg}</div>}
          
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
              <div className="profile-large-avatar">
                {profileData.fullName ? profileData.fullName.charAt(0).toUpperCase() : 'U'}
              </div>
            </div>
            <div className="profile-summary-info">
              <h2 className="summary-name">{profileData.fullName}</h2>
              <p className="summary-detail">{profileData.email}</p>
              <p className="summary-detail">{profileData.mobile}</p>
            </div>
            <div className="profile-summary-action">
              <button 
                type="button" 
                className="btn-edit-profile-dummy"
                disabled
              >
                Edit Profile
              </button>
            </div>
          </div>

          {/* Personal Information */}
          <div className="profile-section-block">
            <div className="section-block-header">
              <h3 className="section-block-title">Personal Information</h3>
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
                  Edit Profile
                </button>
              )}
            </div>

            <div className="profile-info-card">
              {isEditingProfile ? (
                <form 
                  className="profile-edit-form" 
                  onSubmit={handleUpdateProfile}
                >
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
                        required
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

        </main>
      </div>
    </div>
  );
};

export default CustomerProfile;
