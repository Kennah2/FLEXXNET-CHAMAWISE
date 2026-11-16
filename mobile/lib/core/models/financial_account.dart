class FinancialAccount {
  FinancialAccount({
    required this.id,
    required this.name,
    required this.type,
    required this.isDefault,
    required this.balanceFormatted,
  });

  factory FinancialAccount.fromJson(Map<String, dynamic> json) => FinancialAccount(
        id: json['id'] as int,
        name: json['name'] as String,
        type: json['type'] as String,
        isDefault: json['is_default'] as bool,
        balanceFormatted: json['balance_formatted'] as String,
      );

  final int id;
  final String name;
  final String type;
  final bool isDefault;
  final String balanceFormatted;
}
