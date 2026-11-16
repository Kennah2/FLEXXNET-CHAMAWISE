class AppUser {
  AppUser({
    required this.id,
    required this.name,
    required this.username,
    required this.email,
    this.phone,
    this.emailVerifiedAt,
  });

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
        id: json['id'] as int,
        name: json['name'] as String,
        username: json['username'] as String,
        email: json['email'] as String,
        phone: json['phone'] as String?,
        emailVerifiedAt: json['email_verified_at'] as String?,
      );

  final int id;
  final String name;
  final String username;
  final String email;
  final String? phone;
  final String? emailVerifiedAt;

  bool get isEmailVerified => emailVerifiedAt != null;
}
