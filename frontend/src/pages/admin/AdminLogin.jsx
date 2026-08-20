import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import logo from '../../../assets/images/boutique_logo.png';

const AdminLogin = () => {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    email: 'admin@vivishaboutique.com',
    password: '••••••••',
    rememberMe: true
  });
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!formData.email || !formData.password) {
      setError('Please enter your admin email and password.');
      return;
    }

    setError('');
    setIsLoading(true);

    // Simulate mock authentication dispatch
    setTimeout(() => {
      setIsLoading(false);
      navigate('/admin/dashboard');
    }, 800);
  };

  return (
    <div className="admin-login-page">
      <div className="admin-login-card">
        {/* Brand Header */}
        <div className="login-brand-header">
          <Link to="/" title="Go to Store Homepage">
            <img src={logo} alt="Vivisha Boutique Logo" className="login-logo" />
          </Link>
          <h1 className="login-title">Vivisha Boutique Admin</h1>
          <p className="login-subtitle">Sign in to access your e-commerce management panel</p>
        </div>

        {/* Error Alert */}
        {error && (
          <div className="admin-alert admin-alert-error">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span>{error}</span>
          </div>
        )}

        {/* Login Form */}
        <form onSubmit={handleSubmit} className="admin-login-form">
          <div className="form-group">
            <label htmlFor="email">Admin Email / Username</label>
            <div className="input-with-icon">
              <span className="input-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                  <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
              </span>
              <input
                type="email"
                id="email"
                name="email"
                placeholder="admin@vivishaboutique.com"
                value={formData.email}
                onChange={handleChange}
                required
              />
            </div>
          </div>

          <div className="form-group">
            <label htmlFor="password">Password</label>
            <div className="input-with-icon">
              <span className="input-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
              </span>
              <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter password"
                value={formData.password}
                onChange={handleChange}
                required
              />
            </div>
          </div>

          <div className="form-options">
            <label className="remember-checkbox">
              <input
                type="checkbox"
                name="rememberMe"
                checked={formData.rememberMe}
                onChange={handleChange}
              />
              <span>Remember me</span>
            </label>
            <a href="#forgot" onClick={(e) => { e.preventDefault(); alert('Please contact system administrator to reset password.'); }} className="forgot-password-link">
              Forgot Password?
            </a>
          </div>

          <button
            type="submit"
            className="admin-btn admin-btn-primary admin-login-btn"
            disabled={isLoading}
          >
            {isLoading ? (
              <span className="btn-spinner">Signing in...</span>
            ) : (
              <span>Sign In to Admin Panel</span>
            )}
          </button>
        </form>

        {/* Footer Link back to Website */}
        <div className="login-card-footer">
          <Link to="/" className="back-to-store-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Back to Store Website</span>
          </Link>
        </div>
      </div>
    </div>
  );
};

export default AdminLogin;
