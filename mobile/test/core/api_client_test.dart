import 'dart:convert';

import 'package:chama_wise/core/api_client.dart';
import 'package:chama_wise/core/api_exception.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  group('ApiClient', () {
    test('sends the bearer token and Accept header on every request', () async {
      http.Request? captured;
      final client = ApiClient(
        tokenProvider: () async => 'abc123',
        client: MockClient((request) async {
          captured = request;

          return http.Response(jsonEncode({'data': 'ok'}), 200);
        }),
      );

      await client.get('/chamas');

      expect(captured!.headers['Authorization'], 'Bearer abc123');
      expect(captured!.headers['Accept'], 'application/json');
    });

    test('omits the Authorization header when there is no stored token', () async {
      http.Request? captured;
      final client = ApiClient(
        tokenProvider: () async => null,
        client: MockClient((request) async {
          captured = request;

          return http.Response(jsonEncode({'data': 'ok'}), 200);
        }),
      );

      await client.get('/chamas');

      expect(captured!.headers.containsKey('Authorization'), isFalse);
    });

    test('returns the decoded body on a 2xx response', () async {
      final client = ApiClient(
        tokenProvider: () async => null,
        client: MockClient((_) async => http.Response(jsonEncode({'data': [1, 2, 3]}), 200)),
      );

      final result = await client.get('/x');

      expect(result['data'], [1, 2, 3]);
    });

    test('throws ApiException with the backend message on a non-2xx response', () async {
      final client = ApiClient(
        tokenProvider: () async => null,
        client: MockClient((_) async => http.Response(jsonEncode({'message': 'Nope.'}), 422)),
      );

      await expectLater(
        client.post('/x'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', 'Nope.').having((e) => e.statusCode, 'statusCode', 422)),
      );
    });

    test('parses Laravel validation errors into fieldErrors', () async {
      final client = ApiClient(
        tokenProvider: () async => null,
        client: MockClient((_) async => http.Response(
              jsonEncode({
                'message': 'The amount field is required.',
                'errors': {
                  'amount': ['The amount field is required.'],
                },
              }),
              422,
            )),
      );

      try {
        await client.post('/x');
        fail('Expected an ApiException');
      } on ApiException catch (e) {
        expect(e.fieldErrors!['amount'], ['The amount field is required.']);
      }
    });

    test('a transport failure becomes a friendly ApiException, not an uncaught error', () async {
      final client = ApiClient(
        tokenProvider: () async => null,
        client: MockClient((_) async => throw Exception('socket closed')),
      );

      await expectLater(
        client.get('/x'),
        throwsA(isA<ApiException>().having((e) => e.message, 'message', contains('reach the server'))),
      );
    });

    test('a 401 response is recognized as unauthorized', () async {
      final client = ApiClient(
        tokenProvider: () async => 'expired',
        client: MockClient((_) async => http.Response(jsonEncode({'message': 'Unauthenticated.'}), 401)),
      );

      try {
        await client.get('/x');
        fail('Expected an ApiException');
      } on ApiException catch (e) {
        expect(e.isUnauthorized, isTrue);
      }
    });
  });
}
