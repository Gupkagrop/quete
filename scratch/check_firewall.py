import paramiko
import os
import sys

hostname = "155.212.165.20"
port = 22
username = "root"
password = "_XrYd2K_GNgv2uK"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(hostname, port=port, username=username, password=password)

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

run_cmd("ufw status")
run_cmd("iptables -L -n -v | grep 8888")
run_cmd("curl -I https://quete.ru:8888/") # Let's see if Nginx responds to HTTPS on port 8888 locally from the server

ssh.close()
