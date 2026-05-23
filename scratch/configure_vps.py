import paramiko
import os
import re
import sys

# Configure stdout to use UTF-8 to prevent encoding issues with Unicode symbols (e.g., ● in systemctl output)
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

hostname = "155.212.165.20"
port = 22
username = "root"
password = "_XrYd2K_GNgv2uK"
key_path = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity\scratch\id_quete"
pub_key = "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAaE7MrZzENTGiQQHnUSDthHHOR+UtahHltCEYJLMNKZ denis@Gupkagrop"

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
        # Safely print by replacing characters that cannot be encoded in the terminal's active codepage
        safe_out = out.strip().encode(sys.stdout.encoding, errors='replace').decode(sys.stdout.encoding, errors='replace')
        print("[OUT]:", safe_out)
    if err:
        safe_err = err.strip().encode(sys.stdout.encoding, errors='replace').decode(sys.stdout.encoding, errors='replace')
        print("[ERR]:", safe_err)
    return out, err

# 1. Install SSH public key if not already done
print("--- 1. Installing SSH Public Key ---")
setup_key_cmd = f'mkdir -p /root/.ssh && grep -qF "{pub_key}" /root/.ssh/authorized_keys 2>/dev/null || echo "{pub_key}" >> /root/.ssh/authorized_keys && chmod 700 /root/.ssh && chmod 600 /root/.ssh/authorized_keys'
run_cmd(setup_key_cmd)

# 2. Disable default nginx site if active
print("--- 2. Disabling default Nginx site ---")
run_cmd("rm -f /etc/nginx/sites-enabled/default")

# 3. Configure /etc/nginx/sites-available/quete
print("--- 3. Configuring Nginx for quete ---")
nginx_config = """# 1. Основной сайт игры на HTTPS (порт 443)
server {
    listen 443 ssl http2;
    server_name quete.ru www.quete.ru;

    root /var/www/quete;
    index index.php index.html index.htm;

    # SSL-сертификаты Let's Encrypt
    ssl_certificate /etc/letsencrypt/live/quete.ru/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/quete.ru/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    # Настройки отдачи файлов игры
    location / {
        try_files $uri $uri/ =404;
    }

    # Защита скрытых системных файлов (.env, .git)
    location ~ /\.(?!well-known) {
        deny all;
    }

    # Обработка PHP через PHP 8.3-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# 2. Безопасное SSL-проксирование WebSockets на порту 8888 (WSS)
server {
    listen 8888 ssl;
    server_name quete.ru www.quete.ru;

    ssl_certificate /etc/letsencrypt/live/quete.ru/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/quete.ru/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_read_timeout 86400; # Соединение не разорвется 24 часа
    }
}

# 3. Автоматический редирект с небезопасного HTTP (порт 80) на HTTPS
server {
    listen 80;
    server_name quete.ru www.quete.ru;

    if ($host = www.quete.ru) {
        return 301 https://$host$request_uri;
    }
    if ($host = quete.ru) {
        return 301 https://$host$request_uri;
    }
    return 404;
}
"""

# Write Nginx config
sftp = ssh.open_sftp()
with sftp.open("/etc/nginx/sites-available/quete", "w") as f:
    f.write(nginx_config)
print("Wrote Nginx config to /etc/nginx/sites-available/quete")

# Enable Nginx config if not enabled
run_cmd("ln -sf /etc/nginx/sites-available/quete /etc/nginx/sites-enabled/quete")

# Stop WebSocket daemon and kill any processes on port 8888/tcp BEFORE starting Nginx
print("--- 3.5 Freeing port 8888 and preparing Nginx ---")
run_cmd("systemctl stop quete-websocket")
run_cmd("fuser -k 8888/tcp")
run_cmd("fuser -k 8080/tcp")

# Test and reload Nginx
run_cmd("nginx -t")
out_nginx, err_nginx = run_cmd("systemctl restart nginx")

# If Nginx failed, let's print debug information
if "failed" in err_nginx.lower() or "failed" in out_nginx.lower():
    print("Nginx restart failed! Checking status...")
    run_cmd("systemctl status nginx.service -n 50")
    run_cmd("journalctl -xeu nginx.service -n 50")

# 4. Modify config.php on the server
print("--- 4. Updating config.php ---")
try:
    with sftp.open("/var/www/quete/config.php", "r") as f:
        config_content = f.read().decode('utf-8')
    
    # Replace define('WS_PORT', 8888);
    # and define('WS_HOST', '0.0.0.0');
    pattern_port = r"define\('WS_PORT',\s*8888\);"
    pattern_host = r"define\('WS_HOST',\s*'0\.0\.0\.0'\);"
    
    new_config = re.sub(pattern_port, "define('WS_PORT', getenv('WS_PORT') !== false ? (int)getenv('WS_PORT') : 8888);", config_content)
    new_config = re.sub(pattern_host, "define('WS_HOST', getenv('WS_HOST') ?: '0.0.0.0');", new_config)
    
    with sftp.open("/var/www/quete/config.php", "w") as f:
        f.write(new_config)
    print("Successfully updated /var/www/quete/config.php")
except Exception as e:
    print("Could not update config.php:", e)

# 5. Update /var/www/quete/.env
print("--- 5. Updating .env ---")
try:
    env_content = ""
    try:
        with sftp.open("/var/www/quete/.env", "r") as f:
            env_content = f.read().decode('utf-8')
    except IOError:
        print(".env not found, checking .env.example")
        with sftp.open("/var/www/quete/.env.example", "r") as f:
            env_content = f.read().decode('utf-8')
            
    # Check if WS_PORT and WS_HOST exist, if not, append them
    if "WS_PORT=" not in env_content:
        env_content += "\n# Внутренние настройки WebSocket (для обхода конфликта портов)\nWS_PORT=8080\nWS_HOST=127.0.0.1\n"
    else:
        # Update them if they exist
        env_content = re.sub(r"WS_PORT=\d+", "WS_PORT=8080", env_content)
        env_content = re.sub(r"WS_HOST=[\d\.]+", "WS_HOST=127.0.0.1", env_content)
        
    with sftp.open("/var/www/quete/.env", "w") as f:
        f.write(env_content)
    print("Successfully updated /var/www/quete/.env")
except Exception as e:
    print("Could not update .env:", e)

# 6. Restart WebSocket service
print("--- 6. Restarting WebSocket Service ---")
run_cmd("systemctl daemon-reload")
run_cmd("systemctl restart quete-websocket")
run_cmd("systemctl status quete-websocket")

sftp.close()
ssh.close()
print("All tasks completed successfully!")
