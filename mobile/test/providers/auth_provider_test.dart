import 'dart:convert';

import 'package:chama_wise/core/api_client.dart';
import 'package:chama_wise/providers/auth_provider.dart';
import 'package:chama_wise/repositories/auth_repository.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import '../test_helpers.dart';

void main() {
  setUp(mockSecureStorage);

  group('AuthProvider', () {
    test('a successful login sets the user and signedIn status', () async {
      final repository = AuthRepository(
        client: ApiClient(
          tokenProvider: () async => null,
          client: MockClient((request) async {
            expect(request.url.path, contains('/auth/login'));

            return http.Response(
              jsonEncode({
                'token': '1|fake',
                'user': {'id': 1, 'name': 'Ken', 'username': 'ken', 'email': 'ken@example.test'},
              }),
              200,
            );
          }),
        ),
      );
      final provider = AuthProvider(repository: repository);

      final ok = await provider.login('ken', 'password');

      expect(ok, isTrue);
      expect(provider.status, AuthStatus.signedIn);
      expect(provider.user!.name, 'Ken');
      expect(provider.error, isNull);
    });

    test('a failed login surfaces the backend message and stays signed out', () async {
      final repository = AuthRepository(
        client: ApiClient(
          tokenProvider: () async => null,
          client: MockClient((_) async => http.Response(jsonEncode({'message': 'These credentials do not match our records.'}), 422)),
        ),
      );
      final provider = AuthProvider(repository: repository);

      final ok = await provider.login('ken', 'wrong');

      expect(ok, isFalse);
      expect(provider.status, isNot(AuthStatus.signedIn));
      expect(provider.error, 'These credentials do not match our records.');
    });

    test('tryAutoLogin signs out silently when there is no stored token', () async {
      final repository = AuthRepository(
        client: ApiClient(tokenProvider: () async => null, client: MockClient((_) async => http.Response('{}', 200))),
      );
      final provider = AuthProvider(repository: repository);

      await provider.tryAutoLogin();

      expect(provider.status, AuthStatus.signedOut);
    });
  });
}
