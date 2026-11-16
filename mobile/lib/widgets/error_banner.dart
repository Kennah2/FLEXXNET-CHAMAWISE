import 'package:material_ui/material_ui.dart';

class ErrorBanner extends StatelessWidget {
  const ErrorBanner({super.key, required this.message});

  final String? message;

  @override
  Widget build(BuildContext context) {
    if (message == null) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.red.shade50,
        border: Border(left: BorderSide(color: Colors.red.shade400, width: 4)),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(message!, style: TextStyle(color: Colors.red.shade700)),
    );
  }
}

class StatusBanner extends StatelessWidget {
  const StatusBanner({super.key, required this.message, this.isError = false});

  final String? message;
  final bool isError;

  @override
  Widget build(BuildContext context) {
    if (message == null) return const SizedBox.shrink();
    final color = isError ? Colors.red : const Color(0xFF16A085);

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        border: Border(left: BorderSide(color: color, width: 4)),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(message!, style: TextStyle(color: color.withValues(alpha: 0.9))),
    );
  }
}
