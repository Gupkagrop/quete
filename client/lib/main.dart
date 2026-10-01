import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'core/router/app_router.dart';
import 'core/theme/retro_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(
    const ProviderScope(
      child: QueteApp(),
    ),
  );
}

/// Корневой виджет приложения Quete с поддержкой Riverpod и go_router.
class QueteApp extends ConsumerWidget {
  const QueteApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(appRouterProvider);

    return MaterialApp.router(
      title: 'Quete',
      debugShowCheckedModeBanner: false,
      theme: RetroTheme.darkTheme,
      routerConfig: router,
    );
  }
}
