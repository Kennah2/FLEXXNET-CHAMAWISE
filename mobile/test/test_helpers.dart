import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

/// Stubs the `flutter_secure_storage` plugin's platform channel with a simple in-memory map, so
/// tests that exercise `TokenStorage` (directly or via AuthRepository/AuthProvider) don't need a
/// real platform and don't throw MissingPluginException. Call once per test file, inside
/// `setUpAll` or at the top of `main()` — `TestWidgetsFlutterBinding.ensureInitialized()` must
/// run first, which this also does.
void mockSecureStorage() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final store = <String, String>{};
  const channel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, (call) async {
    switch (call.method) {
      case 'write':
        store[call.arguments['key'] as String] = call.arguments['value'] as String;

        return null;
      case 'read':
        return store[call.arguments['key'] as String];
      case 'delete':
        store.remove(call.arguments['key'] as String);

        return null;
      case 'deleteAll':
        store.clear();

        return null;
      case 'containsKey':
        return store.containsKey(call.arguments['key'] as String);
      default:
        return null;
    }
  });
}
