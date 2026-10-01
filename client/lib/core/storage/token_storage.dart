import 'package:shared_preferences/shared_preferences.dart';

/// Сервис сохранения и чтения токенов авторизации в локальном хранилище.
class TokenStorage {
  static const String _accessTokenKey = 'quete_access_token';
  static const String _refreshTokenKey = 'quete_refresh_token';
  static const String _playerIdKey = 'quete_player_id';
  static const String _nicknameKey = 'quete_nickname';

  final SharedPreferences? _prefs;

  const TokenStorage([this._prefs]);

  Future<SharedPreferences> _getPrefs() async {
    return _prefs ?? await SharedPreferences.getInstance();
  }

  /// Сохраняет учетные данные и токены игрока.
  Future<void> saveAuthData({
    required String accessToken,
    required String refreshToken,
    required String playerId,
    required String nickname,
  }) async {
    final prefs = await _getPrefs();
    await prefs.setString(_accessTokenKey, accessToken);
    await prefs.setString(_refreshTokenKey, refreshToken);
    await prefs.setString(_playerIdKey, playerId);
    await prefs.setString(_nicknameKey, nickname);
  }

  /// Получает сохраненный JWT access-токен.
  Future<String?> getAccessToken() async {
    final prefs = await _getPrefs();
    return prefs.getString(_accessTokenKey);
  }

  /// Получает сохраненный refresh-токен.
  Future<String?> getRefreshToken() async {
    final prefs = await _getPrefs();
    return prefs.getString(_refreshTokenKey);
  }

  /// Получает ID текущего игрока.
  Future<String?> getPlayerId() async {
    final prefs = await _getPrefs();
    return prefs.getString(_playerIdKey);
  }

  /// Получает никнейм игрока.
  Future<String?> getNickname() async {
    final prefs = await _getPrefs();
    return prefs.getString(_nicknameKey);
  }

  /// Проверяет наличие токена доступа.
  Future<bool> hasToken() async {
    final token = await getAccessToken();
    return token != null && token.isNotEmpty;
  }

  /// Очищает все токены и данные сессии при выходе из аккаунта.
  Future<void> clear() async {
    final prefs = await _getPrefs();
    await prefs.remove(_accessTokenKey);
    await prefs.remove(_refreshTokenKey);
    await prefs.remove(_playerIdKey);
    await prefs.remove(_nicknameKey);
  }
}
