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

local_root = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity-git"
remote_root = "/var/www/quete"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    ssh.connect(hostname, port=port, username=username, password=password, timeout=30)
    print("Connected successfully to VPS!")
except Exception as e:
    print(f"Connection failed: {e}")
    sys.exit(1)

sftp = ssh.open_sftp()

files_to_check = [
    "COMPLETED_WORK.md",
    "GENERAL.md",
    "config.php",
    "core/db.php",
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
    "assets/js/websocket-client.js",
    "core/ai_handler.php",
    "admin.php",
    "websocket/GameWebSocket.php",
    "core/auth_handler.php",
    "login.php",
    "views/footer.php",
    "README.md",
    "views/header.php",
    "index.php",
    "hub.php"
]

def get_sha256(filepath):
    h = hashlib.sha256()
    with open(filepath, 'rb') as f:
        while True:
            chunk = f.read(65536)
            if not chunk:
                break
            h.update(chunk)
    return h.hexdigest()

print(f"\nComparing local files with remote ones in {remote_root}...")
print(f"{'File Path':<40} | {'Status':<15} | {'Details'}")
print("-" * 80)

diff_files = []

for file_rel in files_to_check:
    local_path = os.path.join(local_root, file_rel.replace("/", "\\"))
    remote_path = f"{remote_root}/{file_rel}"
    
    if not os.path.exists(local_path):
        print(f"{file_rel:<40} | {'LOCAL MISSING':<15} | -")
        continue
        
    local_sha = get_sha256(local_path)
    
    try:
        remote_file = sftp.open(remote_path, "r")
        remote_content = remote_file.read()
        remote_sha = hashlib.sha256(remote_content).hexdigest()
        remote_file.close()
        
        if local_sha != remote_sha:
            print(f"{file_rel:<40} | {'DIFFERENT':<15} | Local sha: {local_sha[:8]}, Remote: {remote_sha[:8]}")
            diff_files.append(file_rel)
        else:
            print(f"{file_rel:<40} | {'IDENTICAL':<15} | -")
    except IOError:
        print(f"{file_rel:<40} | {'REMOTE MISSING':<15} | -")
        diff_files.append(file_rel)

sftp.close()
ssh.close()

print("\nFiles with differences:")
for f in diff_files:
    print(f"  - {f}")
