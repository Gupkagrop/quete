import os
from PIL import Image

img_dir = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity-git\assets\img"
files = [
    "joystick.png",
    "slide1.png",
    "slide2.png",
    "slide3.png",
    "slide4.png",
    "slide5.png"
]

for filename in files:
    src_path = os.path.join(img_dir, filename)
    dst_path = os.path.join(img_dir, filename.replace(".png", ".webp"))
    
    if os.path.exists(src_path):
        try:
            with Image.open(src_path) as img:
                img.save(dst_path, "WEBP", quality=85)
                print(f"Successfully converted {filename} to WebP ({os.path.getsize(dst_path)} bytes)")
        except Exception as e:
            print(f"Error converting {filename}: {e}")
    else:
        print(f"File not found: {filename}")
