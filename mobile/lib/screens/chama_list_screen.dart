import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../core/models/chama.dart';
import '../providers/auth_provider.dart';
import '../providers/chama_provider.dart';
import '../widgets/error_banner.dart';
import 'chama_shell_screen.dart';

class ChamaListScreen extends StatelessWidget {
  const ChamaListScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => ChamaProvider()..load(),
      child: const _ChamaListBody(),
    );
  }
}

class _ChamaListBody extends StatelessWidget {
  const _ChamaListBody();

  @override
  Widget build(BuildContext context) {
    final chamaProvider = context.watch<ChamaProvider>();
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Text('Hi, ${auth.user?.name ?? ''}'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Log out',
            onPressed: () => context.read<AuthProvider>().logout(),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => context.read<ChamaProvider>().load(),
        child: chamaProvider.isLoading && chamaProvider.chamas.isEmpty
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  ErrorBanner(message: chamaProvider.error),
                  if (chamaProvider.chamas.isEmpty && !chamaProvider.isLoading)
                    const Padding(
                      padding: EdgeInsets.only(top: 48),
                      child: Center(child: Text("You're not a member of any chama yet.")),
                    ),
                  for (final chama in chamaProvider.chamas) _ChamaCard(chama: chama),
                ],
              ),
      ),
    );
  }
}

class _ChamaCard extends StatelessWidget {
  const _ChamaCard({required this.chama});

  final Chama chama;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        title: Text(chama.name, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Text(chama.myRoles.isEmpty ? chama.groupType : chama.myRoles.join(', ')),
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => ChamaShellScreen(chama: chama)),
        ),
      ),
    );
  }
}
