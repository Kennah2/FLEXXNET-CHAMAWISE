import 'package:material_ui/material_ui.dart';

/// Brand palette and the API base URL — kept in one place so neither drifts.
/// See docs/DEVELOPMENT_PROGRESS.md and the backend's docs/API.md for where these come from.
class AppColors {
  AppColors._();

  static const navy = Color(0xFF101B35);
  static const pink = Color(0xFFE44E83);
  static const teal = Color(0xFF16A085);
  static const white = Color(0xFFFFFFFF);
  static const grey = Color(0xFFF5F7FA);
}

/// The backend's versioned API root. Overridable at build/run time with
/// `--dart-define=API_BASE_URL=https://your-host/api/v1` — never hardcode a different value
/// elsewhere in the app.
const String apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://localhost:8788/api/v1',
);
