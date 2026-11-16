import '../core/api_client.dart';
import '../core/models/chama.dart';
import '../core/models/financial_account.dart';

class ChamaRepository {
  ChamaRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<List<Chama>> myChamas() async {
    final json = await _client.get('/chamas');

    return (json['data'] as List<dynamic>).map((e) => Chama.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<Chama> show(int organizationId) async {
    final json = await _client.get('/chamas/$organizationId');

    return Chama.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<List<ChamaMember>> members(int organizationId) async {
    final json = await _client.get('/chamas/$organizationId/members');

    return (json['data'] as List<dynamic>).map((e) => ChamaMember.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<FinancialAccount>> accounts(int organizationId) async {
    final json = await _client.get('/chamas/$organizationId/accounts');

    return (json['data'] as List<dynamic>).map((e) => FinancialAccount.fromJson(e as Map<String, dynamic>)).toList();
  }
}
