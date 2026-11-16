import '../core/api_client.dart';
import '../core/models/contribution.dart';
import '../core/models/payment_transaction.dart';

class ContributionRepository {
  ContributionRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<ContributionsOverview> overview(int organizationId) async {
    final json = await _client.get('/chamas/$organizationId/contributions');

    return ContributionsOverview.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<void> record(
    int organizationId, {
    required String amount,
    required String paymentMethod,
    String? transactionReference,
    required String paidAt,
  }) async {
    await _client.post('/chamas/$organizationId/contributions', {
      'amount': amount,
      'payment_method': paymentMethod,
      if (transactionReference != null && transactionReference.isNotEmpty) 'transaction_reference': transactionReference,
      'paid_at': paidAt,
    });
  }

  Future<void> verify(int organizationId, int contributionId, int financialAccountId) async {
    await _client.post(
      '/chamas/$organizationId/contributions/$contributionId/verify',
      {'financial_account_id': financialAccountId},
    );
  }

  Future<void> reject(int organizationId, int contributionId) async {
    await _client.post('/chamas/$organizationId/contributions/$contributionId/reject');
  }

  Future<StkPushStatus> initiateMpesa(int organizationId, {required String phone, required String amount}) async {
    final json = await _client.post('/chamas/$organizationId/contributions/mpesa', {
      'phone': phone,
      'amount': amount,
    });

    return StkPushStatus.fromInitiateJson(json);
  }

  Future<StkPushStatus> pollMpesa(int organizationId, int transactionId) async {
    final json = await _client.get('/chamas/$organizationId/contributions/mpesa/$transactionId');

    return StkPushStatus.fromPollJson(json);
  }
}
