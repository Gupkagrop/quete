import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:quete/core/network/auth_api_service.dart';
import 'package:quete/core/storage/token_storage.dart';
import 'package:quete/models/auth.dart';
import 'package:quete/state/auth_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

class FakeAuthApiService extends AuthApiService {
  AuthResponse? guestLoginResult;
  AuthException? guestLoginError;

  @override
  Future<AuthResponse> guestLogin({
    required String nickname,
    String? pinCode,
  }) async {
    if (guestLoginError != null) {
      throw guestLoginError!;
    }
    return guestLoginResult ??
        AuthResponse(
          accessToken: 'fake_jwt_token',
          refreshToken: 'fake_refresh_token',
          tokenType: 'bearer',
          player: PlayerProfile(
            id: 'player-1',
            nickname: nickname,
            pinCode: pinCode ?? '123456',
          ),
        );
  }

  @override
  Future<PlayerProfile> getMe({required String accessToken}) async {
    return const PlayerProfile(id: 'player-1', nickname: 'TestUser');
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('AuthProvider & AuthNotifier', () {
    late TokenStorage storage;
    late FakeAuthApiService fakeApi;

    setUp(() async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();
      storage = TokenStorage(prefs);
      fakeApi = FakeAuthApiService();
    });

    test('initial state is AuthUnauthenticated when storage is empty', () async {
      final container = ProviderContainer(
        overrides: [
          tokenStorageProvider.overrideWithValue(storage),
          authApiServiceProvider.overrideWithValue(fakeApi),
        ],
      );
      addTearDown(container.dispose);

      final state = await container.read(authProvider.future);
      expect(state, isA<AuthUnauthenticated>());
    });

    test('successful loginAsGuest saves tokens and transitions to AuthAuthenticated', () async {
      final container = ProviderContainer(
        overrides: [
          tokenStorageProvider.overrideWithValue(storage),
          authApiServiceProvider.overrideWithValue(fakeApi),
        ],
      );
      addTearDown(container.dispose);

      // Ждём инициализации
      await container.read(authProvider.future);

      await container.read(authProvider.notifier).loginAsGuest(
            nickname: 'Knight99',
          );

      final state = container.read(authProvider).value;
      expect(state, isA<AuthAuthenticated>());
      final auth = state as AuthAuthenticated;
      expect(auth.player.nickname, equals('Knight99'));
      expect(auth.newGeneratedPin, equals('123456'));

      // Проверяем запись в TokenStorage
      expect(await storage.hasToken(), isTrue);
      expect(await storage.getAccessToken(), equals('fake_jwt_token'));
    });

    test('failed loginAsGuest with rate limit sets AuthError with isRateLimited', () async {
      fakeApi.guestLoginError = const AuthException(
        message: 'Слишком много попыток ввода PIN-кода.',
        statusCode: 429,
        isRateLimited: true,
      );

      final container = ProviderContainer(
        overrides: [
          tokenStorageProvider.overrideWithValue(storage),
          authApiServiceProvider.overrideWithValue(fakeApi),
        ],
      );
      addTearDown(container.dispose);

      await container.read(authProvider.future);

      await container.read(authProvider.notifier).loginAsGuest(
            nickname: 'Spammer',
            pinCode: '000000',
          );

      final state = container.read(authProvider).value;
      expect(state, isA<AuthError>());
      final err = state as AuthError;
      expect(err.isRateLimited, isTrue);
      expect(err.message, contains('Слишком много попыток'));
    });

    test('logout clears storage and sets AuthUnauthenticated', () async {
      await storage.saveAuthData(
        accessToken: 'valid_jwt',
        refreshToken: 'valid_ref',
        playerId: 'id_1',
        nickname: 'Player',
      );

      final container = ProviderContainer(
        overrides: [
          tokenStorageProvider.overrideWithValue(storage),
          authApiServiceProvider.overrideWithValue(fakeApi),
        ],
      );
      addTearDown(container.dispose);

      final state = await container.read(authProvider.future);
      expect(state, isA<AuthAuthenticated>());

      await container.read(authProvider.notifier).logout();

      final newState = container.read(authProvider).value;
      expect(newState, isA<AuthUnauthenticated>());
      expect(await storage.hasToken(), isFalse);
    });
  });
}
