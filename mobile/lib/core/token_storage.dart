import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Wraps secure, platform-appropriate storage for the Sanctum bearer token (Keystore on
/// Android, IndexedDB-backed on web). Nothing else in the app reads/writes the token directly.
class TokenStorage {
  TokenStorage._();

  static const _key = 'chama_wise_api_token';
  static const _storage = FlutterSecureStorage();

  static Future<void> save(String token) => _storage.write(key: _key, value: token);

  static Future<String?> read() => _storage.read(key: _key);

  static Future<void> clear() => _storage.delete(key: _key);
}
