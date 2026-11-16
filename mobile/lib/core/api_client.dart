import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_exception.dart';
import 'constants.dart';
import 'token_storage.dart';

/// Thin HTTP wrapper around the backend's `/api/v1` surface (see backend/docs/API.md). Holds no
/// business logic of its own — every decision about what a request means or what to do with its
/// result lives in the backend and is only ever rendered here, never re-derived (Part 15: the
/// Flutter client never re-implements financial/business logic).
class ApiClient {
  ApiClient({http.Client? client, Future<String?> Function()? tokenProvider})
      : _client = client ?? http.Client(),
        _tokenProvider = tokenProvider ?? TokenStorage.read;

  final http.Client _client;
  final Future<String?> Function() _tokenProvider;

  Future<Map<String, dynamic>> get(String path, {Map<String, String>? query}) async {
    final uri = Uri.parse('$apiBaseUrl$path').replace(queryParameters: query);
    final response = await _send(() async => _client.get(uri, headers: await _headers()));

    return _decode(response);
  }

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) async {
    final uri = Uri.parse('$apiBaseUrl$path');
    final response = await _send(
      () async => _client.post(uri, headers: await _headers(), body: jsonEncode(body ?? {})),
    );

    return _decode(response);
  }

  Future<http.Response> _send(Future<http.Response> Function() call) async {
    try {
      return await call();
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException('Could not reach the server — check your connection and try again.');
    }
  }

  Future<Map<String, String>> _headers() async {
    final token = await _tokenProvider();

    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  Map<String, dynamic> _decode(http.Response response) {
    final body = response.body.isEmpty ? <String, dynamic>{} : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return body;
    }

    final message = (body['message'] as String?) ?? 'Something went wrong (${response.statusCode}).';
    Map<String, List<String>>? fieldErrors;
    final errors = body['errors'];
    if (errors is Map) {
      fieldErrors = errors.map((key, value) => MapEntry(key as String, (value as List).cast<String>()));
    }

    throw ApiException(message, statusCode: response.statusCode, fieldErrors: fieldErrors);
  }
}
