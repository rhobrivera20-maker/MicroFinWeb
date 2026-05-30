import 'dart:io';
import 'package:http/http.dart' as http;
import 'api_config_local.dart';

/// Centralized configuration manager
/// Checks local availability first, falls back to production
class Config {
  // Production (Railway) URLs
  static const String _productionAppBaseUrl =
      'https://microfinweb-production-e3b0.up.railway.app';
  static const String _productionApiPath = '/admin-draft/microfin_backend/mobile_api';
  static const String _productionEmailServiceUrl = '';
  static const String _productionNotificationServiceUrl = '';

  // Environment variable overrides
  static const String _apiBaseUrlOverride = String.fromEnvironment(
    'API_BASE_URL',
  );
  static const String _appBaseUrlOverride = String.fromEnvironment(
    'APP_BASE_URL',
  );

  // Availability cache
  static bool _localhostChecked = false;
  static bool _localhostAvailable = false;
  static bool _emailServiceChecked = false;
  static bool _emailServiceAvailable = false;
  static bool _notificationServiceChecked = false;
  static bool _notificationServiceAvailable = false;

  // ─── App Base URL ────────────────────────────────────────────────
  static String get appBaseUrl {
    // Use environment variable override if set
    if (_appBaseUrlOverride.isNotEmpty) {
      return _appBaseUrlOverride;
    }

    // Check if local dev is enabled
    if (!ConfigLocal.useLocalDev) {
      return _productionAppBaseUrl;
    }

    // Use platform-specific URL from ConfigLocal
    final localUrl = ConfigLocal.appBaseUrl;
    if (localUrl.isNotEmpty) {
      return localUrl;
    }

    // If skipLocalhostCheck is true, force use localhost (web only)
    if (ConfigLocal.skipLocalhostCheck) {
      return ConfigLocal.localDevAppBaseUrl;
    }

    // Check localhost availability once
    if (!_localhostChecked) {
      _checkLocalhostAvailability();
      _localhostChecked = true;
    }

    // Use localhost if available, otherwise fallback to production
    return _localhostAvailable ? ConfigLocal.localDevAppBaseUrl : _productionAppBaseUrl;
  }

  // ─── API Base URL ─────────────────────────────────────────────────
  static String get apiBaseUrl {
    final raw = _apiBaseUrlOverride.isNotEmpty
        ? _apiBaseUrlOverride
        : '$appBaseUrl${appBaseUrl == _productionAppBaseUrl ? _productionApiPath : ConfigLocal.localDevApiPath}';
    final normalized = _stripTrailingSlashes(raw);
    return (normalized.endsWith('/api') || normalized.endsWith('/mobile_api')) ? normalized : '$normalized/api';
  }

  // ─── Email Service URL ────────────────────────────────────────────
  static String get emailServiceUrl {
    if (ConfigLocal.useLocalEmailService && ConfigLocal.localEmailServiceUrl.isNotEmpty) {
      return ConfigLocal.localEmailServiceUrl;
    }
    return _productionEmailServiceUrl;
  }

  static bool get useEmailService {
    if (!ConfigLocal.useLocalEmailService) {
      return _productionEmailServiceUrl.isNotEmpty;
    }

    if (!_emailServiceChecked) {
      _checkEmailServiceAvailability();
      _emailServiceChecked = true;
    }

    return _emailServiceAvailable;
  }

  // ─── Notification Service URL ───────────────────────────────────────
  static String get notificationServiceUrl {
    if (ConfigLocal.useLocalNotificationService && ConfigLocal.localNotificationServiceUrl.isNotEmpty) {
      return ConfigLocal.localNotificationServiceUrl;
    }
    return _productionNotificationServiceUrl;
  }

  static bool get useNotificationService {
    if (!ConfigLocal.useLocalNotificationService) {
      return _productionNotificationServiceUrl.isNotEmpty;
    }

    if (!_notificationServiceChecked) {
      _checkNotificationServiceAvailability();
      _notificationServiceChecked = true;
    }

    return _notificationServiceAvailable;
  }

  // ─── Helper Methods ────────────────────────────────────────────────
  static String getUrl(String endpoint) {
    if (endpoint.startsWith('http')) return endpoint;

    final path = endpoint.startsWith('/') ? endpoint : '/$endpoint';
    return '$apiBaseUrl$path';
  }

  static String _stripTrailingSlashes(String value) {
    return value.trim().replaceFirst(RegExp(r'/+$'), '');
  }

  // ─── Availability Checks ───────────────────────────────────────────
  static Future<void> _checkLocalhostAvailability() async {
    try {
      final uri = Uri.parse('${ConfigLocal.localDevAppBaseUrl}${ConfigLocal.localDevApiPath}');
      final request = await http.head(uri).timeout(
        const Duration(seconds: 5),
        onTimeout: () {
          throw Exception('Timeout');
        },
      );
      _localhostAvailable = request.statusCode == 200 || request.statusCode == 404;
    } catch (e) {
      _localhostAvailable = false;
    }
  }

  static Future<void> _checkEmailServiceAvailability() async {
    if (ConfigLocal.localEmailServiceUrl.isEmpty) {
      _emailServiceAvailable = false;
      return;
    }
    try {
      final uri = Uri.parse(ConfigLocal.localEmailServiceUrl);
      final request = await http.head(uri).timeout(
        const Duration(seconds: 2),
        onTimeout: () {
          throw Exception('Timeout');
        },
      );
      _emailServiceAvailable = request.statusCode == 200 || request.statusCode == 404;
    } catch (e) {
      _emailServiceAvailable = false;
    }
  }

  static Future<void> _checkNotificationServiceAvailability() async {
    if (ConfigLocal.localNotificationServiceUrl.isEmpty) {
      _notificationServiceAvailable = false;
      return;
    }
    try {
      final uri = Uri.parse(ConfigLocal.localNotificationServiceUrl);
      final request = await http.head(uri).timeout(
        const Duration(seconds: 2),
        onTimeout: () {
          throw Exception('Timeout');
        },
      );
      _notificationServiceAvailable = request.statusCode == 200 || request.statusCode == 404;
    } catch (e) {
      _notificationServiceAvailable = false;
    }
  }

  // ─── Force Refresh Checks (for testing) ───────────────────────────
  static Future<void> refreshAllChecks() async {
    _localhostChecked = false;
    _emailServiceChecked = false;
    _notificationServiceChecked = false;
    await _checkLocalhostAvailability();
    await _checkEmailServiceAvailability();
    await _checkNotificationServiceAvailability();
    _localhostChecked = true;
    _emailServiceChecked = true;
    _notificationServiceChecked = true;
  }
}
