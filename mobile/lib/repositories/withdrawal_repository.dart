import '../core/api_client.dart';
import '../core/models/withdrawal.dart';

class WithdrawalRepository {
  WithdrawalRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<WithdrawalsOverview> overview(int organizationId) async {
    final json = await _client.get('/chamas/$organizationId/withdrawals');

    return WithdrawalsOverview.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<void> request(int organizationId, {required int financialAccountId, required String amount, String? reason}) async {
    await _client.post('/chamas/$organizationId/withdrawals', {
      'financial_account_id': financialAccountId,
      'amount': amount,
      if (reason != null && reason.isNotEmpty) 'reason': reason,
    });
  }

  Future<void> approve(int organizationId, int withdrawalId) =>
      _client.post('/chamas/$organizationId/withdrawals/$withdrawalId/approve');

  Future<void> reject(int organizationId, int withdrawalId, {String? reason}) =>
      _client.post('/chamas/$organizationId/withdrawals/$withdrawalId/reject', {'reason': ?reason});

  Future<void> pay(int organizationId, int withdrawalId) =>
      _client.post('/chamas/$organizationId/withdrawals/$withdrawalId/pay');

  Future<void> cancel(int organizationId, int withdrawalId) =>
      _client.post('/chamas/$organizationId/withdrawals/$withdrawalId/cancel');
}
