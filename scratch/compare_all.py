import paramiko
import os
import hashlib
import sys

# Configure stdout to use UTF-8
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

hostname = "155.212.165.20"
port = 22
username = "root"
password = "_XrYd2K_GNgv2uK"
key_path = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity-git\scratch\id_quete"

local_root = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity-git"
remote_root = "/var/www/quete"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

connected = False
if os.path.exists(key_path):
    try:
        ssh.connect(hostname, port=port, username=username, key_filename=key_path, timeout=30)
        connected = True
    except Exception:
        pass

if not connected:
    try:
        ssh.connect(hostname, port=port, username=username, password=password, timeout=30)
        connected = True
    except Exception as e:
        print(f"Connection failed: {e}")
        sys.exit(1)

sftp = ssh.open_sftp()

def get_sha256(filepath):
    h = hashlib.sha256()
    try:
        with open(filepath, 'rb') as f:
            while True:
                chunk = f.read(65536)
                if not chunk:
                    break
                h.update(chunk)
        return h.hexdigest()
    except Exception:
        return None

exclude_dirs = {".git", "vendor"}
exclude_files = {".env", "scratch/id_quete", "scratch/id_quete.pub"}

different_files = []
missing_remote = []
identical_files = []

for root, dirs, files in os.walk(local_root):
    dirs[:] = [d for d in dirs if d not in exclude_dirs]
    for file in files:
        local_path = os.path.join(root, file)
        rel_path = os.path.relpath(local_path, local_root).replace("\\", "/")
        
        if rel_path in exclude_files or any(rel_path.startswith(d + "/") for d in exclude_dirs):
            continue
            
        local_sha = get_sha256(local_path)
        if local_sha is None:
            continue
            
        remote_path = f"{remote_root}/{rel_path}"
        try:
            remote_stat = sftp.stat(remote_path)
            # Fetch remote file content to calculate hash
            with sftp.open(remote_path, "rb") as rf:
                remote_content = rf.read()
            remote_sha = hashlib.sha256(remote_content).hexdigest()
            
            if local_sha != remote_sha:
                different_files.append((rel_path, local_sha[:8], remote_sha[:8]))
            else:
                identical_files.append(rel_path)
        except IOError:
            missing_remote.append(rel_path)

sftp.close()
ssh.close()

print(f"\n--- Scan completed. Total identical files: {len(identical_files)} ---")
if different_files:
    print("\nDifferent files (Local vs Remote):")
    for rel_path, l_sha, r_sha in different_files:
        print(f"  - {rel_path} (Local: {l_sha}, Remote: {r_sha})")
else:
    print("\nNo different files found.")

if missing_remote:
    print("\nMissing on Remote VPS:")
    for rel_path in missing_remote:
         print(f"  - {rel_path}")
else:
    print("\nNo missing files on Remote VPS.")
