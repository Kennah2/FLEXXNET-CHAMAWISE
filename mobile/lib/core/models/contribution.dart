class ContributionObligation {
  ContributionObligation({
    required this.id,
    this.scheduleName,
    this.dueAt,
    required this.outstandingCents,
    required this.outstandingFormatted,
    required this.status,
    required this.penaltyAmountCents,
    required this.penaltyStatus,
  });

  factory ContributionObligation.fromJson(Map<String, dynamic> json) => ContributionObligation(
        id: json['id'] as int,
        scheduleName: json['schedule_name'] as String?,
        dueAt: json['due_at'] as String?,
        outstandingCents: json['outstanding_cents'] as int,
        outstandingFormatted: json['outstanding_formatted'] as String,
        status: json['status'] as String,
        penaltyAmountCents: json['penalty_amount_cents'] as int,
        penaltyStatus: json['penalty_status'] as String,
      );

  final int id;
  final String? scheduleName;
  final String? dueAt;
  final int outstandingCents;
  final String outstandingFormatted;
  final String status;
  final int penaltyAmountCents;
  final String penaltyStatus;

  bool get hasPenalty => penaltyStatus == 'assessed';
}

class Contribution {
  Contribution({
    required this.id,
    this.userName,
    required this.amountFormatted,
    required this.paymentMethodLabel,
    this.paidAt,
    required this.status,
  });

  factory Contribution.fromJson(Map<String, dynamic> json) => Contribution(
        id: json['id'] as int,
        userName: json['user']?['name'] as String?,
        amountFormatted: json['amount_formatted'] as String,
        paymentMethodLabel: json['payment_method_label'] as String,
        paidAt: json['paid_at'] as String?,
        status: json['status'] as String,
      );

  final int id;
  final String? userName;
  final String amountFormatted;
  final String paymentMethodLabel;
  final String? paidAt;
  final String status;
}

/// The shape of `GET /chamas/{organization}/contributions` (backend docs/API.md).
class ContributionsOverview {
  ContributionsOverview({
    required this.canManageFinances,
    required this.outstandingFormatted,
    required this.obligations,
    required this.payments,
    required this.pendingVerifications,
  });

  factory ContributionsOverview.fromJson(Map<String, dynamic> json) => ContributionsOverview(
        canManageFinances: json['can_manage_finances'] as bool,
        outstandingFormatted: json['outstanding_formatted'] as String,
        obligations: (json['obligations'] as List<dynamic>)
            .map((e) => ContributionObligation.fromJson(e as Map<String, dynamic>))
            .toList(),
        payments:
            (json['payments'] as List<dynamic>).map((e) => Contribution.fromJson(e as Map<String, dynamic>)).toList(),
        pendingVerifications: (json['pending_verifications'] as List<dynamic>)
            .map((e) => Contribution.fromJson(e as Map<String, dynamic>))
            .toList(),
      );

  final bool canManageFinances;
  final String outstandingFormatted;
  final List<ContributionObligation> obligations;
  final List<Contribution> payments;
  final List<Contribution> pendingVerifications;
}
