import 'package:flutter_test/flutter_test.dart';
import 'package:quete/core/storage/token_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('TokenStorage', () {
    late TokenStorage storage;

    setUp(() async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();
      storage = TokenStorage(prefs);
    });

    test('hasToken returns false initially', () async {
      expect(await storage.hasToken(), isFalse);
      expect(await storage.getAccessToken(), isNull);
    });

    test('saveAuthData and retrieve values correctly', () async {
      await storage.saveAuthData(
        accessToken: 'test_access_jwt',
        refreshToken: 'test_refresh_uuid',
        playerId: 'player-uuid-123',
        nickname: 'PixelHero',
      );

      expect(await storage.hasToken(), isTrue);
      expect(await storage.getAccessToken(), equals('test_access_jwt'));
      expect(await storage.getRefreshToken(), equals('test_refresh_uuid'));
      expect(await storage.getPlayerId(), equals('player-uuid-123'));
      expect(await storage.getNickname(), equals('PixelHero'));
    });

    test('clear removes all stored credentials', () async {
      await storage.saveAuthData(
        accessToken: 'jwt_to_remove',
        refreshToken: 'refresh_to_remove',
        playerId: 'uuid_to_remove',
        nickname: 'Hero',
      );

      expect(await storage.hasToken(), isTrue);

      await storage.clear();

      expect(await storage.hasToken(), isFalse);
      expect(await storage.getAccessToken(), isNull);
      expect(await storage.getRefreshToken(), isNull);
      expect(await storage.getPlayerId(), isNull);
      expect(await storage.getNickname(), isNull);
    });
  });
}
