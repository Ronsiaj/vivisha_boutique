import React from 'react';

const AdminPagination = ({ currentPage, totalPages, onPageChange }) => {
  if (totalPages <= 1) return null;

  const renderPageNumbers = () => {
    let pages = [];
    let startPage = Math.max(1, currentPage - 1);
    let endPage = Math.min(totalPages, currentPage + 1);

    if (startPage > 1) {
      pages.push(
        <button
          key={1}
          type="button"
          className={`admin-pagination-btn ${currentPage === 1 ? 'active' : ''}`}
          onClick={() => onPageChange(1)}
          aria-label="Page 1"
        >
          1
        </button>
      );
      if (startPage > 2) {
        pages.push(<span key="dots-1" className="admin-pagination-dots">...</span>);
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      pages.push(
        <button
          key={i}
          type="button"
          className={`admin-pagination-btn ${currentPage === i ? 'active' : ''}`}
          onClick={() => onPageChange(i)}
          aria-label={`Page ${i}`}
        >
          {i}
        </button>
      );
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        pages.push(<span key="dots-2" className="admin-pagination-dots">...</span>);
      }
      pages.push(
        <button
          key={totalPages}
          type="button"
          className={`admin-pagination-btn ${currentPage === totalPages ? 'active' : ''}`}
          onClick={() => onPageChange(totalPages)}
          aria-label={`Page ${totalPages}`}
        >
          {totalPages}
        </button>
      );
    }
    return pages;
  };

  return (
    <div className="admin-pagination-container">
      <button 
        type="button"
        className="admin-pagination-arrow" 
        disabled={currentPage === 1}
        onClick={() => onPageChange(currentPage - 1)}
        aria-label="Previous page"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </button>

      {renderPageNumbers()}

      <button 
        type="button"
        className="admin-pagination-arrow" 
        disabled={currentPage === totalPages}
        onClick={() => onPageChange(currentPage + 1)}
        aria-label="Next page"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
      </button>
    </div>
  );
};

export default AdminPagination;
