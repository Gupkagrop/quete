import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:quete/core/theme/retro_theme.dart';
import 'package:quete/state/auth_provider.dart';
import 'package:quete/ui/widgets/retro_button.dart';

/// Экран гостевой авторизации и восстановления сессии по PIN-коду.
class AuthScreen extends ConsumerStatefulWidget {
  const AuthScreen({super.key});

  @override
  ConsumerState<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends ConsumerState<AuthScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nicknameController = TextEditingController();
  final _pinController = TextEditingController();
  static final _nicknameRegex = RegExp(r'^[a-zA-Z0-9_-]{2,20}$');
  static final _pinRegex = RegExp(r'^[0-9]{6}$');

  @override
  void dispose() {
    _nicknameController.dispose();
    _pinController.dispose();
    super.dispose();
  }

  void _submit() {
    if (_formKey.currentState?.validate() ?? false) {
      ref.read(authProvider.notifier).loginAsGuest(
            nickname: _nicknameController.text.trim(),
            pinCode: _pinController.text.trim().isNotEmpty
                ? _pinController.text.trim()
                : null,
          );
    }
  }

  @override
  Widget build(BuildContext context) {
    final authAsync = ref.watch(authProvider);
    final authState = authAsync.value;
    final isLoading = authState is AuthLoading;

    // Автоматический показ диалога сгенерированного PIN-кода
    ref.listen(authProvider, (previous, next) {
      final state = next.value;
      if (state is AuthAuthenticated && state.newGeneratedPin != null) {
        _showPinDialog(context, state.newGeneratedPin!);
      }
    });

    return Scaffold(
      backgroundColor: RetroTheme.background,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32.0),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Ретро-логотип
                    const Text(
                      'QUETE',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontFamily: 'Courier',
                        fontSize: 48,
                        fontWeight: FontWeight.bold,
                        color: RetroTheme.primaryNeonGreen,
                        letterSpacing: 6,
                        shadows: [
                          Shadow(
                            color: RetroTheme.primaryNeonGreen,
                            blurRadius: 10,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      '8-BIT MULTIPLAYER QUIZ',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontFamily: 'Courier',
                        fontSize: 12,
                        color: RetroTheme.accentNeonMagenta,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 3,
                      ),
                    ),
                    const SizedBox(height: 40),

                    // Поле ввода никнейма
                    const Text(
                      '> ВВЕДИТЕ НИКНЕЙМ:',
                      style: TextStyle(
                        color: RetroTheme.primaryNeonGreen,
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 1.5,
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextFormField(
                      controller: _nicknameController,
                      enabled: !isLoading,
                      style: const TextStyle(
                        color: RetroTheme.textWhite,
                        fontSize: 16,
                        letterSpacing: 1,
                      ),
                      decoration: const InputDecoration(
                        hintText: 'RetroPlayer99',
                      ),
                      validator: (value) {
                        final val = value?.trim() ?? '';
                        if (val.isEmpty) {
                          return 'Введите ваш никнейм';
                        }
                        if (!_nicknameRegex.hasMatch(val)) {
                          return 'От 2 до 20 символов (a-z, 0-9, _, -)';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 20),

                    // Поле опционального PIN-кода
                    const Text(
                      '> PIN-КОД (ЕСЛИ УЖЕ ИГРАЛИ):',
                      style: TextStyle(
                        color: RetroTheme.textMuted,
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 1,
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextFormField(
                      controller: _pinController,
                      enabled: !isLoading,
                      keyboardType: TextInputType.number,
                      inputFormatters: [
                        FilteringTextInputFormatter.digitsOnly,
                        LengthLimitingTextInputFormatter(6),
                      ],
                      style: const TextStyle(
                        color: RetroTheme.textWhite,
                        fontSize: 16,
                        letterSpacing: 3,
                      ),
                      decoration: const InputDecoration(
                        hintText: '------ (6 цифр для восстановления)',
                      ),
                      validator: (value) {
                        final val = value?.trim() ?? '';
                        if (val.isNotEmpty && !_pinRegex.hasMatch(val)) {
                          return 'PIN-код должен состоять ровно из 6 цифр';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 24),

                    // Сообщение об ошибке
                    if (authState is AuthError)
                      Container(
                        margin: const EdgeInsets.only(bottom: 20),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: RetroTheme.surface,
                          border: Border.all(
                            color: RetroTheme.errorRed,
                            width: 2,
                          ),
                        ),
                        child: Row(
                          children: [
                            const Icon(
                              Icons.error_outline,
                              color: RetroTheme.errorRed,
                              size: 20,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                authState.message,
                                style: const TextStyle(
                                  color: RetroTheme.errorRed,
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),

                    // Кнопка входа
                    RetroButton(
                      text: 'ВОЙТИ В ИГРУ',
                      isLoading: isLoading,
                      onPressed: isLoading ? null : _submit,
                    ),
                    const SizedBox(height: 24),

                    // Пояснительная ретро-сноска
                    const Text(
                      'Новым игрокам 6-значный PIN-код\nбудет сгенерирован автоматически.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: RetroTheme.textMuted,
                        fontSize: 11,
                        height: 1.5,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _showPinDialog(BuildContext context, String pinCode) {
    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        return Dialog(
          backgroundColor: RetroTheme.surface,
          shape: const RoundedRectangleBorder(borderRadius: BorderRadius.zero),
          child: Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              border: Border.all(color: RetroTheme.primaryNeonGreen, width: 3),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  'ВАШ PIN-КОД ВОССТАНОВЛЕНИЯ',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: RetroTheme.accentNeonMagenta,
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 1.5,
                  ),
                ),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  decoration: BoxDecoration(
                    color: RetroTheme.background,
                    border: Border.all(color: RetroTheme.primaryNeonGreen, width: 2),
                  ),
                  child: Text(
                    pinCode,
                    style: const TextStyle(
                      fontFamily: 'Courier',
                      fontSize: 32,
                      fontWeight: FontWeight.bold,
                      color: RetroTheme.primaryNeonGreen,
                      letterSpacing: 8,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Сохраните или запишите этот код!\nОн понадобится для входа под своим ником с других устройств.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: RetroTheme.textWhite,
                    fontSize: 12,
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 24),
                RetroButton(
                  text: 'СКОПИРОВАТЬ И В БОЙ!',
                  onPressed: () {
                    Clipboard.setData(ClipboardData(text: pinCode));
                    ref.read(authProvider.notifier).dismissNewPinDialog();
                    Navigator.of(ctx).pop();
                  },
                  width: double.infinity,
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
