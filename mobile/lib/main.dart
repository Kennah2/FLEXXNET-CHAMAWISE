import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import 'core/constants.dart';
import 'providers/auth_provider.dart';
import 'screens/chama_list_screen.dart';
import 'screens/login_screen.dart';

void main() {
  runApp(const ChamaWiseApp());
}

class ChamaWiseApp extends StatelessWidget {
  const ChamaWiseApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => AuthProvider()..tryAutoLogin(),
      child: MaterialApp(
        title: 'CHAMA WISE',
        debugShowCheckedModeBanner: false,
        theme: _buildTheme(),
        // material_ui (the package Flutter's own `flutter create` now scaffolds — it decoupled
        // Material widgets out of package:flutter/material.dart) no longer bundles a default
        // MaterialLocalizations fallback the way the old flutter/material.dart did; without
        // this, every TextField/TextFormField throws "No MaterialLocalizations found" the
        // instant it builds.
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        supportedLocales: const [Locale('en')],
        home: const _RootScreen(),
      ),
    );
  }

  ThemeData _buildTheme() {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: AppColors.navy,
      primary: AppColors.navy,
      secondary: AppColors.pink,
      tertiary: AppColors.teal,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: AppColors.grey,
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.navy,
        foregroundColor: AppColors.white,
        centerTitle: false,
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.pink,
          foregroundColor: AppColors.white,
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 20),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
      ),
      cardTheme: CardThemeData(
        color: AppColors.white,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.symmetric(vertical: 6),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
      ),
    );
  }
}

/// Decides what the user sees based on auth status, instead of a route table — there are only
/// two real top-level destinations (signed out vs signed in), so a route table would be
/// premature structure for what this app actually needs right now.
class _RootScreen extends StatelessWidget {
  const _RootScreen();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return switch (auth.status) {
      AuthStatus.unknown => const Scaffold(body: Center(child: CircularProgressIndicator())),
      AuthStatus.signedOut => const LoginScreen(),
      AuthStatus.signedIn => const ChamaListScreen(),
    };
  }
}
