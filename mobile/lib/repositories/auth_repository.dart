import '../core/api_client.dart';
import '../core/models/user.dart';
import '../core/token_storage.dart';

class AuthRepository {
  AuthRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<AppUser> login({required String login, required String password, required String deviceName}) async {
    final json = await _client.post('/auth/login', {
      'login': login,
      'password': password,
      'device_name': deviceName,
    });
    await TokenStorage.save(json['token'] as String);

    return AppUser.fromJson(json['user'] as Map<String, dynamic>);
  }

  Future<AppUser> register({
    required String name,
    required String username,
    required String email,
    required String password,
    required String passwordConfirmation,
    required String deviceName,
  }) async {
    final json = await _client.post('/auth/register', {
      'name': name,
      'username': username,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'device_name': deviceName,
    });
    await TokenStorage.save(json['token'] as String);

    return AppUser.fromJson(json['user'] as Map<String, dynamic>);
  }

  Future<void> logout() async {
    try {
      await _client.post('/auth/logout');
    } finally {
      await TokenStorage.clear();
    }
  }

  Future<AppUser> me() async {
    final json = await _client.get('/auth/me');

    return AppUser.fromJson(json['data'] as Map<String, dynamic>);
  }
}
