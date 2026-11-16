import 'package:flutter/foundation.dart';

import '../core/api_exception.dart';
import '../core/models/user.dart';
import '../core/token_storage.dart';
import '../repositories/auth_repository.dart';

enum AuthStatus { unknown, signedOut, signedIn }

class AuthProvider extends ChangeNotifier {
  AuthProvider({AuthRepository? repository}) : _repository = repository ?? AuthRepository();

  final AuthRepository _repository;

  AuthStatus status = AuthStatus.unknown;
  AppUser? user;
  bool isBusy = false;
  String? error;

  /// Called once at app start: if a token is already stored, confirm it still works by calling
  /// `/auth/me` rather than trusting its mere presence (it may have been revoked server-side).
  Future<void> tryAutoLogin() async {
    final token = await TokenStorage.read();
    if (token == null) {
      status = AuthStatus.signedOut;
      notifyListeners();

      return;
    }

    try {
      user = await _repository.me();
      status = AuthStatus.signedIn;
    } on ApiException {
      await TokenStorage.clear();
      status = AuthStatus.signedOut;
    }
    notifyListeners();
  }

  Future<bool> login(String login, String password) => _run(() async {
        user = await _repository.login(login: login, password: password, deviceName: _deviceName());
        status = AuthStatus.signedIn;
      });

  Future<bool> register({
    required String name,
    required String username,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) =>
      _run(() async {
        user = await _repository.register(
          name: name,
          username: username,
          email: email,
          password: password,
          passwordConfirmation: passwordConfirmation,
          deviceName: _deviceName(),
        );
        status = AuthStatus.signedIn;
      });

  Future<void> logout() async {
    isBusy = true;
    notifyListeners();
    try {
      await _repository.logout();
    } catch (_) {
      // Token revocation on the server is best-effort from the client's point of view — the
      // user is signed out locally regardless, so a flaky connection on logout never traps them.
    }
    user = null;
    status = AuthStatus.signedOut;
    isBusy = false;
    notifyListeners();
  }

  Future<bool> _run(Future<void> Function() action) async {
    isBusy = true;
    error = null;
    notifyListeners();
    try {
      await action();

      return true;
    } on ApiException catch (e) {
      error = e.message;

      return false;
    } finally {
      isBusy = false;
      notifyListeners();
    }
  }

  String _deviceName() => kIsWeb ? 'web-browser' : 'mobile-device';
}
