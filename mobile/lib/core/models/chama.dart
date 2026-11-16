class Chama {
  Chama({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    required this.groupType,
    required this.currency,
    required this.status,
    this.formationDate,
    this.myRoles = const [],
  });

  factory Chama.fromJson(Map<String, dynamic> json) => Chama(
        id: json['id'] as int,
        name: json['name'] as String,
        slug: json['slug'] as String,
        description: json['description'] as String?,
        groupType: json['group_type'] as String,
        currency: json['currency'] as String,
        status: json['status'] as String,
        formationDate: json['formation_date'] as String?,
        myRoles: (json['my_roles'] as List<dynamic>? ?? []).cast<String>(),
      );

  final int id;
  final String name;
  final String slug;
  final String? description;
  final String groupType;
  final String currency;
  final String status;
  final String? formationDate;
  final List<String> myRoles;

  /// Mirrors the web app's ChamaPermissions::MANAGE_FINANCES-gated UI — a rough client-side
  /// display hint only. The API re-checks the real permission on every write; this never
  /// substitutes for that (Part 15 — never re-implement an authorization decision).
  bool get canLikelyManageFinances =>
      myRoles.contains('chairperson') || myRoles.contains('treasurer');
}

class ChamaMember {
  ChamaMember({
    required this.id,
    required this.membershipNumber,
    required this.status,
    required this.userName,
  });

  factory ChamaMember.fromJson(Map<String, dynamic> json) => ChamaMember(
        id: json['id'] as int,
        membershipNumber: json['membership_number'] as String,
        status: json['status'] as String,
        userName: (json['user']?['name'] as String?) ?? 'Unknown',
      );

  final int id;
  final String membershipNumber;
  final String status;
  final String userName;
}
