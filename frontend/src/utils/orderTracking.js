/**
 * Vivisha Boutique - Order Tracking Service / Integration Layer
 * =============================================================
 * 
 * FUTURE INTEGRATION ARCHITECTURE:
 * When a third-party tracking provider (e.g., Shiprocket, Delhivery,
 * India Post, or Blue Dart) is ready to be connected, configure and
 * update the resolveTrackingDestination function below.
 * 
 * Flow:
 * Tracking ID -> Third-party tracking URL/API -> Customer tracking page
 * 
 * The UI layer calls `resolveTrackingDestination(trackingId)` which handles
 * validation and returns either the external redirect URL or the destination
 * details without needing to redesign any UI components.
 */

// Configuration for third-party courier providers (to be enabled in future phase)
export const THIRD_PARTY_TRACKING_CONFIG = {
  // Provider name placeholder (e.g., 'shiprocket', 'delhivery', 'indiapost', 'bluedart')
  provider: 'placeholder',
  
  // Flag indicating if live third-party integration is active
  isLiveIntegrationActive: false,

  // Base URL pattern for courier tracking (e.g., 'https://shiprocket.co/tracking/{TRACKING_ID}')
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
 * For now:
 * Returns temporary placeholder destination metadata for testing the navigation flow.
 * 
 * In future:
 * When live integration is activated, this will construct the third-party courier
 * tracking URL or call the tracking gateway API.
 * 
 * @param {string} rawTrackingId
 * @returns {{ isValid: boolean, error?: string, trackingId?: string, isTemporarySimulation?: boolean, destinationUrl?: string }}
 */
export const resolveTrackingDestination = (rawTrackingId) => {
  const validation = validateTrackingId(rawTrackingId);
  if (!validation.isValid) {
    return validation;
  }

  // If live integration is active in the future:
  if (THIRD_PARTY_TRACKING_CONFIG.isLiveIntegrationActive && THIRD_PARTY_TRACKING_CONFIG.trackingUrlTemplate) {
    const url = THIRD_PARTY_TRACKING_CONFIG.trackingUrlTemplate.replace('{TRACKING_ID}', encodeURIComponent(validation.trackingId));
    return {
      isValid: true,
      trackingId: validation.trackingId,
      isTemporarySimulation: false,
      destinationUrl: url
    };
  }

  // Temporary simulation placeholder mode:
  return {
    isValid: true,
    trackingId: validation.trackingId,
    isTemporarySimulation: true,
    destinationUrl: null,
    message: 'Valid tracking ID entered. Demonstrating navigation to temporary tracking destination.'
  };
};
