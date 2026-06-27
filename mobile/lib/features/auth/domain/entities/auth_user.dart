class AuthUser {
  const AuthUser({
    required this.id,
    required this.username,
    this.displayName,
    this.avatarUrl,
    this.bio,
    this.locale = 'uz',
    this.phoneVerified = false,
  });

  final String id;
  final String username;
  final String? displayName;
  final String? avatarUrl;
  final String? bio;
  final String locale;
  final bool phoneVerified;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: json['id'] as String,
      username: json['username'] as String,
      displayName: json['display_name'] as String?,
      avatarUrl: json['avatar_url'] as String?,
      bio: json['bio'] as String?,
      locale: json['locale'] as String? ?? 'uz',
      phoneVerified: json['phone_verified'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'username': username,
        'display_name': displayName,
        'avatar_url': avatarUrl,
        'bio': bio,
        'locale': locale,
        'phone_verified': phoneVerified,
      };

  AuthUser copyWith({
    String? displayName,
    String? bio,
    String? locale,
    bool? phoneVerified,
  }) {
    return AuthUser(
      id: id,
      username: username,
      displayName: displayName ?? this.displayName,
      avatarUrl: avatarUrl,
      bio: bio ?? this.bio,
      locale: locale ?? this.locale,
      phoneVerified: phoneVerified ?? this.phoneVerified,
    );
  }
}
