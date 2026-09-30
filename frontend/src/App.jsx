import React, { useState, useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { PrivateRoute } from '../Routers/PrivateRoute.jsx';
import { PublicRoute } from '../Routers/PublicRoute.jsx';
import AboutUs from './pages/AboutUs.jsx';
import ContactUs from './pages/ContactUs.jsx';
import PrivacyPolicy from './pages/PrivacyPolicy.jsx';
import TermsConditions from './pages/TermsConditions.jsx';
import TrackOrder from './pages/TrackOrder.jsx';
import PublicLayout from './layouts/PublicLayout.jsx';
import logo from '../assets/images/boutique_logo.png';

import Home from './pages/Home.jsx';
import Collections from './pages/Collections.jsx';
import ProductDetails from './pages/ProductDetails.jsx';
import Wishlist from './pages/Wishlist.jsx';
import Cart from './pages/Cart.jsx';
import Checkout from './pages/Checkout.jsx';
import Coupons from './pages/Coupons.jsx';
import { WishlistProvider } from './context/WishlistContext.jsx';
import { CartProvider } from './context/CartContext.jsx';
import { CouponProvider } from './context/CouponContext.jsx';
import { AuthProvider } from './context/AuthContext.jsx';
import { ToastProvider } from './context/ToastContext.jsx';
import Login from './pages/auth/Login.jsx';
import Register from './pages/auth/Register.jsx';
import ForgotPassword from './pages/auth/ForgotPassword.jsx';
import ScrollToTop from './components/ScrollToTop.jsx';

// Admin Panel Imports
import AdminLayout from './layouts/AdminLayout.jsx';
import AdminPlaceholder from './pages/admin/AdminPlaceholder.jsx';
import AdminCategories from './pages/admin/AdminCategories.jsx';
import AdminColours from './pages/admin/AdminColours.jsx';
import AdminSizes from './pages/admin/AdminSizes.jsx';
import AdminHsn from './pages/admin/AdminHsn.jsx';
import AdminProducts from './pages/admin/AdminProducts.jsx';
import AdminVariants from './pages/admin/AdminVariants.jsx';
import AdminNewArrivals from './pages/admin/AdminNewArrivals.jsx';
import AdminTopSelling from './pages/admin/AdminTopSelling.jsx';
import AdminUsers from './pages/admin/AdminUsers.jsx';
import AdminOrders from './pages/admin/AdminOrders.jsx';
import AdminPayments from './pages/admin/AdminPayments.jsx';
import AdminRefunds from './pages/admin/AdminRefunds.jsx';
import AdminDashboard from './pages/admin/AdminDashboard.jsx';
import AdminLogin from './pages/admin/AdminLogin.jsx';
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
      <ToastProvider>
        <WishlistProvider>
          <CartProvider>
            <CouponProvider>
              <BrowserRouter>
              <ScrollToTop />
              <Routes>
                {/* Customer-Facing Public Website Routes */}
                <Route element={<PublicLayout />}>
                  <Route path="/" element={<Home />} />
                  <Route path="/login" element={<Login />} />
                  <Route path="/register" element={<Register />} />
                  <Route path="/forgot-password" element={<ForgotPassword />} />

                  {/* Unrestricted Information & Store Pages */}
                  <Route path="/collections" element={<Collections />} />
                  <Route path="/product/:id" element={<ProductDetails />} />
                  <Route path="/coupons" element={<Coupons />} />
                  <Route path="/about" element={<AboutUs />} />
                  <Route path="/contact" element={<ContactUs />} />
                  <Route path="/privacy-policy" element={<PrivacyPolicy />} />
                  <Route path="/terms" element={<TermsConditions />} />
                  <Route path="/track-order" element={<TrackOrder />} />

                  {/* Customer Account Pages wrapped in PublicLayout */}
                  <Route path="/notifications" element={<CustomerProfile defaultTab="notifications" />} />
                </Route>

                {/* Private Customer Routes */}
                <Route element={<PrivateRoute />}>
                  <Route element={<PublicLayout />}>
                    <Route path="/dashboard" element={<CustomerProfile defaultTab="profile" />} />
                    <Route path="/profile" element={<CustomerProfile defaultTab="profile" />} />
                    <Route path="/orders" element={<CustomerProfile defaultTab="orders" />} />
                    <Route path="/addresses" element={<CustomerProfile defaultTab="addresses" />} />
                    <Route path="/wishlist" element={<Wishlist />} />
                    <Route path="/cart" element={<Cart />} />
                    <Route path="/checkout" element={<Checkout />} />
                  </Route>
                </Route>


              {/* Dedicated Admin Login */}
              <Route path="/admin/login" element={<AdminLogin />} />

              {/* Admin Panel Shell Layout & Modules */}
              <Route path="/admin" element={<AdminLayout />}>
                <Route index element={<Navigate to="/admin/dashboard" replace />} />
                <Route path="dashboard" element={<AdminDashboard />} />
                <Route path="reports" element={<AdminPlaceholder moduleKey="reports" />} />
                <Route path="products" element={<AdminProducts />} />
                <Route path="variants" element={<AdminVariants />} />
                <Route path="new-arrivals" element={<AdminNewArrivals />} />
                <Route path="top-selling" element={<AdminTopSelling />} />
                <Route path="categories" element={<AdminCategories />} />
                <Route path="colours" element={<AdminColours />} />
                <Route path="sizes" element={<AdminSizes />} />
                <Route path="hsn" element={<AdminHsn />} />
                <Route path="banners-coupons" element={<AdminPlaceholder moduleKey="banners-coupons" />} />
                <Route path="suppliers" element={<AdminPlaceholder moduleKey="suppliers" />} />
                <Route path="purchases" element={<AdminPlaceholder moduleKey="purchases" />} />
                <Route path="pos" element={<AdminPlaceholder moduleKey="pos" />} />
                <Route path="orders" element={<AdminOrders />} />
                <Route path="customers" element={<AdminUsers />} />
                <Route path="payments" element={<AdminPayments />} />
                <Route path="returns" element={<AdminRefunds />} />
                <Route path="whatsapp" element={<AdminPlaceholder moduleKey="whatsapp" />} />
              </Route>

            </Routes>
          </BrowserRouter>
          </CouponProvider>
        </CartProvider>
      </WishlistProvider>
    </ToastProvider>
  </AuthProvider>
);
}


export default App;
