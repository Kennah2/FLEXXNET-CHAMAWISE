class WithdrawalRequestModel {
  WithdrawalRequestModel({
    required this.id,
    this.userId,
    this.userName,
    this.financialAccountName,
    required this.amountFormatted,
    this.reason,
    required this.requiredApprovals,
    required this.approvalsCount,
    required this.status,
  });

  factory WithdrawalRequestModel.fromJson(Map<String, dynamic> json) => WithdrawalRequestModel(
        id: json['id'] as int,
        userId: json['user']?['id'] as int?,
        userName: json['user']?['name'] as String?,
        financialAccountName: json['financial_account_name'] as String?,
        amountFormatted: json['amount_formatted'] as String,
        reason: json['reason'] as String?,
        requiredApprovals: json['required_approvals'] as int,
        approvalsCount: json['approvals_count'] as int,
        status: json['status'] as String,
      );

  final int id;
  final int? userId;
  final String? userName;
  final String? financialAccountName;
  final String amountFormatted;
  final String? reason;
  final int requiredApprovals;
  final int approvalsCount;
  final String status;
}

/// The shape of `GET /chamas/{organization}/withdrawals` (backend docs/API.md).
class WithdrawalsOverview {
  WithdrawalsOverview({
    required this.canManageFinances,
    required this.myBalanceFormatted,
    required this.myRequests,
    required this.pendingApprovals,
    required this.readyToPay,
  });

  factory WithdrawalsOverview.fromJson(Map<String, dynamic> json) => WithdrawalsOverview(
        canManageFinances: json['can_manage_finances'] as bool,
        myBalanceFormatted: json['my_balance_formatted'] as String,
        myRequests: (json['my_requests'] as List<dynamic>)
            .map((e) => WithdrawalRequestModel.fromJson(e as Map<String, dynamic>))
            .toList(),
        pendingApprovals: (json['pending_approvals'] as List<dynamic>)
            .map((e) => WithdrawalRequestModel.fromJson(e as Map<String, dynamic>))
            .toList(),
        readyToPay: (json['ready_to_pay'] as List<dynamic>)
            .map((e) => WithdrawalRequestModel.fromJson(e as Map<String, dynamic>))
            .toList(),
      );

  final bool canManageFinances;
  final String myBalanceFormatted;
  final List<WithdrawalRequestModel> myRequests;
  final List<WithdrawalRequestModel> pendingApprovals;
  final List<WithdrawalRequestModel> readyToPay;
}
