import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:quete/state/auth_provider.dart';
import 'package:quete/ui/screens/auth/auth_screen.dart';
import 'package:quete/ui/screens/menu/main_menu_screen.dart';

/// Провайдер декларативной маршрутизации go_router с Auth Guard.
final appRouterProvider = Provider<GoRouter>((ref) {
  // Класс-слушатель для триггера обновления маршрутизатора при смене состояния AuthProvider
  final notifier = _RouterRefreshNotifier(ref);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: notifier,
    redirect: (BuildContext context, GoRouterState state) {
      final authAsync = ref.read(authProvider);
      final authState = authAsync.value;

      final isGoingToAuth = state.matchedLocation == '/auth';

      // Если состояние загружается и мы на /auth, остаёмся
      if (authState is AuthLoading) {
        return null;
      }

      final isAuthenticated = authState is AuthAuthenticated;

      // Не авторизован и пытается зайти на защищённый экран -> редирект на /auth
      if (!isAuthenticated && !isGoingToAuth) {
        return '/auth';
      }

      // Уже авторизован и находится на экране входа -> редирект в главное меню
      if (isAuthenticated && isGoingToAuth) {
        return '/';
      }

      return null;
    },
    routes: [
      GoRoute(
        path: '/auth',
        name: 'auth',
        builder: (context, state) => const AuthScreen(),
      ),
      GoRoute(
        path: '/',
        name: 'home',
        builder: (context, state) => const MainMenuScreen(),
      ),
    ],
  );
});

class _RouterRefreshNotifier extends ChangeNotifier {
  _RouterRefreshNotifier(Ref ref) {
    ref.listen(authProvider, (previous, next) {
      notifyListeners();
    });
  }
}
