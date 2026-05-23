import os
import time

root_dir = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity"
exclude_dirs = {"vendor", "scratch", ".git"}
exclude_subpaths = {"assets/img"}

modified_files = []
now = time.time()

for root, dirs, files in os.walk(root_dir):
    # filter out excluded dirs
    dirs[:] = [d for d in dirs if d not in exclude_dirs]
    
    for file in files:
        filepath = os.path.join(root, file)
        
        # Check subpath exclusion
        rel_path = os.path.relpath(filepath, root_dir)
        norm_rel = rel_path.replace("\\", "/")
        if any(norm_rel.startswith(p) for p in exclude_subpaths):
            continue
            
        try:
            mtime = os.path.getmtime(filepath)
            # files modified in the last 24 hours (86400 seconds)
            if now - mtime < 86400:
                modified_files.append((rel_path, mtime))
        except Exception:
            pass

# Sort by mtime descending
modified_files.sort(key=lambda x: x[1], reverse=True)

print("Recently modified files (last 24 hours):")
for path, mtime in modified_files:
    print(f"{path} - {time.ctime(mtime)}")
