<#
  deploy.ps1  —  Deploy the xplace-dashboard changes to the live droplet.

  Run from YOUR OWN terminal (where SSH works interactively):
      powershell -ExecutionPolicy Bypass -File .\deploy.ps1

  Options:
      -ServerIP 167.99.130.154      skip auto-detect, use this droplet
      -User root                    SSH user (default: root)
      -RemoteDir /var/www/xplace-dashboard
      -KeyPath C:\Users\micha\.ssh\id_rsa   use a specific private key
      -UseGit                       deploy with git instead of copying files

  What it does:
    1. Probes your known droplet IPs and prints a status for each
       (HIT = this is the dashboard server, AUTH_FAILED = key rejected, etc.).
    2. Backs up the 4 target files on the server (timestamped folder).
    3. Deploys the 4 changed files via scp (default), or git with -UseGit.
    4. Verifies the new code landed.
  Nothing is deleted. The backup folder lets you roll back instantly.
#>

param(
  [string]$ServerIP  = "",
  [string]$User      = "root",
  [string]$RemoteDir = "/var/www/xplace-dashboard",
  [string]$KeyPath   = "",
  [switch]$UseGit
)

# Do NOT stop on ssh stderr — we handle failures per-host.
$ErrorActionPreference = "Continue"
$repo  = Split-Path -Parent $MyInvocation.MyCommand.Path
$files = @("index.php", "action.php", "api/get_proposal_requests.php", "api/delete_proposal.php")
$candidates = @("46.101.85.13", "167.99.130.154", "161.35.78.39", "164.90.223.113")

$keyOpt = ""
if ($KeyPath) { $keyOpt = "-i `"$KeyPath`"" }

# Run an ssh command through cmd so PowerShell never treats stderr as fatal.
function SshCapture([string]$ip, [string]$remoteCmd) {
  $c = "ssh $keyOpt -o BatchMode=yes -o ConnectTimeout=8 -o StrictHostKeyChecking=accept-new -o PreferredAuthentications=publickey $User@$ip ""$remoteCmd"" 2>&1"
  return (cmd /c $c | Out-String)
}

function Probe([string]$ip) {
  $out = SshCapture $ip "test -f $RemoteDir/index.php && echo HIT || echo NODASH"
  if ($out -match "HIT")               { return "HIT  (dashboard server)" }
  if ($out -match "NODASH")            { return "auth OK, but no dashboard here" }
  if ($out -match "Permission denied") { return "AUTH_FAILED (key rejected)" }
  if ($out -match "timed out")         { return "unreachable (timeout)" }
  if ($out -match "refused")           { return "connection refused" }
  return "unknown: " + ($out.Trim())
}

# 1) Resolve the server -------------------------------------------------
if (-not $ServerIP) {
  Write-Host "Probing your known droplets for the dashboard..." -ForegroundColor Cyan
  foreach ($ip in $candidates) {
    $status = Probe $ip
    $color = if ($status -like "HIT*") { "Green" } elseif ($status -like "AUTH_FAILED*") { "Yellow" } else { "Gray" }
    Write-Host ("  {0,-16} {1}" -f $ip, $status) -ForegroundColor $color
    if ($status -like "HIT*") { $ServerIP = $ip }
  }
  if (-not $ServerIP) {
    Write-Host ""
    Write-Host "No droplet matched. Options:" -ForegroundColor Red
    Write-Host "  - If you know the IP:    .\deploy.ps1 -ServerIP <ip>" -ForegroundColor Yellow
    Write-Host "  - If the key was rejected everywhere, point to the right key:" -ForegroundColor Yellow
    Write-Host "      .\deploy.ps1 -ServerIP <ip> -KeyPath C:\Users\micha\.ssh\id_rsa" -ForegroundColor Yellow
    Write-Host "  - Different SSH user:    .\deploy.ps1 -ServerIP <ip> -User <user>" -ForegroundColor Yellow
    exit 1
  }
}
Write-Host ""
Write-Host "Target:  $User@$ServerIP : $RemoteDir" -ForegroundColor Green

$confirm = Read-Host "Deploy now? (y/n)"
if ($confirm -ne "y") { Write-Host "Aborted."; exit 0 }

# Build the ssh/scp prefix (interactive auth allowed from here on).
$sshBase = "ssh $keyOpt -o StrictHostKeyChecking=accept-new $User@$ServerIP"

# 2) Backup current files on the server --------------------------------
$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
Write-Host "Backing up current files -> $RemoteDir/_backup_$stamp" -ForegroundColor Cyan
cmd /c "$sshBase ""mkdir -p $RemoteDir/_backup_$stamp; cd $RemoteDir; for f in index.php action.php api/get_proposal_requests.php api/delete_proposal.php; do [ -f \$f ] && cp \$f _backup_$stamp/\$(echo \$f | tr / _); done; echo backup-done"""

# 3) Deploy ------------------------------------------------------------
if ($UseGit) {
  Write-Host "Deploying with git..." -ForegroundColor Cyan
  cmd /c "$sshBase ""cd $RemoteDir; git fetch origin; git checkout -f origin/main -- index.php action.php api/get_proposal_requests.php api/delete_proposal.php; echo git-deploy-done"""
} else {
  Write-Host "Copying files via scp..." -ForegroundColor Cyan
  foreach ($f in $files) {
    $local  = Join-Path $repo ($f -replace "/", "\")
    $remote = "${User}@${ServerIP}:${RemoteDir}/$f"
    Write-Host "  -> $f"
    cmd /c "scp $keyOpt -o StrictHostKeyChecking=accept-new ""$local"" ""$remote"""
  }
}

# 4) Verify ------------------------------------------------------------
Write-Host "Verifying new code on the server..." -ForegroundColor Cyan
cmd /c "$sshBase ""echo -n 'index.php btn-newcontent: '; grep -c btn-newcontent $RemoteDir/index.php; echo -n 'action.php notes-coalesce: '; grep -c 'COALESCE(NULLIF' $RemoteDir/action.php; echo -n 'get_proposal_requests cols: '; grep -c 'agent_notes, notes, proposal_text' $RemoteDir/api/get_proposal_requests.php"""

Write-Host ""
Write-Host "Done. Roll back any time from: $RemoteDir/_backup_$stamp" -ForegroundColor Green
Write-Host "Hard-refresh the dashboard (Ctrl+F5) to see the new buttons." -ForegroundColor Green
