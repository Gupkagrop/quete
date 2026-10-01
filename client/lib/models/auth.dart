/// Профиль игрока.
class PlayerProfile {
  final String id;
  final String nickname;
  final String? pinCode;
  final DateTime? createdAt;

  const PlayerProfile({
    required this.id,
    required this.nickname,
    this.pinCode,
    this.createdAt,
  });

  factory PlayerProfile.fromJson(Map<String, dynamic> json) {
    return PlayerProfile(
      id: json['id'] as String,
      nickname: json['nickname'] as String,
      pinCode: json['pin_code'] as String?,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'] as String)
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nickname': nickname,
      'pin_code': pinCode,
      'created_at': createdAt?.toIso8601String(),
    };
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is PlayerProfile &&
          runtimeType == other.runtimeType &&
          id == other.id &&
          nickname == other.nickname &&
          pinCode == other.pinCode;

  @override
  int get hashCode => id.hashCode ^ nickname.hashCode ^ pinCode.hashCode;
}

/// Ответ сервера при успешной авторизации или обновлении токенов.
class AuthResponse {
  final String accessToken;
  final String refreshToken;
  final String tokenType;
  final PlayerProfile player;

  const AuthResponse({
    required this.accessToken,
    required this.refreshToken,
    required this.tokenType,
    required this.player,
  });

  factory AuthResponse.fromJson(Map<String, dynamic> json) {
    return AuthResponse(
      accessToken: json['access_token'] as String,
      refreshToken: json['refresh_token'] as String,
      tokenType: json['token_type'] as String? ?? 'bearer',
      player: PlayerProfile.fromJson(json['player'] as Map<String, dynamic>),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'access_token': accessToken,
      'refresh_token': refreshToken,
      'token_type': tokenType,
      'player': player.toJson(),
    };
  }
}
