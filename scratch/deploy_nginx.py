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
key_path = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity-git\scratch\id_quete"

local_config = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity-git\scratch\quete_nginx.conf"
remote_config = "/etc/nginx/sites-available/quete"

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
        print("[OUT]:", out.strip())
    if err:
        print("[ERR]:", err.strip())
    return out, err

# Open SFTP session
sftp = ssh.open_sftp()

print("--- 1. Backing up original Nginx config on VPS ---")
try:
    sftp.stat(remote_config)
    run_cmd(f"cp {remote_config} {remote_config}.bak")
    print("Backup created successfully!")
except Exception as e:
    print(f"No backup created: {e}")

print("--- 2. Uploading new Nginx config to VPS ---")
try:
    print(f"Uploading {local_config} -> {remote_config}")
    sftp.put(local_config, remote_config)
    print("Upload completed successfully!")
except Exception as e:
    print(f"Upload failed: {e}")
    sys.exit(1)

# Close SFTP
sftp.close()

print("--- 3. Testing Nginx Configuration ---")
out, err = run_cmd("nginx -t")
if "test is successful" in out or "test is successful" in err:
    print("Nginx configuration test passed! Reloading Nginx...")
    run_cmd("systemctl reload nginx")
    print("Nginx reloaded successfully!")
else:
    print("[ERROR] Nginx configuration test failed! Restoring backup...")
    run_cmd(f"mv {remote_config}.bak {remote_config}")
    run_cmd("systemctl reload nginx")
    sys.exit(1)

ssh.close()
print("Nginx deployment completed successfully!")
