import 'config.dart';

/// API Configuration
/// This is now a thin wrapper around the centralized Config class
/// for backward compatibility
class ApiConfig {
  @Deprecated('Use Config.appBaseUrl instead')
  static String get appBaseUrl => Config.appBaseUrl;

  @Deprecated('Use Config.apiBaseUrl instead')
  static String get baseUrl => Config.apiBaseUrl;

  static String getUrl(String endpoint) {
    return Config.getUrl(endpoint);
  }

  @Deprecated('Use Config.refreshAllChecks() instead')
  static Future<void> refreshLocalhostCheck() async {
    await Config.refreshAllChecks();
  }
}
