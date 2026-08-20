import React from 'react';
import Header from '../components/Header';
import Footer from '../components/Footer';
import MobileBottomNav from '../components/MobileBottomNav';
import { Outlet } from 'react-router-dom';

const PublicLayout = ({ children }) => {
  return (
    <div className="public-layout">
      <Header />
      <main className="main-content">
        {children || <Outlet />}
      </main>
      <Footer />
      <MobileBottomNav />
    </div>
  );
};

export default PublicLayout;
