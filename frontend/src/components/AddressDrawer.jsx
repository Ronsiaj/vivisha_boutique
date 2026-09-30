import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext.jsx';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const AddressDrawer = ({
  isOpen,
  onClose,
  savedAddresses = [],
  selectedAddressId = null,
  onSelectAddress,
  onAddressSaved,
  onAddressDeleted
}) => {
  const { user, token } = useAuth();

  const [isAddingNewAddress, setIsAddingNewAddress] = useState(false);
  const [editingAddressId, setEditingAddressId] = useState(null);
  const [addressError, setAddressError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

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

  if (!isOpen) return null;

  const handleOpenAddNew = () => {
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
    setIsAddingNewAddress(true);
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
    setIsAddingNewAddress(true);
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

    setIsSubmitting(true);
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
        setIsAddingNewAddress(false);
        setEditingAddressId(null);
        if (onAddressSaved) {
          await onAddressSaved(data.data?.address?.id || editingAddressId);
        }
      } else {
        setAddressError(data.message || 'Error saving address');
      }
    } catch (err) {
      console.error('Error saving address', err);
      setAddressError('Network error saving address. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleAddressSelect = async (id) => {
    if (onSelectAddress) {
      onSelectAddress(id);
    }
    try {
      await fetch(`${API_BASE_URL}/address/select_address.php`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ address_id: id })
      });
    } catch (err) {
      console.error('Error setting default address', err);
    }
  };

  const handleDeleteAddress = async (id, e) => {
    if (e) e.stopPropagation();
    if (!window.confirm('Are you sure you want to remove this address?')) return;
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
      if (data.status && onAddressDeleted) {
        onAddressDeleted(id);
      }
    } catch (err) {
      console.error('Error deleting address:', err);
    }
  };

  return (
    <div
      className="address-drawer-overlay open"
      onClick={onClose}
    >
      <div
        className="address-drawer open"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="drawer-header">
          <div className="drawer-title-box">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
              <circle cx="12" cy="10" r="3"></circle>
            </svg>
            <h3>{isAddingNewAddress ? (editingAddressId ? 'Edit Address' : 'Add New Address') : 'Select Delivery Address'}</h3>
          </div>
          <button
            className="drawer-close-btn"
            onClick={onClose}
          >
            ✕
          </button>
        </div>

        <div className="drawer-body">
          {!isAddingNewAddress ? (
            <>
              {savedAddresses.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '30px 16px', color: '#6b7280' }}>
                  <div style={{ fontSize: '2rem', marginBottom: '8px' }}>📍</div>
                  <h4 style={{ margin: '0 0 6px 0', color: '#374151' }}>No Saved Addresses</h4>
                  <p style={{ fontSize: '0.88rem', margin: '0 0 16px 0' }}>
                    Add a delivery address to view and apply available discount coupons.
                  </p>
                </div>
              ) : (
                <div className="drawer-addresses-list">
                  {savedAddresses.map((addr) => {
                    const isSelected = addr.id === selectedAddressId;
                    return (
                      <div
                        key={addr.id}
                        className={`drawer-address-item ${isSelected ? 'selected' : ''}`}
                        onClick={() => handleAddressSelect(addr.id)}
                      >
                        <div className="radio-col">
                          <input
                            type="radio"
                            name="drawerAddressSelect"
                            checked={isSelected}
                            onChange={() => handleAddressSelect(addr.id)}
                          />
                        </div>
                        <div className="info-col">
                          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
                            <strong className="user-name">{addr.user_name || user?.name}</strong>
                            <span style={{ fontSize: '0.7rem', padding: '2px 6px', borderRadius: '4px', background: 'var(--border-color, #f0e6f6)', fontWeight: '600' }}>
                              {(addr.address_type || 'home').toUpperCase()}
                            </span>
                            {addr.is_default === 1 && (
                              <span style={{ fontSize: '0.7rem', padding: '2px 6px', borderRadius: '4px', background: '#dcfce7', color: '#166534', fontWeight: 'bold' }}>
                                DEFAULT
                              </span>
                            )}
                          </div>
                          <p className="full-address">
                            {addr.door_no}, {addr.street}, {addr.area}, {addr.city}{addr.district ? `, ${addr.district}` : ''}, {addr.state} - {addr.pincode}
                          </p>
                          {addr.landmark && (
                            <p style={{ fontSize: '0.8rem', color: '#666', marginTop: '2px' }}>Landmark: {addr.landmark}</p>
                          )}
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                          <button
                            type="button"
                            className="edit-pencil-btn"
                            onClick={(e) => handleEditAddress(addr, e)}
                            title="Edit Address"
                          >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M12 20h9"></path>
                              <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                          </button>
                          <button
                            type="button"
                            style={{ background: 'none', border: 'none', color: '#dc2626', cursor: 'pointer', padding: '4px' }}
                            onClick={(e) => handleDeleteAddress(addr.id, e)}
                            title="Delete Address"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <polyline points="3 6 5 6 21 6"></polyline>
                              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}

              <button
                type="button"
                className="btn-add-new-address-trigger"
                onClick={handleOpenAddNew}
              >
                <span className="plus-icon">+</span> ADD NEW ADDRESS
              </button>
            </>
          ) : (
            <form onSubmit={handleSaveAddressSubmit} className="drawer-address-form">
              {addressError && (
                <div style={{ padding: '10px 14px', background: '#fee2e2', color: '#991b1b', borderRadius: '6px', fontSize: '0.85rem', marginBottom: '14px' }}>
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
                <label htmlFor="drawerDoorNo">Door No. *</label>
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
                  onClick={() => setIsAddingNewAddress(false)}
                  disabled={isSubmitting}
                >
                  Cancel
                </button>
                <button type="submit" className="btn-save-address" disabled={isSubmitting}>
                  {isSubmitting ? 'Saving...' : 'Save Address'}
                </button>
              </div>
            </form>
          )}
        </div>

        <div className="drawer-footer">
          {!isAddingNewAddress && (
            <button
              type="button"
              className="btn-drawer-done"
              onClick={onClose}
            >
              DONE
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default AddressDrawer;
