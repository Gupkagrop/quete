import paramiko
import sys
import difflib

# Configure stdout to use UTF-8
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

hostname = "155.212.165.20"
port = 22
username = "root"
password = "_XrYd2K_GNgv2uK"

local_path = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity\core\ai_handler.php"
remote_path = "/var/www/quete/core/ai_handler.php"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    ssh.connect(hostname, port=port, username=username, password=password, timeout=30)
except Exception as e:
    print(f"Connection failed: {e}")
    sys.exit(1)

sftp = ssh.open_sftp()

try:
    with open(local_path, 'r', encoding='utf-8') as f:
        local_lines = f.readlines()
        
    with sftp.open(remote_path, 'r') as f:
        raw_lines = f.readlines()
        remote_lines = []
        for line in raw_lines:
            if isinstance(line, bytes):
                remote_lines.append(line.decode('utf-8', errors='ignore'))
            else:
                remote_lines.append(str(line))
        
    diff = difflib.unified_diff(remote_lines, local_lines, fromfile='remote/ai_handler.php', tofile='local/ai_handler.php')
    print("Diff for core/ai_handler.php:")
    for line in diff:
        print(line, end='')
except Exception as e:
    print(f"Error diffing: {e}")

sftp.close()
ssh.close()
