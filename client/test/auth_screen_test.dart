import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:quete/core/network/auth_api_service.dart';
import 'package:quete/core/storage/token_storage.dart';
import 'package:quete/models/auth.dart';
import 'package:quete/state/auth_provider.dart';
import 'package:quete/ui/screens/auth/auth_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

class MockAuthApiService extends AuthApiService {
  String? lastNickname;

  @override
  Future<AuthResponse> guestLogin({
    required String nickname,
    String? pinCode,
  }) async {
    lastNickname = nickname;
    return AuthResponse(
      accessToken: 'token',
      refreshToken: 'refresh',
      tokenType: 'bearer',
      player: PlayerProfile(id: '1', nickname: nickname, pinCode: '654321'),
    );
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('AuthScreen displays all 8-bit elements and validates input', (
    WidgetTester tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    final mockApi = MockAuthApiService();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(TokenStorage(prefs)),
          authApiServiceProvider.overrideWithValue(mockApi),
        ],
        child: const MaterialApp(
          home: AuthScreen(),
        ),
      ),
    );

    // Ожидаем завершения сборки
    await tester.pumpAndSettle();

    // Проверяем наличие логотипа и заголовков
    expect(find.text('QUETE'), findsOneWidget);
    expect(find.text('8-BIT MULTIPLAYER QUIZ'), findsOneWidget);
    expect(find.text('> ВВЕДИТЕ НИКНЕЙМ:'), findsOneWidget);
    expect(find.text('> PIN-КОД (ЕСЛИ УЖЕ ИГРАЛИ):'), findsOneWidget);
    expect(find.text('ВОЙТИ В ИГРУ'), findsOneWidget);

    // 1. Попытка нажать вход с пустым полем -> ошибка валидации
    await tester.tap(find.text('ВОЙТИ В ИГРУ'));
    await tester.pumpAndSettle();

    expect(find.text('Введите ваш никнейм'), findsOneWidget);

    // 2. Ввод невалидного никнейма со спецсимволами
    final nicknameField = find.byType(TextFormField).first;
    await tester.enterText(nicknameField, 'Bad Nick!');
    await tester.tap(find.text('ВОЙТИ В ИГРУ'));
    await tester.pumpAndSettle();

    expect(find.text('От 2 до 20 символов (a-z, 0-9, _, -)'), findsOneWidget);

    // 3. Ввод невалидного PIN-кода (3 цифры вместо 6)
    await tester.enterText(nicknameField, 'ValidPlayer');
    final pinField = find.byType(TextFormField).at(1);
    await tester.enterText(pinField, '123');
    await tester.tap(find.text('ВОЙТИ В ИГРУ'));
    await tester.pumpAndSettle();

    expect(find.text('PIN-код должен состоять ровно из 6 цифр'), findsOneWidget);

    // 4. Очистка неверного PIN и успешный вход
    await tester.enterText(pinField, '');
    await tester.tap(find.text('ВОЙТИ В ИГРУ'));
    await tester.pumpAndSettle();

    // Должен появиться диалог с новым сгенерированным PIN-кодом
    expect(find.text('ВАШ PIN-КОД ВОССТАНОВЛЕНИЯ'), findsOneWidget);
    expect(find.text('654321'), findsOneWidget);
    expect(find.text('СКОПИРОВАТЬ И В БОЙ!'), findsOneWidget);

    // Закрытие диалога
    await tester.tap(find.text('СКОПИРОВАТЬ И В БОЙ!'));
    await tester.pumpAndSettle();

    expect(find.text('ВАШ PIN-КОД ВОССТАНОВЛЕНИЯ'), findsNothing);
  });
}
