#!/usr/bin/env bash
# Run once on prod server as dev_back_p_8_usr (FastPanel SSH).
# Creates deploy key for GitHub Actions and prints base64 for secret SSH_PRIVATE_KEY.
set -euo pipefail

KEY="${HOME}/.ssh/github_actions_deploy"

if [ ! -f "$KEY" ]; then
  ssh-keygen -t ed25519 -f "$KEY" -N "" -C "github-actions-prod-deploy"
fi

PUB=$(cat "${KEY}.pub")
if ! grep -qF "$PUB" "${HOME}/.ssh/authorized_keys" 2>/dev/null; then
  echo "$PUB" >> "${HOME}/.ssh/authorized_keys"
  chmod 600 "${HOME}/.ssh/authorized_keys"
fi

echo ""
echo "=== GitHub: Settings → Secrets → Actions → SSH_PRIVATE_KEY ==="
echo "Option A (recommended): base64 one line:"
base64 -w0 "$KEY"
echo ""
echo ""
echo "Option B: paste full private key PEM (BEGIN OPENSSH PRIVATE KEY)"
echo "Fingerprint: $(ssh-keygen -lf "${KEY}.pub")"
