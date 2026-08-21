import React, { useState, useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { PrivateRoute } from '../Routers/PrivateRoute.jsx';
import { PublicRoute } from '../Routers/PublicRoute.jsx';
import AboutUs from './pages/AboutUs.jsx';
import ContactUs from './pages/ContactUs.jsx';
import PrivacyPolicy from './pages/PrivacyPolicy.jsx';
import TermsConditions from './pages/TermsConditions.jsx';
import PublicLayout from './layouts/PublicLayout.jsx';
import logo from '../assets/images/boutique_logo.png';

import Home from './pages/Home.jsx';
import Collections from './pages/Collections.jsx';
import ProductDetails from './pages/ProductDetails.jsx';
import Wishlist from './pages/Wishlist.jsx';
import Cart from './pages/Cart.jsx';
import Checkout from './pages/Checkout.jsx';
import { WishlistProvider } from './context/WishlistContext.jsx';
import { CartProvider } from './context/CartContext.jsx';
import { AuthProvider } from './context/AuthContext.jsx';
import Login from './pages/auth/Login.jsx';
import Register from './pages/auth/Register.jsx';

// Admin Panel Imports
import AdminLayout from './layouts/AdminLayout.jsx';
import AdminPlaceholder from './pages/admin/AdminPlaceholder.jsx';
import AdminCategories from './pages/admin/AdminCategories.jsx';
import AdminProducts from './pages/admin/AdminProducts.jsx';
import AdminVariants from './pages/admin/AdminVariants.jsx';
import AdminUsers from './pages/admin/AdminUsers.jsx';

import CustomerProfile from './pages/CustomerProfile.jsx';

function App() {
  const [isAppLoading, setIsAppLoading] = useState(true);

  useEffect(() => {
    // Simulate app initialization time
    const timer = setTimeout(() => {
      setIsAppLoading(false);
    }, 2500);
    return () => clearTimeout(timer);
  }, []);

  if (isAppLoading) {
    return (
      <div className="splash-screen">
        <img src={logo} alt="Vivisha Boutique Logo" className="splash-logo" />
      </div>
    );
  }

  return (
    <AuthProvider>
      <WishlistProvider>
        <CartProvider>
          <BrowserRouter>
            <Routes>
              {/* Customer-Facing Public Website Routes */}
              <Route element={<PublicLayout />}>
                <Route path="/" element={<Home />} />
                <Route path="/login" element={<Login />} />
                <Route path="/register" element={<Register />} />

                {/* Unrestricted Information & Store Pages */}
                <Route path="/collections" element={<Collections />} />
                <Route path="/product/:id" element={<ProductDetails />} />
                <Route path="/about" element={<AboutUs />} />
                <Route path="/contact" element={<ContactUs />} />
                <Route path="/privacy-policy" element={<PrivacyPolicy />} />
                <Route path="/terms" element={<TermsConditions />} />

                {/* Customer Account Pages wrapped in PublicLayout */}
                <Route path="/notifications" element={<CustomerProfile defaultTab="notifications" />} />
              </Route>

              {/* Private Customer Routes */}
              <Route element={<PrivateRoute />}>
                <Route element={<PublicLayout />}>
                  <Route path="/dashboard" element={<CustomerProfile defaultTab="profile" />} />
                  <Route path="/profile" element={<CustomerProfile defaultTab="profile" />} />
                  <Route path="/orders" element={<CustomerProfile defaultTab="orders" />} />
                  <Route path="/track-order" element={<CustomerProfile defaultTab="track" />} />
                  <Route path="/wishlist" element={<Wishlist />} />
                  <Route path="/cart" element={<Cart />} />
                  <Route path="/checkout" element={<Checkout />} />
                </Route>
              </Route>

              {/* Redirect Old Standalone Admin Login to Common /login */}
              <Route path="/admin/login" element={<Navigate to="/login" replace />} />

              {/* Admin Panel Shell Layout & Modules */}
              <Route path="/admin" element={<AdminLayout />}>
                <Route index element={<Navigate to="/admin/dashboard" replace />} />
                <Route path="dashboard" element={<AdminPlaceholder moduleKey="dashboard" />} />
                <Route path="reports" element={<AdminPlaceholder moduleKey="reports" />} />
                <Route path="products" element={<AdminProducts />} />
                <Route path="variants" element={<AdminVariants />} />
                <Route path="categories" element={<AdminCategories />} />
                <Route path="banners-coupons" element={<AdminPlaceholder moduleKey="banners-coupons" />} />
                <Route path="suppliers" element={<AdminPlaceholder moduleKey="suppliers" />} />
                <Route path="purchases" element={<AdminPlaceholder moduleKey="purchases" />} />
                <Route path="pos" element={<AdminPlaceholder moduleKey="pos" />} />
                <Route path="orders" element={<AdminPlaceholder moduleKey="orders" />} />
                <Route path="customers" element={<AdminUsers />} />
                <Route path="payments" element={<AdminPlaceholder moduleKey="payments" />} />
                <Route path="returns" element={<AdminPlaceholder moduleKey="returns" />} />
                <Route path="whatsapp" element={<AdminPlaceholder moduleKey="whatsapp" />} />
              </Route>

            </Routes>
          </BrowserRouter>
        </CartProvider>
      </WishlistProvider>
    </AuthProvider>
  );
}

export default App;
