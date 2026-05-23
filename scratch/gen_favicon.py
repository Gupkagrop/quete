"""
Генератор favicon.ico для сайта "Куэте" (ретро-игровой квиз).
Создаёт пиксельный знак вопроса на тёмном фоне в стиле 8-bit аркадных игр.
Сохраняет ICO в нескольких размерах: 16x16, 32x32, 48x48.
"""

from PIL import Image, ImageDraw, ImageFont
import os

# ────────────────────────────────────────────────
# Цветовая палитра (ретро-стиль)
# ────────────────────────────────────────────────
BG_COLOR      = (15,  12,  35)   # тёмно-синий фон
PIXEL_COLOR   = (200, 255,  50)  # неоново-жёлтый (основной)
GLOW_COLOR    = (100, 200,  20)  # чуть темнее для тени/обводки
DOT_COLOR     = (255, 255, 100)  # точка (яркая)

# ────────────────────────────────────────────────
# Пиксельная карта знака "?" в сетке 8x8 (1=пиксель, 0=фон)
# Масштабируется до нужного размера
# ────────────────────────────────────────────────
QUESTION_MARK_8 = [
    [0, 1, 1, 1, 1, 0, 0, 0],
    [1, 1, 0, 0, 1, 1, 0, 0],
    [0, 0, 0, 0, 1, 1, 0, 0],
    [0, 0, 0, 1, 1, 0, 0, 0],
    [0, 0, 1, 1, 0, 0, 0, 0],
    [0, 0, 0, 0, 0, 0, 0, 0],
    [0, 0, 1, 1, 0, 0, 0, 0],
    [0, 0, 1, 1, 0, 0, 0, 0],
]

def draw_pixel_icon(size: int) -> Image.Image:
    """Рисует иконку заданного размера с пиксельным знаком вопроса."""
    img = Image.new("RGBA", (size, size), BG_COLOR + (255,))
    draw = ImageDraw.Draw(img)

    # Соотношение: знак вопроса занимает ~6/8 ширины, центрирован
    grid_w = 8
    grid_h = 8
    cell = size // (grid_w + 2)          # размер одного пикселя карты
    offset_x = (size - cell * grid_w) // 2
    offset_y = (size - cell * grid_h) // 2

    for row_i, row in enumerate(QUESTION_MARK_8):
        for col_i, val in enumerate(row):
            if val:
                x0 = offset_x + col_i * cell
                y0 = offset_y + row_i * cell
                x1 = x0 + cell - 1
                y1 = y0 + cell - 1

                # Тень (смещение на 1 пиксель вправо-вниз)
                if cell > 2:
                    draw.rectangle([x0+1, y0+1, x1+1, y1+1], fill=GLOW_COLOR + (180,))

                # Основной пиксель
                color = DOT_COLOR if row_i >= 6 else PIXEL_COLOR
                draw.rectangle([x0, y0, x1, y1], fill=color + (255,))

    # Скруглённая рамка (по желанию — лёгкое свечение по краям)
    if size >= 32:
        border_col = (60, 120, 0, 120)
        draw.rectangle([0, 0, size-1, size-1], outline=border_col, width=2)

    return img


def generate_favicon(output_path: str):
    sizes = [16, 32, 48]
    icons = [draw_pixel_icon(s) for s in sizes]

    # PIL сохраняет ICO с несколькими размерами через append_images
    icons[0].save(
        output_path,
        format="ICO",
        sizes=[(s, s) for s in sizes],
        append_images=icons[1:],
    )
    print(f"[OK] favicon.ico saved: {output_path}")
    print(f"     Sizes: {sizes}")


if __name__ == "__main__":
    out = os.path.join(os.path.dirname(__file__), "..", "favicon.ico")
    out = os.path.normpath(out)
    generate_favicon(out)
