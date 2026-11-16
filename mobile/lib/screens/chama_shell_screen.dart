import 'package:material_ui/material_ui.dart';

import '../core/models/chama.dart';
import 'contributions_screen.dart';
import 'withdrawals_screen.dart';

/// Hosts the two money-movement screens for one chama — contributions and withdrawals are the
/// features a member reaches for most often on a phone (the other web pages — accounts,
/// accounting, expenses, settings — are officer/admin-heavy setup screens better suited to a
/// larger screen for now; see docs/DEVELOPMENT_PROGRESS.md's Phase 5 mobile entry).
class ChamaShellScreen extends StatefulWidget {
  const ChamaShellScreen({super.key, required this.chama});

  final Chama chama;

  @override
  State<ChamaShellScreen> createState() => _ChamaShellScreenState();
}

class _ChamaShellScreenState extends State<ChamaShellScreen> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final screens = [
      ContributionsScreen(chama: widget.chama),
      WithdrawalsScreen(chama: widget.chama),
    ];

    return Scaffold(
      appBar: AppBar(title: Text(widget.chama.name)),
      body: IndexedStack(index: _index, children: screens),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.savings_outlined), label: 'Contributions'),
          NavigationDestination(icon: Icon(Icons.arrow_upward), label: 'Withdrawals'),
        ],
      ),
    );
  }
}
