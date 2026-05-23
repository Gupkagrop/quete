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

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(hostname, port=port, username=username, password=password, timeout=30)

def run_cmd(cmd):
    print(f"\n=== Running: {cmd} ===")
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode('utf-8', errors='ignore')
    err = stderr.read().decode('utf-8', errors='ignore')
    if out:
        print("[OUT]:", out.strip())
    if err:
        print("[ERR]:", err.strip())
    return out, err

# 1. Allow port 8888/tcp in UFW
run_cmd("ufw allow 8888/tcp")

# 2. Reload UFW
run_cmd("ufw reload")

# 3. Check UFW status
run_cmd("ufw status")

ssh.close()
