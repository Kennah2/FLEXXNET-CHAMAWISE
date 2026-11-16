/// The shape StkPushController returns for both `POST .../contributions/mpesa` (initiate) and
/// `GET .../contributions/mpesa/{transaction}` (poll) — see backend/docs/API.md.
class StkPushStatus {
  StkPushStatus({
    required this.transactionId,
    required this.status,
    this.message,
    this.resultDesc,
    this.isTerminal = false,
  });

  factory StkPushStatus.fromInitiateJson(Map<String, dynamic> json) => StkPushStatus(
        transactionId: json['transaction_id'] as int,
        status: json['status'] as String,
        message: json['message'] as String?,
      );

  factory StkPushStatus.fromPollJson(Map<String, dynamic> json) => StkPushStatus(
        transactionId: json['transaction_id'] as int,
        status: json['status'] as String,
        resultDesc: json['result_desc'] as String?,
        isTerminal: json['is_terminal'] as bool,
      );

  final int transactionId;
  final String status;
  final String? message;
  final String? resultDesc;
  final bool isTerminal;

  bool get isCompleted => status == 'completed';
}
