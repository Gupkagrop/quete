import os
import re

php_files = []
for root, dirs, files in os.walk('.'):
    if 'vendor' in root or '.git' in root or '.gemini' in root or 'scratch' in root:
        continue
    for file in files:
        if file.endswith('.php') or file.endswith('.js'):
            php_files.append(os.path.join(root, file))

print(f"Auditing .innerHTML occurrences in {len(php_files)} files...")

inner_html_pattern = re.compile(r'\.innerHTML\s*=')

for file_path in php_files:
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            lines = f.read().split('\n')
            for i, line in enumerate(lines):
                if inner_html_pattern.search(line):
                    print(f"{file_path}:{i+1} - {line.strip()}")
    except Exception as e:
        print(f"Error reading {file_path}: {e}")
