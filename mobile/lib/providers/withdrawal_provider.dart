import 'package:flutter/foundation.dart';

import '../core/api_exception.dart';
import '../core/models/financial_account.dart';
import '../core/models/withdrawal.dart';
import '../repositories/chama_repository.dart';
import '../repositories/withdrawal_repository.dart';

class WithdrawalProvider extends ChangeNotifier {
  WithdrawalProvider({
    required this.organizationId,
    WithdrawalRepository? repository,
    ChamaRepository? chamaRepository,
  })  : _repository = repository ?? WithdrawalRepository(),
        _chamaRepository = chamaRepository ?? ChamaRepository();

  final int organizationId;
  final WithdrawalRepository _repository;
  final ChamaRepository _chamaRepository;

  WithdrawalsOverview? overview;
  List<FinancialAccount> accounts = [];
  bool isLoading = false;
  String? error;

  Future<void> load() async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      overview = await _repository.overview(organizationId);
      accounts = await _chamaRepository.accounts(organizationId);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> request({required int financialAccountId, required String amount, String? reason}) => _runAndReload(
        () => _repository.request(organizationId, financialAccountId: financialAccountId, amount: amount, reason: reason),
      );

  Future<bool> approve(int id) => _runAndReload(() => _repository.approve(organizationId, id));

  Future<bool> reject(int id) => _runAndReload(() => _repository.reject(organizationId, id));

  Future<bool> pay(int id) => _runAndReload(() => _repository.pay(organizationId, id));

  Future<bool> cancel(int id) => _runAndReload(() => _repository.cancel(organizationId, id));

  Future<bool> _runAndReload(Future<void> Function() action) async {
    try {
      await action();
      await load();

      return true;
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();

      return false;
    }
  }
}
