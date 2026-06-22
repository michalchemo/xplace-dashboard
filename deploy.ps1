<#
  deploy.ps1  —  Deploy the xplace-dashboard changes to the live droplet.

  Run from YOUR OWN terminal (where SSH works interactively):
      powershell -ExecutionPolicy Bypass -File .\deploy.ps1

  Options:
      -ServerIP 167.99.130.154      skip auto-detect, use this droplet
      -User root                    SSH user (default: root)
      -RemoteDir /var/www/xplace-dashboard
      -UseGit                       deploy with "git pull" instead of copying files

  What it does:
    1. Finds the droplet that hosts /var/www/xplace-dashboard (probes your known IPs).
    2. Backs up the 4 target files on the server (timestamped folder).
    3. Deploys: copies the 4 changed files via scp (default), or git pull with -UseGit.
    4. Verifies the new code landed.
  Nothing is deleted. The backup folder lets you roll back instantly.
#>

param(
  [string]$ServerIP  = "",
  [string]$User      = "root",
  [string]$RemoteDir = "/var/www/xplace-dashboard",
  [switch]$UseGit
)

$ErrorActionPreference = "Stop"
$repo  = Split-Path -Parent $MyInvocation.MyCommand.Path
$files = @("index.php", "action.php", "api/get_proposal_requests.php", "api/delete_proposal.php")
$candidates = @("46.101.85.13", "167.99.130.154", "161.35.78.39", "164.90.223.113")

function Probe([string]$ip) {
  $r = ssh -o BatchMode=yes -o ConnectTimeout=8 -o StrictHostKeyChecking=accept-new "$User@$ip" "test -f $RemoteDir/index.php && echo HIT" 2>$null
  return ($r -match "HIT")
}

# 1) Resolve the server -------------------------------------------------
if (-not $ServerIP) {
  Write-Host "Auto-detecting the dashboard droplet..." -ForegroundColor Cyan
  foreach ($ip in $candidates) {
    Write-Host "  probing $ip ..."
    if (Probe $ip) { $ServerIP = $ip; break }
  }
  if (-not $ServerIP) {
    Write-Host "Could not auto-detect the server." -ForegroundColor Red
    Write-Host "Re-run with the right IP, e.g.:  .\deploy.ps1 -ServerIP <ip>" -ForegroundColor Yellow
    exit 1
  }
}
Write-Host "Target:  $User@$ServerIP : $RemoteDir" -ForegroundColor Green

$confirm = Read-Host "Deploy now? (y/n)"
if ($confirm -ne "y") { Write-Host "Aborted."; exit 0 }

# 2) Backup current files on the server --------------------------------
$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
Write-Host "Backing up current files -> $RemoteDir/_backup_$stamp" -ForegroundColor Cyan
ssh "$User@$ServerIP" "mkdir -p $RemoteDir/_backup_$stamp; cd $RemoteDir; for f in index.php action.php api/get_proposal_requests.php api/delete_proposal.php; do [ -f `$f ] && cp `$f _backup_$stamp/`$(echo `$f | tr / _); done; echo 'backup done'"

# 3) Deploy ------------------------------------------------------------
if ($UseGit) {
  Write-Host "Deploying with git pull..." -ForegroundColor Cyan
  ssh "$User@$ServerIP" "cd $RemoteDir; git fetch origin; git checkout -f origin/main -- index.php action.php api/get_proposal_requests.php api/delete_proposal.php; echo 'git deploy done'"
} else {
  Write-Host "Copying files via scp..." -ForegroundColor Cyan
  foreach ($f in $files) {
    $local  = Join-Path $repo ($f -replace "/", "\")
    $remote = "${User}@${ServerIP}:${RemoteDir}/$f"
    Write-Host "  -> $f"
    scp -o StrictHostKeyChecking=accept-new "$local" "$remote"
  }
}

# 4) Verify ------------------------------------------------------------
Write-Host "Verifying new code on the server..." -ForegroundColor Cyan
ssh "$User@$ServerIP" "echo -n 'index.php btn-newcontent: '; grep -c 'btn-newcontent' $RemoteDir/index.php; echo -n 'action.php request_proposal notes: '; grep -c 'COALESCE(NULLIF' $RemoteDir/action.php; echo -n 'get_proposal_requests notes col: '; grep -c 'agent_notes, notes, proposal_text' $RemoteDir/api/get_proposal_requests.php"

Write-Host ""
Write-Host "Done. If anything looks wrong, restore from: $RemoteDir/_backup_$stamp" -ForegroundColor Green
Write-Host "Then hard-refresh the dashboard (Ctrl+F5)." -ForegroundColor Green
