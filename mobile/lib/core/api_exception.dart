/// A non-2xx response from the API, or a transport failure reaching it. `message` is always
/// something safe to show a user directly — the backend's own `message`/validation-error text,
/// or a plain "couldn't reach the server" for a network failure.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.fieldErrors});

  final String message;
  final int? statusCode;

  /// Laravel validation errors (`{"field": ["message", ...]}`), when present.
  final Map<String, List<String>>? fieldErrors;

  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => message;
}
