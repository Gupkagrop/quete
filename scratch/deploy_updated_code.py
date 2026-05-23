import paramiko
import os
import re
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

# List of files to upload based on recent modifications since 17:00 today
# (Excluding core/ai_handler.php in accordance with the rule to avoid modifying core/ai_handler.php unless requested)
files_to_upload = [
    "COMPLETED_WORK.md",
    "GENERAL.md",
    "config.php",
    "core/db.php",
    "core/ai_handler.php",
    "solo.php",
    "lobby.php",
    "game.php",
    "test_ai.php",
    "views/user_stats.php",
    "ajax/get_lobby_update.php",
    "ajax/award_points.php",
    "ajax/finalize_round.php",
    "ajax/activate_next.php",
    "ajax/select_topic.php",
    "ajax/game_state_update.php",
    "assets/css/game.css",
    "assets/css/auth.css",
    "assets/css/style.css",
    "assets/js/websocket-client.js"
]

print("--- 1. Uploading updated files to VPS ---")
for file_rel in files_to_upload:
    local_path = os.path.join(local_root, file_rel.replace("/", "\\"))
    remote_path = f"{remote_root}/{file_rel}"
    
    # Ensure remote directory exists
    remote_dir = os.path.dirname(remote_path)
    remote_mkdir_p(remote_dir)
    
    print(f"Uploading {local_path} -> {remote_path}")
    sftp.put(local_path, remote_path)
print("Files successfully uploaded!")

# 2. Check and update .env on VPS (keep WS_CLIENT_PORT=8888)
print("--- 2. Updating .env on VPS with WS_CLIENT_PORT ---")
try:
    env_path = f"{remote_root}/.env"
    env_content = ""
    try:
        with sftp.open(env_path, "r") as f:
            env_content = f.read().decode('utf-8')
    except IOError:
        print(".env not found on server, attempting to read .env.example")
        with sftp.open(f"{remote_root}/.env.example", "r") as f:
            env_content = f.read().decode('utf-8')

    # Add or update WS_CLIENT_PORT
    if "WS_CLIENT_PORT=" not in env_content:
        env_content += "\n# Внешний порт WebSocket клиента для продакшена\nWS_CLIENT_PORT=8888\n"
    else:
        env_content = re.sub(r"WS_CLIENT_PORT=\d+", "WS_CLIENT_PORT=8888", env_content)

    # Save updated .env
    with sftp.open(env_path, "w") as f:
        f.write(env_content)
    print("Successfully updated .env on VPS!")
except Exception as e:
    print(f"Error updating .env: {e}")

# Close SFTP
sftp.close()

# 3. Restart services
print("--- 3. Restarting Services on VPS ---")
run_cmd("systemctl daemon-reload")
run_cmd("systemctl restart quete-websocket")
run_cmd("systemctl restart nginx")

# 4. Check Statuses
print("--- 4. Checking status of quete-websocket ---")
run_cmd("systemctl status quete-websocket --no-pager -n 20")

print("--- 5. Checking status of nginx ---")
run_cmd("systemctl status nginx --no-pager -n 20")

ssh.close()
print("Deployment completed successfully!")
