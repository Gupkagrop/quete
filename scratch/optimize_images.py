import os
from PIL import Image

img_dir = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity\assets\img"
print(f"Optimizing images in: {img_dir}\n")

total_saved = 0
total_original = 0
total_new = 0

for file in os.listdir(img_dir):
    filepath = os.path.join(img_dir, file)
    if not os.path.isfile(filepath):
        continue
    
    # Check extension
    ext = os.path.splitext(file)[1].lower()
    if ext not in [".jpg", ".jpeg", ".png"]:
        continue
        
    orig_size = os.path.getsize(filepath)
    total_original += orig_size
    
    try:
        img = Image.open(filepath)
        
        # Decide resize rules based on filename
        filename = os.path.splitext(file)[0].lower()
        
        new_width, new_height = img.size
        
        if filename.startswith("avatar"):
            # Avatar images (shown at 34px - 100px)
            max_size = 128
            img.thumbnail((max_size, max_size), Image.Resampling.LANCZOS)
        elif filename in ["brain_gears", "hourglass", "icon_lock", "icon_sword", "judge_sign", "question_mark", "spy_mask"]:
            # UI Icons
            max_size = 256
            img.thumbnail((max_size, max_size), Image.Resampling.LANCZOS)
        elif filename in ["login_insert_coin", "medal_1", "medal_2", "medal_3"]:
            # Illustration / Large elements
            max_size = 512
            img.thumbnail((max_size, max_size), Image.Resampling.LANCZOS)
        elif filename.startswith("slide"):
            # Slides
            max_width = 600
            if img.width > max_width:
                aspect = img.height / img.width
                img = img.resize((max_width, int(max_width * aspect)), Image.Resampling.LANCZOS)
        else:
            # General optimization
            pass
            
        # Save optimized image back overwriting the original file
        if ext in [".jpg", ".jpeg"]:
            # Save JPEG with quality optimization
            img.convert("RGB").save(filepath, "JPEG", quality=80, optimize=True)
        elif ext == ".png":
            # Save PNG with optimization
            img.save(filepath, "PNG", optimize=True)
            
        new_size = os.path.getsize(filepath)
        total_new += new_size
        saved = orig_size - new_size
        total_saved += saved
        
        print(f"File: {file}")
        print(f"  Original: {orig_size / 1024:.2f} KB | Optimized: {new_size / 1024:.2f} KB")
        print(f"  Saved: {saved / 1024:.2f} KB ({saved / orig_size * 100:.1f}%)")
        
    except Exception as e:
        print(f"Error optimizing {file}: {e}")
        total_new += orig_size

print(f"\n--- Total Optimization Report ---")
print(f"Original total size: {total_original / 1024 / 1024:.2f} MB")
print(f"New total size: {total_new / 1024 / 1024:.2f} MB")
print(f"Total space saved: {total_saved / 1024 / 1024:.2f} MB ({total_saved / total_original * 100:.1f}%)")
