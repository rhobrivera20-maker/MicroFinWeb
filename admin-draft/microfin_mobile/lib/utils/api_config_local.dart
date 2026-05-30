import 'dart:io' show Platform;

/// Local development configuration
/// This file should be added to .gitignore
/// Each developer can configure their local environment here

class ConfigLocal {
  /// Local development base URL for web (Flutter web on Chrome)
  /// Change this to match your local development server
  static const String localDevAppBaseUrl =
      'http://127.0.0.1/admin-draft-withmobile';

  /// Local development base URL for mobile (APK/emulator)
  /// Use your machine's LAN IP for mobile to connect to local server
  /// Example: 'http://192.168.1.100/admin-draft-withmobile'
  static const String localDevAppBaseUrlMobile =
      'http://192.168.1.100/admin-draft-withmobile';

  /// Local development API path
  static const String localDevApiPath = '/admin-draft/microfin_backend/mobile_api';

  /// Skip localhost availability check (force use localhost)
  /// Set to true if you know your local server is running but the check fails
  static const bool skipLocalhostCheck = false;

  /// Use local development server (false = use production Railway)
  /// When true, tries local first and falls back to Railway if unavailable
  static const bool useLocalDev = false;

  /// Email/OTP service configuration
  static const String localEmailServiceUrl = '';
  static const bool useLocalEmailService = false;

  /// Notification service configuration
  static const String localNotificationServiceUrl = '';
  static const bool useLocalNotificationService = false;

  /// Get the appropriate base URL based on platform
  static String get appBaseUrl {
    if (!useLocalDev) {
      return ''; // Will fall back to production
    }

    // For web, use 127.0.0.1
    // For mobile (APK/emulator), use LAN IP
    try {
      if (Platform.isAndroid || Platform.isIOS) {
        return localDevAppBaseUrlMobile;
      }
    } catch (e) {
      // Platform operations not supported (e.g., web)
      // Default to web URL
    }
    return localDevAppBaseUrl;
  }
}
