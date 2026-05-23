import paramiko
import os
import sys

# Configure stdout to use UTF-8 to prevent encoding issues with Unicode symbols
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

hostname = "155.212.165.20"
port = 22
username = "root"
password = "_XrYd2K_GNgv2uK"
key_path = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity\scratch\id_quete"

local_root = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity"
remote_root = "/var/www/quete"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

connected = False

# Try key-based auth first
if os.path.exists(key_path):
    print("Trying to connect to the VPS via SSH key...")
    try:
        ssh.connect(hostname, port=port, username=username, key_filename=key_path, timeout=30)
        print("Connected successfully via SSH Key!")
        connected = True
    except Exception as e:
        print(f"Key-based authentication failed or timed out: {e}")

# Fallback to password auth
if not connected:
    print("Trying to connect to the VPS via Password...")
    try:
        ssh.connect(hostname, port=port, username=username, password=password, timeout=30)
        print("Connected successfully via Password!")
        connected = True
    except Exception as e:
        print(f"Password-based authentication failed or timed out: {e}")
        sys.exit(1)

def run_cmd(cmd):
    print(f"Running command: {cmd}")
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode('utf-8', errors='ignore')
    err = stderr.read().decode('utf-8', errors='ignore')
    if out:
        safe_out = out.strip().encode(sys.stdout.encoding, errors='replace').decode(sys.stdout.encoding, errors='replace')
        print("[OUT]:", safe_out)
    if err:
        safe_err = err.strip().encode(sys.stdout.encoding, errors='replace').decode(sys.stdout.encoding, errors='replace')
        print("[ERR]:", safe_err)
    return out, err

# Open SFTP session
sftp = ssh.open_sftp()

# Helper to ensure remote directory exists
def remote_mkdir_p(remote_directory):
    parts = remote_directory.split('/')
    current = ""
    for part in parts:
        if not part:
            continue
        current += f"/{part}"
        try:
            sftp.stat(current)
        except IOError:
            print(f"Creating remote directory: {current}")
            sftp.mkdir(current)

print("--- 1. Uploading optimized images to VPS ---")
local_img_dir = os.path.join(local_root, "assets", "img")
remote_img_dir = f"{remote_root}/assets/img"

# Ensure remote assets/img directory exists
remote_mkdir_p(remote_img_dir)

# List local images
local_files = [f for f in os.listdir(local_img_dir) if os.path.isfile(os.path.join(local_img_dir, f))]
img_extensions = ['.png', '.jpg', '.jpeg', '.gif', '.ico']

uploaded_count = 0
for file in local_files:
    ext = os.path.splitext(file)[1].lower()
    if ext not in img_extensions:
        continue
    
    local_path = os.path.join(local_img_dir, file)
    remote_path = f"{remote_img_dir}/{file}"
    
    orig_size = os.path.getsize(local_path)
    
    print(f"Uploading {file} ({orig_size / 1024:.2f} KB) -> {remote_path}")
    sftp.put(local_path, remote_path)
    uploaded_count += 1

print(f"Successfully uploaded {uploaded_count} optimized images!")

# Close SFTP
sftp.close()

# 2. Fix permissions on remote server
print("--- 2. Setting proper permissions for remote assets/img ---")
run_cmd(f"chown -R www-data:www-data {remote_img_dir}")
run_cmd(f"chmod -R 755 {remote_img_dir}")

# 3. Clear Nginx cache or restart Nginx just in case
print("--- 3. Restarting Nginx to clear cache / load new files ---")
run_cmd("systemctl restart nginx")

ssh.close()
print("Deployment of optimized images completed successfully!")
