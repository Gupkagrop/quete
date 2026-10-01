import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:quete/core/theme/retro_theme.dart';
import 'package:quete/state/auth_provider.dart';
import 'package:quete/ui/widgets/retro_button.dart';

/// Экран главного меню (placeholder для последующих спринтов лобби).
class MainMenuScreen extends ConsumerWidget {
  const MainMenuScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider).value;
    final nickname = authState is AuthAuthenticated
        ? authState.player.nickname
        : 'ИГРОК';

    return Scaffold(
      backgroundColor: RetroTheme.background,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32.0),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text(
                    'QUETE',
                    style: TextStyle(
                      fontFamily: 'Courier',
                      fontSize: 40,
                      fontWeight: FontWeight.bold,
                      color: RetroTheme.primaryNeonGreen,
                      letterSpacing: 4,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'ГЛАВНОЕ МЕНЮ',
                    style: TextStyle(
                      fontFamily: 'Courier',
                      fontSize: 14,
                      color: RetroTheme.textMuted,
                      letterSpacing: 2,
                    ),
                  ),
                  const SizedBox(height: 32),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: RetroTheme.surface,
                      border: Border.all(color: RetroTheme.surfaceBorder, width: 2),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.person,
                          color: RetroTheme.primaryNeonGreen,
                          size: 28,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'ПРОФИЛЬ:',
                                style: TextStyle(
                                  color: RetroTheme.textMuted,
                                  fontSize: 11,
                                  letterSpacing: 1,
                                ),
                              ),
                              Text(
                                nickname,
                                style: const TextStyle(
                                  color: RetroTheme.textWhite,
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                  letterSpacing: 1.5,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 32),
                  RetroButton(
                    text: 'СОЗДАТЬ КОМНАТУ',
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Лобби будет доступно в Спринте 3!'),
                        ),
                      );
                    },
                    width: double.infinity,
                  ),
                  const SizedBox(height: 16),
                  RetroButton(
                    text: 'ВОЙТИ ПО КОДУ',
                    backgroundColor: RetroTheme.surface,
                    textColor: RetroTheme.textWhite,
                    shadowColor: RetroTheme.surfaceBorder,
                    borderColor: RetroTheme.textWhite,
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Подключение будет доступно в Спринте 3!'),
                        ),
                      );
                    },
                    width: double.infinity,
                  ),
                  const SizedBox(height: 32),
                  TextButton.icon(
                    onPressed: () {
                      ref.read(authProvider.notifier).logout();
                    },
                    icon: const Icon(Icons.logout, color: RetroTheme.errorRed, size: 18),
                    label: const Text(
                      'ВЫЙТИ ИЗ АККАУНТА',
                      style: TextStyle(
                        color: RetroTheme.errorRed,
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 1,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
