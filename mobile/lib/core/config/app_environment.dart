import 'package:flutter/foundation.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';

class AppEnvironment {
  const AppEnvironment._();

  static const _emulatorBaseUrl = 'http://10.0.2.2:8000/api/v1';
  static const _usbReverseBaseUrl = 'http://127.0.0.1:8000/api/v1';

  static String? _explicitBaseUrl() {
    const defined = String.fromEnvironment('API_BASE_URL');
    if (defined.isNotEmpty) return defined;
    if (dotenv.isInitialized) {
      final fromDotEnv = dotenv.env['API_BASE_URL'];
      if (fromDotEnv != null && fromDotEnv.isNotEmpty) return fromDotEnv;
    }
    return null;
  }

  static String get apiBaseUrl =>
      _explicitBaseUrl() ??
      (kDebugMode ? _usbReverseBaseUrl : _emulatorBaseUrl);

  static List<String> get connectionFallbackBaseUrls {
    if (!kDebugMode) return const <String>[];
    final current = apiBaseUrl;
    return <String>[
      _usbReverseBaseUrl,
      _emulatorBaseUrl,
    ].where((baseUrl) => baseUrl != current).toList();
  }
}
