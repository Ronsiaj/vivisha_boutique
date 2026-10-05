/**
 * Vivisha Boutique - Order Tracking Service / Integration Layer
 * =============================================================
 * 
 * THIRD-PARTY COURIER TRACKING ARCHITECTURE:
 * Order shipment tracking is handled through third-party courier/shipping
 * tracking websites (e.g., India Post, Shiprocket, Delhivery, Blue Dart).
 * 
 * Once an order is dispatched, the admin team shares the customer's
 * Tracking ID / Shipment ID along with the courier tracking portal via WhatsApp.
 * 
 * Centralized Configuration:
 * To update or activate a direct third-party tracking portal URL in the future,
 * configure `THIRD_PARTY_TRACKING_CONFIG` below.
 */

// Configuration for third-party courier tracking partner
export const THIRD_PARTY_TRACKING_CONFIG = {
  // Provider name (e.g., 'Courier Partner', 'India Post', 'Shiprocket', 'Delhivery')
  providerName: 'Courier Partner',
  
  // Flag indicating if direct URL redirection to third-party courier tracking is active
  isLiveIntegrationActive: false,

  // Configurable third-party tracking URL template (easy to update in one place when final partner URL is confirmed)
  // Example: 'https://www.indiapost.gov.in/_layouts/15/dop.portal.tracking/trackconsignment.aspx?id={TRACKING_ID}'
  // or 'https://shiprocket.co/tracking/{TRACKING_ID}'
  trackingUrlTemplate: null,
};

/**
 * Validates a user-supplied tracking ID.
 * 
 * @param {string} trackingId - The tracking ID entered by the customer.
 * @returns {{ isValid: boolean, error?: string, trackingId?: string }}
 */
export const validateTrackingId = (trackingId) => {
  const trimmed = (trackingId || '').trim();

  if (!trimmed) {
    return {
      isValid: false,
      error: 'Please enter your tracking ID.'
    };
  }

  if (trimmed.length < 3) {
    return {
      isValid: false,
      error: 'Tracking ID must be at least 3 characters.'
    };
  }

  if (trimmed.length > 50) {
    return {
      isValid: false,
      error: 'Tracking ID cannot exceed 50 characters.'
    };
  }

  // Allow alphanumeric characters, hyphens, and underscores
  const validPattern = /^[A-Za-z0-9\-_]+$/;
  if (!validPattern.test(trimmed)) {
    return {
      isValid: false,
      error: 'Tracking ID can only contain letters, numbers, hyphens (-), and underscores (_).'
    };
  }

  return {
    isValid: true,
    trackingId: trimmed
  };
};

/**
 * Resolves the tracking destination for the given tracking ID.
 * 
 * If a live third-party courier tracking URL is configured, it constructs the URL.
 * Otherwise, it resolves with the verified tracking ID for the third-party courier guidance view.
 * 
 * @param {string} rawTrackingId
 * @returns {{ isValid: boolean, error?: string, trackingId?: string, isThirdPartyRedirect?: boolean, destinationUrl?: string | null }}
 */
export const resolveTrackingDestination = (rawTrackingId) => {
  const validation = validateTrackingId(rawTrackingId);
  if (!validation.isValid) {
    return validation;
  }

  // If live third-party courier integration is active:
  if (THIRD_PARTY_TRACKING_CONFIG.isLiveIntegrationActive && THIRD_PARTY_TRACKING_CONFIG.trackingUrlTemplate) {
    const url = THIRD_PARTY_TRACKING_CONFIG.trackingUrlTemplate.replace('{TRACKING_ID}', encodeURIComponent(validation.trackingId));
    return {
      isValid: true,
      trackingId: validation.trackingId,
      isThirdPartyRedirect: true,
      destinationUrl: url
    };
  }

  // Standard third-party courier tracking flow:
  return {
    isValid: true,
    trackingId: validation.trackingId,
    isThirdPartyRedirect: false,
    destinationUrl: null
  };
};

