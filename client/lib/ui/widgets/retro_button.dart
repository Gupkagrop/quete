import 'package:flutter/material.dart';
import '../../core/theme/retro_theme.dart';

/// Переиспользуемый 8-bit ретро-компонент кнопки с пиксельной рамкой и псевдо-тенью.
class RetroButton extends StatefulWidget {
  final String text;
  final VoidCallback? onPressed;
  final Color backgroundColor;
  final Color textColor;
  final Color borderColor;
  final Color shadowColor;
  final bool isLoading;
  final double? width;
  final double height;

  const RetroButton({
    super.key,
    required this.text,
    required this.onPressed,
    this.backgroundColor = RetroTheme.primaryNeonGreen,
    this.textColor = RetroTheme.background,
    this.borderColor = Colors.black,
    this.shadowColor = const Color(0xFF008F24),
    this.isLoading = false,
    this.width,
    this.height = 48.0,
  });

  @override
  State<RetroButton> createState() => _RetroButtonState();
}

class _RetroButtonState extends State<RetroButton> {
  bool _isPressed = false;

  bool get _isEnabled => widget.onPressed != null && !widget.isLoading;

  @override
  Widget build(BuildContext context) {
    const double shadowOffset = 4.0;
    final Color currentBg = _isEnabled
        ? widget.backgroundColor
        : widget.backgroundColor.withValues(alpha: 0.4);

    return SizedBox(
      width: widget.width,
      height: widget.height + shadowOffset,
      child: GestureDetector(
        onTapDown: _isEnabled ? (_) => setState(() => _isPressed = true) : null,
        onTapUp: _isEnabled
            ? (_) {
                setState(() => _isPressed = false);
                widget.onPressed?.call();
              }
            : null,
        onTapCancel: _isEnabled ? () => setState(() => _isPressed = false) : null,
        child: Stack(
          children: [
            // Пиксельная псевдо-тень под кнопкой
            Positioned(
              top: shadowOffset,
              left: shadowOffset,
              right: 0,
              bottom: 0,
              child: Container(
                decoration: BoxDecoration(
                  color: _isEnabled ? widget.shadowColor : Colors.transparent,
                  borderRadius: BorderRadius.zero,
                ),
              ),
            ),
            // Сама кнопка (смещается при нажатии)
            AnimatedPositioned(
              duration: const Duration(milliseconds: 50),
              top: _isPressed ? shadowOffset : 0,
              left: _isPressed ? shadowOffset : 0,
              right: _isPressed ? 0 : shadowOffset,
              bottom: _isPressed ? 0 : shadowOffset,
              child: Container(
                decoration: BoxDecoration(
                  color: currentBg,
                  borderRadius: BorderRadius.zero,
                  border: Border.all(
                    color: widget.borderColor,
                    width: 2.5,
                  ),
                ),
                alignment: Alignment.center,
                child: widget.isLoading
                    ? SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          color: widget.textColor,
                        ),
                      )
                    : Text(
                        widget.text.toUpperCase(),
                        style: TextStyle(
                          color: widget.textColor,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                          letterSpacing: 1.5,
                          fontFamily: 'Courier',
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
