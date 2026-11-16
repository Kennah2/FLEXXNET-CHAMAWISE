import 'dart:convert';

import 'package:chama_wise/core/api_client.dart';
import 'package:chama_wise/providers/auth_provider.dart';
import 'package:chama_wise/repositories/auth_repository.dart';
import 'package:chama_wise/screens/login_screen.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import '../test_helpers.dart';

Widget _wrap(AuthProvider provider) => MaterialApp(
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      supportedLocales: const [Locale('en')],
      home: ChangeNotifierProvider<AuthProvider>.value(value: provider, child: const LoginScreen()),
    );

void main() {
  setUp(mockSecureStorage);

  testWidgets('shows validation errors when submitted empty', (tester) async {
    final provider = AuthProvider(repository: AuthRepository(client: ApiClient(tokenProvider: () async => null)));
    await tester.pumpWidget(_wrap(provider));

    await tester.tap(find.text('Log in'));
    await tester.pump();

    expect(find.text('Required'), findsNWidgets(2));
  });

  testWidgets('a successful login clears the error and reaches signedIn', (tester) async {
    final repository = AuthRepository(
      client: ApiClient(
        tokenProvider: () async => null,
        client: MockClient((_) async => http.Response(
              jsonEncode({
                'token': '1|fake',
                'user': {'id': 1, 'name': 'Ken', 'username': 'ken', 'email': 'ken@example.test'},
              }),
              200,
            )),
      ),
    );
    final provider = AuthProvider(repository: repository);
    await tester.pumpWidget(_wrap(provider));

    await tester.enterText(find.widgetWithText(TextFormField, 'Username or email'), 'ken');
    await tester.enterText(find.widgetWithText(TextFormField, 'Password'), 'password');
    await tester.tap(find.text('Log in'));
    await tester.pumpAndSettle();

    expect(provider.status, AuthStatus.signedIn);
    expect(provider.error, isNull);
  });

  testWidgets('a failed login shows the backend error message', (tester) async {
    final repository = AuthRepository(
      client: ApiClient(
        tokenProvider: () async => null,
        client: MockClient((_) async => http.Response(jsonEncode({'message': 'These credentials do not match our records.'}), 422)),
      ),
    );
    final provider = AuthProvider(repository: repository);
    await tester.pumpWidget(_wrap(provider));

    await tester.enterText(find.widgetWithText(TextFormField, 'Username or email'), 'ken');
    await tester.enterText(find.widgetWithText(TextFormField, 'Password'), 'wrong');
    await tester.tap(find.text('Log in'));
    await tester.pumpAndSettle();

    expect(find.text('These credentials do not match our records.'), findsOneWidget);
  });
}
