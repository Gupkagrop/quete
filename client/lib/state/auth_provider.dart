import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/network/auth_api_service.dart';
import '../core/storage/token_storage.dart';
import '../models/auth.dart';

/// Состояние аутентификации пользователя.
sealed class AuthState {
  const AuthState();
}

class AuthInitial extends AuthState {
  const AuthInitial();
}

class AuthLoading extends AuthState {
  const AuthLoading();
}

class AuthAuthenticated extends AuthState {
  final PlayerProfile player;
  final String? newGeneratedPin;

  const AuthAuthenticated({
    required this.player,
    this.newGeneratedPin,
  });

  AuthAuthenticated copyWith({
    PlayerProfile? player,
    String? newGeneratedPin,
    bool clearGeneratedPin = false,
  }) {
    return AuthAuthenticated(
      player: player ?? this.player,
      newGeneratedPin: clearGeneratedPin
          ? null
          : (newGeneratedPin ?? this.newGeneratedPin),
    );
  }
}

class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated();
}

class AuthError extends AuthState {
  final String message;
  final bool isRateLimited;

  const AuthError({
    required this.message,
    this.isRateLimited = false,
  });
}

// Провайдеры зависимостей
final tokenStorageProvider = Provider<TokenStorage>((ref) {
  return const TokenStorage();
});

final authApiServiceProvider = Provider<AuthApiService>((ref) {
  return AuthApiService();
});

/// Riverpod AsyncNotifier управления состоянием авторизации игрока.
class AuthNotifier extends AsyncNotifier<AuthState> {
  late TokenStorage _storage;
  late AuthApiService _api;

  @override
  Future<AuthState> build() async {
    _storage = ref.watch(tokenStorageProvider);
    _api = ref.watch(authApiServiceProvider);

    final accessToken = await _storage.getAccessToken();
    if (accessToken == null || accessToken.isEmpty) {
      return const AuthUnauthenticated();
    }

    try {
      final player = await _api.getMe(accessToken: accessToken);
      return AuthAuthenticated(player: player);
    } catch (_) {
      // Пытаемся обновить токен через refresh_token
      final refreshToken = await _storage.getRefreshToken();
      if (refreshToken != null && refreshToken.isNotEmpty) {
        try {
          final refreshed = await _api.refreshToken(refreshToken: refreshToken);
          await _storage.saveAuthData(
            accessToken: refreshed.accessToken,
            refreshToken: refreshed.refreshToken,
            playerId: refreshed.player.id,
            nickname: refreshed.player.nickname,
          );
          return AuthAuthenticated(player: refreshed.player);
        } catch (_) {
          await _storage.clear();
          return const AuthUnauthenticated();
        }
      }
      await _storage.clear();
      return const AuthUnauthenticated();
    }
  }

  /// Выполняет гостевой вход или регистрацию по никнейму и PIN-коду.
  Future<void> loginAsGuest({
    required String nickname,
    String? pinCode,
  }) async {
    state = const AsyncValue.data(AuthLoading());

    try {
      final response = await _api.guestLogin(
        nickname: nickname,
        pinCode: pinCode,
      );

      await _storage.saveAuthData(
        accessToken: response.accessToken,
        refreshToken: response.refreshToken,
        playerId: response.player.id,
        nickname: response.player.nickname,
      );

      state = AsyncValue.data(
        AuthAuthenticated(
          player: response.player,
          newGeneratedPin: response.player.pinCode,
        ),
      );
    } on AuthException catch (e) {
      state = AsyncValue.data(
        AuthError(
          message: e.message,
          isRateLimited: e.isRateLimited,
        ),
      );
    } catch (e) {
      state = AsyncValue.data(
        AuthError(message: 'Не удалось подключиться к серверу: $e'),
      );
    }
  }

  /// Скрывает диалог сгенерированного PIN-кода.
  void dismissNewPinDialog() {
    final current = state.value;
    if (current is AuthAuthenticated) {
      state = AsyncValue.data(current.copyWith(clearGeneratedPin: true));
    }
  }

  /// Выход из аккаунта и очистка сессии.
  Future<void> logout() async {
    await _storage.clear();
    state = const AsyncValue.data(AuthUnauthenticated());
  }
}

final authProvider = AsyncNotifierProvider<AuthNotifier, AuthState>(
  AuthNotifier.new,
);
