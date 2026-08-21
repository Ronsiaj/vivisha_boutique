import React, { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext.jsx';
import AsyncSelect from 'react-select/async';

const AdminUsers = () => {
  const { token } = useAuth();
  const [users, setUsers] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  // Filters & Pagination
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  // Modal State
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState(null);
  
  // Status Update State
  const [isStatusModalOpen, setIsStatusModalOpen] = useState(false);
  const [statusFormData, setStatusFormData] = useState({ id: '', status: '' });
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);

  // Addresses State
  const [isAddressesModalOpen, setIsAddressesModalOpen] = useState(false);
  const [userAddresses, setUserAddresses] = useState([]);
  const [isFetchingAddresses, setIsFetchingAddresses] = useState(false);
  const [selectedAddressUser, setSelectedAddressUser] = useState(null);

  // Address Detail State
  const [isAddressDetailModalOpen, setIsAddressDetailModalOpen] = useState(false);
  const [selectedAddressDetail, setSelectedAddressDetail] = useState(null);
  const [isFetchingAddressDetail, setIsFetchingAddressDetail] = useState(false);

  // Fetch Users List
  const fetchUsers = async () => {
    setIsLoading(true);
    setError('');
    
    // Build Query Params
    const params = new URLSearchParams({ page: page.toString(), limit: '20' });
    if (searchQuery) params.append('q', searchQuery);
    if (statusFilter) params.append('status', statusFilter);

    try {
      const response = await fetch(`http://localhost/vivisha_boutique/backend/api/users/list.php?${params.toString()}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      
      if (result.status) {
        setUsers(result.data.users);
        setTotalPages(result.data.pagination.total_pages);
      } else {
        setError(result.message || 'Failed to fetch users');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while fetching users.');
    }
    setIsLoading(false);
  };

  useEffect(() => {
    fetchUsers();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, statusFilter, searchQuery]);

  // Load options for AsyncSelect
  const loadOptions = async (inputValue) => {
    if (!inputValue) return [];
    try {
      const response = await fetch(`http://localhost/vivisha_boutique/backend/api/users/list.php?q=${encodeURIComponent(inputValue)}&limit=10`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      if (result.status && result.data.users) {
        return result.data.users.map(u => ({
          value: u.name,
          label: `${u.name} (${u.email || u.mobile})`
        }));
      }
      return [];
    } catch (err) {
      return [];
    }
  };

  const handleSelectChange = (selectedOption) => {
    if (selectedOption) {
      setSearchQuery(selectedOption.value);
    } else {
      setSearchQuery('');
    }
    setPage(1);
  };

  // View User Profile
  const openViewModal = async (userId) => {
    setSelectedUser(null);
    setIsViewModalOpen(true);
    setError('');
    
    try {
      const response = await fetch(`http://localhost/vivisha_boutique/backend/api/users/view.php?id=${userId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      
      if (result.status) {
        setSelectedUser(result.data.user);
      } else {
        setError(result.message || 'Failed to fetch user details');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while fetching user details.');
    }
  };

  const closeViewModal = () => {
    setIsViewModalOpen(false);
    setSelectedUser(null);
  };

  // Status Update
  const openStatusModal = (user) => {
    setStatusFormData({ id: user.id, status: user.status });
    setIsStatusModalOpen(true);
  };

  const closeStatusModal = () => {
    setIsStatusModalOpen(false);
  };

  const handleStatusUpdate = async (e) => {
    e.preventDefault();
    setIsUpdatingStatus(true);
    setError('');

    try {
      const response = await fetch('http://localhost/vivisha_boutique/backend/api/users/status_update.php', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(statusFormData)
      });
      const result = await response.json();
      
      if (result.status) {
        closeStatusModal();
        fetchUsers(); // Refresh the list
      } else {
        setError(result.message || 'Failed to update user status');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while updating status.');
    }
    setIsUpdatingStatus(false);
  };

  // Fetch Addresses
  const openAddressesModal = async (user) => {
    setSelectedAddressUser(user);
    setIsAddressesModalOpen(true);
    setIsFetchingAddresses(true);
    setError('');
    
    try {
      const response = await fetch(`http://localhost/vivisha_boutique/backend/api/address/list.php?user_id=${user.id}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      if (result.status) {
        setUserAddresses(result.data.addresses || []);
      } else {
        setError(result.message || 'Failed to fetch addresses');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while fetching addresses.');
    }
    setIsFetchingAddresses(false);
  };

  const closeAddressesModal = () => {
    setIsAddressesModalOpen(false);
    setSelectedAddressUser(null);
    setUserAddresses([]);
  };

  // Fetch Address Detail
  const openAddressDetailModal = async (addressId) => {
    setIsAddressDetailModalOpen(true);
    setIsFetchingAddressDetail(true);
    setError('');
    
    try {
      const response = await fetch(`http://localhost/vivisha_boutique/backend/api/address/view.php?address_id=${addressId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const result = await response.json();
      if (result.status) {
        setSelectedAddressDetail(result.data.address);
      } else {
        setError(result.message || 'Failed to fetch address details');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while fetching address details.');
    }
    setIsFetchingAddressDetail(false);
  };

  const closeAddressDetailModal = () => {
    setIsAddressDetailModalOpen(false);
    setSelectedAddressDetail(null);
  };

  // Helper for Status UI Badge
  const getStatusBadge = (status) => {
    const s = status?.toLowerCase() || '';
    if (s === 'active') return { bg: '#dcfce7', text: '#166534', border: '#bbf7d0' };
    if (s === 'blocked') return { bg: '#fee2e2', text: '#991b1b', border: '#fecaca' };
    return { bg: '#f3f4f6', text: '#4b5563', border: '#e5e7eb' }; // Inactive
  };

  return (
    <div className="admin-module-container">
      <div className="admin-page-header">
        <div>
          <div className="admin-breadcrumb">
            <span>Admin</span> &gt; <span>Sales</span> &gt; <span className="active">Customers</span>
          </div>
          <h1 className="admin-module-title">Customer Management</h1>
          <p className="admin-module-desc">View customer profiles, monitor activity, and manage access.</p>
        </div>
      </div>

      {error && !isViewModalOpen && !isStatusModalOpen && (
        <div className="admin-alert admin-alert-error" style={{ marginBottom: '20px', padding: '15px', backgroundColor: '#fdf2f8', color: '#9d174d', border: '1px solid #fbcfe8', borderRadius: '8px' }}>
          {error}
        </div>
      )}

      {/* Filters & Search */}
      <div className="admin-premium-card" style={{ padding: '20px', marginBottom: '20px', display: 'flex', gap: '16px', flexWrap: 'wrap', alignItems: 'center' }}>
        <div style={{ flex: '1', minWidth: '250px' }}>
          <AsyncSelect 
            cacheOptions 
            defaultOptions 
            loadOptions={loadOptions} 
            onChange={handleSelectChange}
            isClearable
            placeholder="Search by name, email, or mobile..."
            styles={{
              control: (base) => ({
                ...base,
                padding: '2px',
                borderRadius: '8px',
                borderColor: '#d1d5db',
                boxShadow: 'none',
                '&:hover': {
                  borderColor: '#9ca3af'
                }
              })
            }}
          />
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <span style={{ fontSize: '0.875rem', fontWeight: '600', color: '#4b5563' }}>Status:</span>
          <select 
            value={statusFilter}
            onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
            className="admin-input"
            style={{ minWidth: '150px' }}
          >
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="blocked">Blocked</option>
          </select>
        </div>
      </div>

      {/* Users Table */}
      <div className="admin-premium-card" style={{ padding: '0', overflow: 'hidden' }}>
        {isLoading ? (
          <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading customers...</div>
        ) : (
          <div className="admin-table-container">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Contact</th>
                  <th>Joined</th>
                  <th>Status</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {users.length === 0 ? (
                  <tr>
                    <td colSpan="5" style={{ padding: '40px', textAlign: 'center', color: '#9ca3af' }}>
                      No customers found matching your criteria.
                    </td>
                  </tr>
                ) : (
                  users.map(user => {
                    const statusClass = user.status === 'active' ? 'admin-badge-active' : user.status === 'blocked' ? 'admin-badge-blocked' : 'admin-badge-inactive';
                    return (
                      <tr key={user.id}>
                        <td style={{ fontWeight: '600', color: '#1f2937' }}>
                          {user.name}
                        </td>
                        <td style={{ color: '#4b5563', fontSize: '0.875rem' }}>
                          <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                            <span>{user.email || <em style={{color: '#9ca3af'}}>No Email</em>}</span>
                            <span style={{ fontSize: '0.8rem', color: '#6b7280' }}>{user.mobile}</span>
                          </div>
                        </td>
                        <td style={{ color: '#6b7280', fontSize: '0.875rem' }}>
                          {user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}
                        </td>
                        <td>
                          <span className={`admin-badge ${statusClass}`}>
                            {user.status ? user.status.charAt(0).toUpperCase() + user.status.slice(1) : 'Unknown'}
                          </span>
                        </td>
                        <td style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', alignItems: 'center' }}>
                          <button 
                            onClick={() => openViewModal(user.id)}
                            className="admin-action-btn admin-action-view"
                          >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            View
                          </button>
                          <button 
                            onClick={() => openStatusModal(user)}
                            className="admin-action-btn admin-btn-secondary"
                            style={{ padding: '6px 12px' }}
                          >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Status
                          </button>
                          <button 
                            onClick={() => openAddressesModal(user)}
                            className="admin-action-btn"
                            style={{ background: '#e0e7ff', border: '1px solid #c7d2fe', color: '#4f46e5' }}
                          >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            Addresses
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
      </div>

      {/* Pagination (Simple) */}
      {!isLoading && totalPages > 1 && (
        <div style={{ display: 'flex', justifyContent: 'center', gap: '12px', marginTop: '20px' }}>
          <button 
            disabled={page <= 1} 
            onClick={() => setPage(p => p - 1)}
            style={{ padding: '8px 16px', borderRadius: '8px', border: '1px solid #d1d5db', backgroundColor: page <= 1 ? '#f3f4f6' : '#fff', color: page <= 1 ? '#9ca3af' : '#374151', cursor: page <= 1 ? 'not-allowed' : 'pointer' }}
          >
            Previous
          </button>
          <span style={{ display: 'flex', alignItems: 'center', fontSize: '0.9rem', fontWeight: '600', color: '#4b5563' }}>
            Page {page} of {totalPages}
          </span>
          <button 
            disabled={page >= totalPages} 
            onClick={() => setPage(p => p + 1)}
            style={{ padding: '8px 16px', borderRadius: '8px', border: '1px solid #d1d5db', backgroundColor: page >= totalPages ? '#f3f4f6' : '#fff', color: page >= totalPages ? '#9ca3af' : '#374151', cursor: page >= totalPages ? 'not-allowed' : 'pointer' }}
          >
            Next
          </button>
        </div>
      )}

      {/* View Modal */}
      {isViewModalOpen && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '600px' }}>
            <div className="admin-modal-header">
              <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#111827', fontWeight: '700' }}>Customer Profile</h2>
              <button onClick={closeViewModal} className="admin-modal-close">&times;</button>
            </div>
            
            <div className="admin-modal-body">
              {!selectedUser ? (
                <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading details...</div>
              ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                  <div className="admin-info-grid">
                    <div className="admin-info-box">
                      <span className="admin-info-label">Full Name</span>
                      <span className="admin-info-value">{selectedUser.name}</span>
                    </div>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Current Status</span>
                      <span className={`admin-badge ${selectedUser.status === 'active' ? 'admin-badge-active' : selectedUser.status === 'blocked' ? 'admin-badge-blocked' : 'admin-badge-inactive'}`}>
                          {selectedUser.status ? selectedUser.status.charAt(0).toUpperCase() + selectedUser.status.slice(1) : 'Unknown'}
                      </span>
                    </div>
                    
                    <div className="admin-info-box">
                      <span className="admin-info-label">Email Address</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151', wordBreak: 'break-all' }}>{selectedUser.email || <em style={{color: '#9ca3af'}}>Not provided</em>}</span>
                    </div>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Mobile Number</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>{selectedUser.mobile || <em style={{color: '#9ca3af'}}>Not provided</em>}</span>
                    </div>

                    <div className="admin-info-box">
                      <span className="admin-info-label">Date of Birth</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>{selectedUser.date_of_birth || <em style={{color: '#9ca3af'}}>Not provided</em>}</span>
                    </div>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Last Login</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>{selectedUser.last_login ? new Date(selectedUser.last_login).toLocaleString() : <em style={{color: '#9ca3af'}}>Never logged in</em>}</span>
                    </div>
                    
                    <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                      <span className="admin-info-label">Account Created On</span>
                      <span style={{ fontSize: '0.95rem', color: '#4b5563' }}>{selectedUser.created_at ? new Date(selectedUser.created_at).toLocaleString() : 'Unknown'}</span>
                    </div>
                  </div>
                </div>
              )}
            </div>
            
            <div className="admin-modal-footer">
              <button type="button" onClick={closeViewModal} className="admin-btn admin-btn-secondary">Close</button>
            </div>
          </div>
        </div>
      )}

      {/* Addresses List Modal */}
      {isAddressesModalOpen && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '700px' }}>
            <div className="admin-modal-header">
              <div>
                <h2 style={{ margin: '0 0 8px 0', fontSize: '1.25rem', color: '#111827', fontWeight: '700' }}>Addresses for {selectedAddressUser?.name}</h2>
                <p style={{ margin: 0, fontSize: '0.875rem', color: '#6b7280' }}>Manage customer delivery locations.</p>
              </div>
              <button onClick={closeAddressesModal} className="admin-modal-close">&times;</button>
            </div>
            
            <div className="admin-modal-body">
              {isFetchingAddresses ? (
                <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading addresses...</div>
              ) : userAddresses.length === 0 ? (
                <div style={{ padding: '40px', textAlign: 'center', color: '#9ca3af', backgroundColor: '#f9fafb', borderRadius: '8px', border: '1px dashed #d1d5db' }}>
                  No addresses found for this customer.
                </div>
              ) : (
                <div style={{ display: 'grid', gap: '16px', gridTemplateColumns: '1fr' }}>
                  {userAddresses.map(address => (
                    <div key={address.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '16px', border: '1px solid #e5e7eb', borderRadius: '12px', backgroundColor: '#fdfdfd' }}>
                      <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' }}>
                          <span style={{ fontWeight: '600', color: '#111827', textTransform: 'capitalize' }}>{address.address_type}</span>
                          {address.is_default === 1 && (
                            <span style={{ fontSize: '0.7rem', backgroundColor: '#dbeafe', color: '#1e40af', padding: '2px 8px', borderRadius: '9999px', fontWeight: '600' }}>Default</span>
                          )}
                          <span className={`admin-badge ${address.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>{address.status}</span>
                        </div>
                        <p style={{ fontSize: '0.875rem', color: '#4b5563', margin: '0 0 4px 0' }}>{address.door_no}, {address.street}, {address.area}</p>
                        <p style={{ fontSize: '0.875rem', color: '#4b5563', margin: '0' }}>{address.city}, {address.state} - {address.pincode}</p>
                      </div>
                      <button 
                        onClick={() => openAddressDetailModal(address.id)}
                        className="admin-btn admin-btn-secondary"
                      >
                        View Details
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
            
            <div className="admin-modal-footer">
              <button type="button" onClick={closeAddressesModal} className="admin-btn admin-btn-secondary">Close</button>
            </div>
          </div>
        </div>
      )}

      {/* Address Detail Modal */}
      {isAddressDetailModalOpen && (
        <div className="admin-modal-overlay" style={{ zIndex: 1100 }}>
          <div className="admin-modal-content" style={{ maxWidth: '500px' }}>
            <div className="admin-modal-header">
              <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#111827', fontWeight: '700' }}>Address Details</h2>
              <button onClick={closeAddressDetailModal} className="admin-modal-close">&times;</button>
            </div>
            
            <div className="admin-modal-body">
              {isFetchingAddressDetail ? (
                <div style={{ padding: '40px', textAlign: 'center', color: '#6b7280' }}>Loading details...</div>
              ) : selectedAddressDetail ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                  <div className="admin-info-grid">
                    <div className="admin-info-box">
                      <span className="admin-info-label">Type</span>
                      <span style={{ fontSize: '0.95rem', color: '#111827', textTransform: 'capitalize', fontWeight: '500' }}>{selectedAddressDetail.address_type}</span>
                    </div>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Status</span>
                      <span className={`admin-badge ${selectedAddressDetail.status === 'active' ? 'admin-badge-active' : 'admin-badge-inactive'}`}>{selectedAddressDetail.status}</span>
                    </div>

                    <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                      <span className="admin-info-label">Door / Flat No.</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>{selectedAddressDetail.door_no || '-'}</span>
                    </div>
                    <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                      <span className="admin-info-label">Street</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>{selectedAddressDetail.street || '-'}</span>
                    </div>
                    <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                      <span className="admin-info-label">Area / Locality</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>{selectedAddressDetail.area || '-'}</span>
                    </div>
                    <div className="admin-info-box" style={{ gridColumn: '1 / -1' }}>
                      <span className="admin-info-label">Landmark</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>{selectedAddressDetail.landmark || '-'}</span>
                    </div>
                    
                    <div className="admin-info-box">
                      <span className="admin-info-label">City</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>{selectedAddressDetail.city || '-'}</span>
                    </div>
                    <div className="admin-info-box">
                      <span className="admin-info-label">Pincode</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151', fontWeight: '600' }}>{selectedAddressDetail.pincode || '-'}</span>
                    </div>
                    
                    <div className="admin-info-box">
                      <span className="admin-info-label">District</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>{selectedAddressDetail.district || '-'}</span>
                    </div>
                    <div className="admin-info-box">
                      <span className="admin-info-label">State</span>
                      <span style={{ fontSize: '0.95rem', color: '#374151' }}>{selectedAddressDetail.state || '-'}</span>
                    </div>
                  </div>
                </div>
              ) : (
                <div style={{ padding: '20px', textAlign: 'center', color: '#ef4444' }}>Address not found.</div>
              )}
            </div>

            <div className="admin-modal-footer">
              <button type="button" onClick={closeAddressDetailModal} className="admin-btn admin-btn-secondary">Back to List</button>
            </div>
          </div>
        </div>
      )}

      {/* Status Update Modal */}
      {isStatusModalOpen && (
        <div className="admin-modal-overlay">
          <div className="admin-modal-content" style={{ maxWidth: '400px' }}>
            <div className="admin-modal-header">
              <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#111827', fontWeight: '700' }}>Change User Status</h2>
              <button onClick={closeStatusModal} className="admin-modal-close">&times;</button>
            </div>
            
            <div className="admin-modal-body">
              <form id="statusForm" onSubmit={handleStatusUpdate}>
                <div style={{ marginBottom: '16px' }}>
                  <label className="admin-label">Status</label>
                  <select 
                    value={statusFormData.status}
                    onChange={(e) => setStatusFormData({...statusFormData, status: e.target.value})}
                    className="admin-input"
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="blocked">Blocked</option>
                  </select>
                  <p style={{ marginTop: '8px', fontSize: '0.75rem', color: '#6b7280' }}>
                    <strong>Blocked</strong> users cannot login or checkout. <strong>Inactive</strong> is for dormant accounts.
                  </p>
                </div>
              </form>
            </div>
            
            <div className="admin-modal-footer">
              <button type="button" onClick={closeStatusModal} className="admin-btn admin-btn-secondary">Cancel</button>
              <button type="submit" form="statusForm" disabled={isUpdatingStatus} className="admin-btn admin-btn-primary" style={{ opacity: isUpdatingStatus ? 0.7 : 1, cursor: isUpdatingStatus ? 'not-allowed' : 'pointer' }}>
                {isUpdatingStatus ? 'Updating...' : 'Confirm Update'}
              </button>
            </div>
          </div>
        </div>
      )}

    </div>
  );
};

export default AdminUsers;
