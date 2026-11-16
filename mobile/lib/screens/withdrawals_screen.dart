import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../core/constants.dart';
import '../core/models/chama.dart';
import '../core/models/withdrawal.dart';
import '../providers/auth_provider.dart';
import '../providers/withdrawal_provider.dart';
import '../widgets/error_banner.dart';

class WithdrawalsScreen extends StatelessWidget {
  const WithdrawalsScreen({super.key, required this.chama});

  final Chama chama;

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => WithdrawalProvider(organizationId: chama.id)..load(),
      child: const _WithdrawalsBody(),
    );
  }
}

class _WithdrawalsBody extends StatefulWidget {
  const _WithdrawalsBody();

  @override
  State<_WithdrawalsBody> createState() => _WithdrawalsBodyState();
}

class _WithdrawalsBodyState extends State<_WithdrawalsBody> {
  final _amountController = TextEditingController();
  final _reasonController = TextEditingController();
  int? _selectedAccountId;

  @override
  void dispose() {
    _amountController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<WithdrawalProvider>();
    final overview = provider.overview;
    final currentUserId = context.watch<AuthProvider>().user?.id;

    if (provider.isLoading && overview == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_selectedAccountId == null && provider.accounts.isNotEmpty) {
      _selectedAccountId = provider.accounts.first.id;
    }

    return RefreshIndicator(
      onRefresh: () => context.read<WithdrawalProvider>().load(),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ErrorBanner(message: provider.error),
          if (overview != null) ...[
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Your savings balance', style: TextStyle(color: Colors.black54)),
                    Text(
                      overview.myBalanceFormatted,
                      style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppColors.navy),
                    ),
                  ],
                ),
              ),
            ),
            _requestCard(provider),
            if (overview.canManageFinances && overview.pendingApprovals.isNotEmpty)
              _queueCard('Pending approvals', overview.pendingApprovals, provider, currentUserId, showApprove: true),
            if (overview.canManageFinances && overview.readyToPay.isNotEmpty)
              _queueCard('Approved — ready to pay', overview.readyToPay, provider, currentUserId, showPay: true),
            _myRequestsCard(overview.myRequests, provider),
          ],
        ],
      ),
    );
  }

  Widget _requestCard(WithdrawalProvider provider) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Request a withdrawal', style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            if (provider.accounts.isNotEmpty)
              DropdownButtonFormField<int>(
                initialValue: _selectedAccountId,
                decoration: const InputDecoration(labelText: 'Pay out from'),
                items: [
                  for (final account in provider.accounts)
                    DropdownMenuItem(value: account.id, child: Text(account.name)),
                ],
                onChanged: (v) => setState(() => _selectedAccountId = v),
              ),
            const SizedBox(height: 8),
            TextField(
              controller: _amountController,
              decoration: const InputDecoration(labelText: 'Amount'),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _reasonController,
              decoration: const InputDecoration(labelText: 'Reason (optional)'),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _selectedAccountId == null
                    ? null
                    : () async {
                        final ok = await provider.request(
                          financialAccountId: _selectedAccountId!,
                          amount: _amountController.text.trim(),
                          reason: _reasonController.text.trim(),
                        );
                        if (ok) {
                          _amountController.clear();
                          _reasonController.clear();
                        }
                      },
                child: const Text('Request withdrawal'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _queueCard(
    String title,
    List<WithdrawalRequestModel> items,
    WithdrawalProvider provider,
    int? currentUserId, {
    bool showApprove = false,
    bool showPay = false,
  }) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
            for (final item in items)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${item.userName ?? 'Member'} — ${item.amountFormatted}'),
                subtitle: Text('${item.approvalsCount}/${item.requiredApprovals} approvals'),
                trailing: showApprove
                    ? (item.userId != null && item.userId == currentUserId
                        ? const Text("Can't approve your own request", style: TextStyle(color: Colors.black45, fontSize: 12))
                        : Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              TextButton(onPressed: () => provider.approve(item.id), child: const Text('Approve')),
                              TextButton(
                                onPressed: () => provider.reject(item.id),
                                child: const Text('Reject', style: TextStyle(color: Colors.red)),
                              ),
                            ],
                          ))
                    : showPay
                        ? TextButton(onPressed: () => provider.pay(item.id), child: const Text('Pay'))
                        : null,
              ),
          ],
        ),
      ),
    );
  }

  Widget _myRequestsCard(List<WithdrawalRequestModel> requests, WithdrawalProvider provider) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Your requests', style: TextStyle(fontWeight: FontWeight.bold)),
            if (requests.isEmpty) const Padding(padding: EdgeInsets.only(top: 8), child: Text('No withdrawal requests yet.')),
            for (final request in requests)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(request.amountFormatted),
                subtitle: Text('${request.status} · ${request.approvalsCount}/${request.requiredApprovals} approvals'),
                trailing: request.status == 'pending' || request.status == 'approved'
                    ? TextButton(onPressed: () => provider.cancel(request.id), child: const Text('Cancel'))
                    : null,
              ),
          ],
        ),
      ),
    );
  }
}
