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
        print("Failed to connect:", e)
        sys.exit(1)

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

# 1. Check Nginx error logs
run_cmd("tail -n 20 /var/log/nginx/error.log")

# 2. Check WebSocket systemd logs
run_cmd("journalctl -u quete-websocket.service -n 20 --no-pager")

# 3. Check what is listening on port 8080 and 8888
run_cmd("ss -tulpn | grep -E '8080|8888'")

# 4. Try connecting directly to localhost:8080 using curl to simulate handshake
run_cmd("curl -i -N -H \"Connection: Upgrade\" -H \"Upgrade: websocket\" -H \"Host: localhost\" -H \"Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\" -H \"Sec-WebSocket-Version: 13\" http://127.0.0.1:8080/")

ssh.close()
