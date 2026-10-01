import 'package:flutter/material.dart';

/// 8-bit ретро-палитра и тема оформления проекта Quete.
class RetroTheme {
  // Цветовая палитра
  static const Color background = Color(0xFF0D0D0D);
  static const Color surface = Color(0xFF1A1A1A);
  static const Color surfaceBorder = Color(0xFF333333);
  static const Color primaryNeonGreen = Color(0xFF00FF41);
  static const Color accentNeonMagenta = Color(0xFFFF00FF);
  static const Color errorRed = Color(0xFFFF3131);
  static const Color textWhite = Color(0xFFFFFFFF);
  static const Color textMuted = Color(0xFFAAAAAA);

  /// Базовая тёмная 8-bit ретро-тема
  static ThemeData get darkTheme {
    return ThemeData(
      brightness: Brightness.dark,
      scaffoldBackgroundColor: background,
      primaryColor: primaryNeonGreen,
      canvasColor: background,
      cardColor: surface,
      colorScheme: const ColorScheme.dark(
        primary: primaryNeonGreen,
        secondary: accentNeonMagenta,
        surface: surface,
        error: errorRed,
        onPrimary: background,
        onSecondary: textWhite,
        onSurface: textWhite,
        onError: textWhite,
      ),
      fontFamily: 'Courier',
      fontFamilyFallback: const ['monospace'],
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surface,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        hintStyle: const TextStyle(color: textMuted, fontSize: 14),
        labelStyle: const TextStyle(color: primaryNeonGreen, fontSize: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.zero,
          borderSide: const BorderSide(color: surfaceBorder, width: 2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.zero,
          borderSide: const BorderSide(color: surfaceBorder, width: 2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.zero,
          borderSide: const BorderSide(color: primaryNeonGreen, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.zero,
          borderSide: const BorderSide(color: errorRed, width: 2),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.zero,
          borderSide: const BorderSide(color: errorRed, width: 2),
        ),
      ),
      textTheme: const TextTheme(
        headlineLarge: TextStyle(
          color: primaryNeonGreen,
          fontSize: 28,
          fontWeight: FontWeight.bold,
          letterSpacing: 2,
        ),
        headlineMedium: TextStyle(
          color: textWhite,
          fontSize: 20,
          fontWeight: FontWeight.bold,
          letterSpacing: 1.5,
        ),
        bodyLarge: TextStyle(
          color: textWhite,
          fontSize: 16,
          letterSpacing: 1,
        ),
        bodyMedium: TextStyle(
          color: textWhite,
          fontSize: 14,
          letterSpacing: 0.5,
        ),
        labelLarge: TextStyle(
          color: background,
          fontSize: 14,
          fontWeight: FontWeight.bold,
          letterSpacing: 1.2,
        ),
      ),
    );
  }
}
