import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../core/constants.dart';
import '../core/models/chama.dart';
import '../core/models/contribution.dart';
import '../providers/contribution_provider.dart';
import '../widgets/error_banner.dart';

class ContributionsScreen extends StatelessWidget {
  const ContributionsScreen({super.key, required this.chama});

  final Chama chama;

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => ContributionProvider(organizationId: chama.id)..load(),
      child: const _ContributionsBody(),
    );
  }
}

class _ContributionsBody extends StatefulWidget {
  const _ContributionsBody();

  @override
  State<_ContributionsBody> createState() => _ContributionsBodyState();
}

class _ContributionsBodyState extends State<_ContributionsBody> {
  final _phoneController = TextEditingController();
  final _mpesaAmountController = TextEditingController();
  final _recordAmountController = TextEditingController();
  final _referenceController = TextEditingController();
  String _paymentMethod = 'cash';

  @override
  void dispose() {
    _phoneController.dispose();
    _mpesaAmountController.dispose();
    _recordAmountController.dispose();
    _referenceController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ContributionProvider>();
    final overview = provider.overview;

    if (provider.isLoading && overview == null) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: () => context.read<ContributionProvider>().load(),
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
                    const Text('Your outstanding balance', style: TextStyle(color: Colors.black54)),
                    Text(
                      overview.outstandingFormatted,
                      style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppColors.navy),
                    ),
                  ],
                ),
              ),
            ),
            _mpesaCard(provider),
            _recordCard(provider),
            if (overview.canManageFinances && overview.pendingVerifications.isNotEmpty)
              _pendingVerificationsCard(provider, overview.pendingVerifications),
            _obligationsCard(overview.obligations),
            _historyCard(overview.payments),
          ],
        ],
      ),
    );
  }

  Widget _mpesaCard(ContributionProvider provider) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Pay with M-Pesa', style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            TextField(
              controller: _phoneController,
              decoration: const InputDecoration(labelText: 'Phone number', hintText: '07XXXXXXXX'),
              keyboardType: TextInputType.phone,
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _mpesaAmountController,
              decoration: InputDecoration(labelText: 'Amount (${provider.overview?.outstandingFormatted.split(' ').first ?? 'KES'}, whole shillings)'),
              keyboardType: TextInputType.number,
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: provider.mpesaBusy
                    ? null
                    : () => provider.payWithMpesa(phone: _phoneController.text.trim(), amount: _mpesaAmountController.text.trim()),
                child: provider.mpesaBusy ? const Text('Waiting…') : const Text('Pay with M-Pesa'),
              ),
            ),
            StatusBanner(message: provider.mpesaMessage, isError: provider.mpesaIsError),
          ],
        ),
      ),
    );
  }

  Widget _recordCard(ContributionProvider provider) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Record a contribution', style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            TextField(
              controller: _recordAmountController,
              decoration: const InputDecoration(labelText: 'Amount'),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
            ),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              initialValue: _paymentMethod,
              decoration: const InputDecoration(labelText: 'Method'),
              items: const [
                DropdownMenuItem(value: 'cash', child: Text('Cash')),
                DropdownMenuItem(value: 'bank', child: Text('Bank')),
                DropdownMenuItem(value: 'manual_mpesa', child: Text('Manual M-Pesa')),
                DropdownMenuItem(value: 'other', child: Text('Other')),
              ],
              onChanged: (v) => setState(() => _paymentMethod = v ?? 'cash'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _referenceController,
              decoration: const InputDecoration(labelText: 'Reference (optional)'),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () async {
                  final ok = await provider.record(
                    amount: _recordAmountController.text.trim(),
                    paymentMethod: _paymentMethod,
                    reference: _referenceController.text.trim(),
                  );
                  if (ok) {
                    _recordAmountController.clear();
                    _referenceController.clear();
                  }
                },
                child: const Text('Record contribution'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _pendingVerificationsCard(ContributionProvider provider, List<Contribution> pending) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Pending verifications', style: TextStyle(fontWeight: FontWeight.bold)),
            for (final contribution in pending)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${contribution.userName ?? 'Member'} — ${contribution.amountFormatted}'),
                subtitle: Text(contribution.paymentMethodLabel),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextButton(
                      onPressed: () => _pickAccountAndVerify(provider, contribution.id),
                      child: const Text('Verify'),
                    ),
                    TextButton(
                      onPressed: () => provider.reject(contribution.id),
                      child: const Text('Reject', style: TextStyle(color: Colors.red)),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickAccountAndVerify(ContributionProvider provider, int contributionId) async {
    final accountId = await showDialog<int>(
      context: context,
      builder: (ctx) => SimpleDialog(
        title: const Text('Verify into which account?'),
        children: [
          for (final account in provider.accounts)
            SimpleDialogOption(
              onPressed: () => Navigator.of(ctx).pop(account.id),
              child: Text('${account.name} (${account.balanceFormatted})'),
            ),
        ],
      ),
    );
    if (accountId != null) {
      await provider.verify(contributionId, accountId);
    }
  }

  Widget _obligationsCard(List<ContributionObligation> obligations) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Your obligations', style: TextStyle(fontWeight: FontWeight.bold)),
            if (obligations.isEmpty) const Padding(padding: EdgeInsets.only(top: 8), child: Text('Nothing due yet.')),
            for (final obligation in obligations)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(obligation.scheduleName ?? 'Contribution'),
                subtitle: Text(obligation.status + (obligation.hasPenalty ? ' · penalty applied' : '')),
                trailing: Text(obligation.outstandingFormatted, style: const TextStyle(fontWeight: FontWeight.w600)),
              ),
          ],
        ),
      ),
    );
  }

  Widget _historyCard(List<Contribution> payments) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Your payment history', style: TextStyle(fontWeight: FontWeight.bold)),
            if (payments.isEmpty) const Padding(padding: EdgeInsets.only(top: 8), child: Text('No payments recorded yet.')),
            for (final payment in payments)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(payment.amountFormatted),
                subtitle: Text(payment.paymentMethodLabel),
                trailing: Text(payment.status),
              ),
          ],
        ),
      ),
    );
  }
}
