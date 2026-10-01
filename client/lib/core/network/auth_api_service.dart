import 'dart:convert';
import 'package:http/http.dart' as http;
import '../../models/auth.dart';

/// Исключение при ошибках сетевой авторизации.
class AuthException implements Exception {
  final String message;
  final int statusCode;
  final bool isRateLimited;

  const AuthException({
    required this.message,
    required this.statusCode,
    this.isRateLimited = false,
  });

  @override
  String toString() => message;
}

/// Сервис выполнения HTTP-запросов к API авторизации.
class AuthApiService {
  final String baseUrl;
  final http.Client _client;

  AuthApiService({
    String? baseUrl,
    http.Client? client,
  })  : baseUrl = baseUrl ?? 'http://localhost:8000',
        _client = client ?? http.Client();

  /// Выполняет гостевой вход или регистрацию.
  Future<AuthResponse> guestLogin({
    required String nickname,
    String? pinCode,
  }) async {
    final uri = Uri.parse('$baseUrl/auth/guest');
    final body = <String, dynamic>{
      'nickname': nickname,
      if (pinCode != null && pinCode.isNotEmpty) 'pin_code': pinCode,
    };

    final response = await _client.post(
      uri,
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode(body),
    );

    return _handleAuthResponse(response);
  }

  /// Обновляет пару токенов по действующему refresh-токену.
  Future<AuthResponse> refreshToken({
    required String refreshToken,
  }) async {
    final uri = Uri.parse('$baseUrl/auth/refresh');
    final body = {'refresh_token': refreshToken};

    final response = await _client.post(
      uri,
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode(body),
    );

    return _handleAuthResponse(response);
  }

  /// Получает профиль текущего игрока по access-токену.
  Future<PlayerProfile> getMe({
    required String accessToken,
  }) async {
    final uri = Uri.parse('$baseUrl/auth/me');

    final response = await _client.get(
      uri,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $accessToken',
      },
    );

    if (response.statusCode == 200) {
      final decoded = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
      return PlayerProfile.fromJson(decoded);
    }

    throw _parseError(response);
  }

  AuthResponse _handleAuthResponse(http.Response response) {
    if (response.statusCode == 200) {
      final decoded = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
      return AuthResponse.fromJson(decoded);
    }
    throw _parseError(response);
  }

  AuthException _parseError(http.Response response) {
    String message = 'Ошибка сервера (${response.statusCode})';
    try {
      final decoded = jsonDecode(utf8.decode(response.bodyBytes));
      if (decoded is Map<String, dynamic> && decoded.containsKey('detail')) {
        message = decoded['detail'] as String;
      }
    } catch (_) {}

    return AuthException(
      message: message,
      statusCode: response.statusCode,
      isRateLimited: response.statusCode == 429,
    );
  }
}
