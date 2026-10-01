import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:quete/core/storage/token_storage.dart';
import 'package:quete/main.dart';
import 'package:quete/state/auth_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('QueteApp smoke test - boots into AuthScreen when unauthenticated', (
    WidgetTester tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tokenStorageProvider.overrideWithValue(TokenStorage(prefs)),
        ],
        child: const QueteApp(),
      ),
    );

    await tester.pumpAndSettle();

    // Приложение без токенов переходит на экран входа
    expect(find.text('QUETE'), findsOneWidget);
    expect(find.text('ВОЙТИ В ИГРУ'), findsOneWidget);
  });
}
