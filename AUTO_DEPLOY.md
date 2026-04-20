# Auto-deploy setup (GitHub → IONOS)

One-time setup so every push to `main` redeploys `events.sherwoodadventure.com`.

## How it works

```
git push origin main
       │
       ▼
GitHub Actions  ──(ssh)──▶  IONOS server  ──▶  runs ./deploy.sh
                                             ├─ git pull origin main
                                             ├─ fix permissions
                                             └─ done
```

Two SSH keys are involved (these are **different keys**):

| Key | Where | Purpose |
| --- | --- | --- |
| **Deploy key** (per repo) | Private on IONOS, public in GitHub → Settings → Deploy Keys | Lets the IONOS server pull from GitHub |
| **CI key** (one per IONOS account, reusable across all Sherwood projects) | Public in IONOS `~/.ssh/authorized_keys`, private as a GitHub Actions secret | Lets GitHub Actions ssh into IONOS to kick off the deploy |

---

## Step 1 — On the IONOS server

SSH in as you normally do, then:

```bash
# Pick the folder for this site (sibling to sherwood_web)
cd /kunden/homepages/40/d493077416/htdocs    # replace with your actual path
mkdir sherwood_events
cd sherwood_events

# Create a GitHub deploy key for this repo (if you haven't already)
ssh-keygen -t ed25519 -f ~/.ssh/sherwood_events_deploy -N "" -C "sherwood_events deploy"
cat ~/.ssh/sherwood_events_deploy.pub
# ↑ copy that output, paste into:
#   github.com/jeffveg/sherwood_events → Settings → Deploy keys → Add deploy key
#   (read-only is fine; no need to check "allow write access")

# Tell SSH to use that key for this repo
cat >> ~/.ssh/config <<'EOF'

Host github-sherwood-events
    HostName github.com
    User git
    IdentityFile ~/.ssh/sherwood_events_deploy
    IdentitiesOnly yes
EOF

# Clone using that host alias
git clone git@github-sherwood-events:jeffveg/sherwood_events.git .
chmod +x deploy.sh

# Adjust SITE_DIR at the top of deploy.sh to match your path, then:
./deploy.sh        # runs it once to verify
```

Point the `events.sherwoodadventure.com` subdomain document root at
`<this-folder>/public/` in the IONOS panel.

---

## Step 2 — Create the CI key (once, reusable)

**If you already have a CI key you reuse for GitHub Actions → IONOS, skip this step
and just reuse it.** Otherwise, on your workstation (not the server):

```bash
ssh-keygen -t ed25519 -f ~/.ssh/ionos_github_actions -N "" -C "github-actions@ionos"
```

You now have:
- `~/.ssh/ionos_github_actions`     — **private** (GitHub Actions secret)
- `~/.ssh/ionos_github_actions.pub` — **public** (add to IONOS)

On the IONOS server, append the **public** half to your authorized_keys:

```bash
# Paste the contents of ionos_github_actions.pub at the end
nano ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Verify it works from your workstation:

```bash
ssh -i ~/.ssh/ionos_github_actions <your-ionos-user>@<your-ionos-ssh-host>
```

You should log in without a password prompt.

---

## Step 3 — Add GitHub secrets

Go to **github.com/jeffveg/sherwood_events → Settings → Secrets and variables → Actions → New repository secret**.

Add these four:

| Secret name | Value |
| --- | --- |
| `IONOS_SSH_HOST` | Your IONOS SSH hostname, e.g. `home12345678.1and1-data.host` (find under IONOS → Hosting → SSH/SFTP access) |
| `IONOS_SSH_USER` | Your IONOS SSH user, e.g. `u123456789` |
| `IONOS_SSH_PORT` | Usually `22` (omit if 22) |
| `IONOS_SSH_KEY`  | **Entire contents** of `~/.ssh/ionos_github_actions` (private key, including the BEGIN/END lines) |
| `IONOS_SITE_DIR` | Absolute path on the server, e.g. `/kunden/homepages/40/d493077416/htdocs/sherwood_events` |

---

## Step 4 — Test

From the Actions tab of the GitHub repo, pick **Deploy to IONOS** and click **Run workflow**. Watch the log. You should see the output of `deploy.sh` ending with `==> Done! Site is live.`

After that, every push to `main` deploys automatically.

If the workflow fails:

- **Permission denied (publickey)** → the CI key's public half isn't in `~/.ssh/authorized_keys`, or file permissions on it are wrong (`chmod 600 ~/.ssh/authorized_keys`, `chmod 700 ~/.ssh`).
- **Host key verification failed** → usually not an issue with `appleboy/ssh-action`, but if it happens, ssh in manually once from your workstation with the CI key first so the known_hosts gets populated.
- **`deploy.sh: command not found` or similar** → `IONOS_SITE_DIR` is wrong, or `deploy.sh` isn't executable. Fix with `chmod +x deploy.sh` on the server.

---

## Skipping auto-deploy for a particular commit

Include `[skip ci]` anywhere in the commit message:

```
git commit -m "Update README [skip ci]"
```
