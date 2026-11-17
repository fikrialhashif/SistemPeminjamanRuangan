import 'package:flutter_dotenv/flutter_dotenv.dart';

class AppEnvironment {
  const AppEnvironment._();

  static const _fallbackBaseUrl = 'http://10.0.2.2:8000/api/v1';

  static String get apiBaseUrl {
    const defined = String.fromEnvironment('API_BASE_URL');
    if (defined.isNotEmpty) return defined;
    if (dotenv.isInitialized) {
      return dotenv.env['API_BASE_URL'] ?? _fallbackBaseUrl;
    }
    return _fallbackBaseUrl;
  }
}
