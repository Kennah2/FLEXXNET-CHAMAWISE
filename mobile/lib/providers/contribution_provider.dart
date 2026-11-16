import 'dart:async';

import 'package:flutter/foundation.dart';

import '../core/api_exception.dart';
import '../core/models/contribution.dart';
import '../core/models/financial_account.dart';
import '../repositories/chama_repository.dart';
import '../repositories/contribution_repository.dart';

class ContributionProvider extends ChangeNotifier {
  ContributionProvider({
    required this.organizationId,
    ContributionRepository? repository,
    ChamaRepository? chamaRepository,
  })  : _repository = repository ?? ContributionRepository(),
        _chamaRepository = chamaRepository ?? ChamaRepository();

  final int organizationId;
  final ContributionRepository _repository;
  final ChamaRepository _chamaRepository;

  ContributionsOverview? overview;
  List<FinancialAccount> accounts = [];
  bool isLoading = false;
  String? error;

  bool mpesaBusy = false;
  String? mpesaMessage;
  bool mpesaIsError = false;

  Future<void> load() async {
    isLoading = true;
    error = null;
    notifyListeners();
    try {
      overview = await _repository.overview(organizationId);
      if (overview!.canManageFinances) {
        accounts = await _chamaRepository.accounts(organizationId);
      }
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> record({required String amount, required String paymentMethod, String? reference}) async {
    try {
      await _repository.record(
        organizationId,
        amount: amount,
        paymentMethod: paymentMethod,
        transactionReference: reference,
        paidAt: DateTime.now().toIso8601String(),
      );
      await load();

      return true;
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();

      return false;
    }
  }

  Future<bool> verify(int contributionId, int financialAccountId) => _runAndReload(
        () => _repository.verify(organizationId, contributionId, financialAccountId),
      );

  Future<bool> reject(int contributionId) => _runAndReload(() => _repository.reject(organizationId, contributionId));

  Future<void> payWithMpesa({required String phone, required String amount}) async {
    mpesaBusy = true;
    mpesaMessage = null;
    mpesaIsError = false;
    notifyListeners();

    try {
      final initiated = await _repository.initiateMpesa(organizationId, phone: phone, amount: amount);
      mpesaMessage = initiated.message;

      if (initiated.status != 'pending') {
        mpesaIsError = true;
        mpesaBusy = false;
        notifyListeners();

        return;
      }

      await _pollMpesa(initiated.transactionId, attempt: 0);
    } on ApiException catch (e) {
      mpesaMessage = e.message;
      mpesaIsError = true;
      mpesaBusy = false;
      notifyListeners();
    }
  }

  Future<void> _pollMpesa(int transactionId, {required int attempt}) async {
    if (attempt > 30) {
      mpesaMessage = 'Still waiting — check your phone, or try again.';
      mpesaBusy = false;
      notifyListeners();

      return;
    }

    await Future<void>.delayed(const Duration(seconds: 3));
    final status = await _repository.pollMpesa(organizationId, transactionId);

    if (status.isTerminal) {
      mpesaBusy = false;
      if (status.isCompleted) {
        mpesaMessage = 'Payment received!';
        mpesaIsError = false;
        await load();
      } else {
        mpesaMessage = status.resultDesc ?? 'Payment was not completed.';
        mpesaIsError = true;
      }
      notifyListeners();

      return;
    }

    await _pollMpesa(transactionId, attempt: attempt + 1);
  }

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
