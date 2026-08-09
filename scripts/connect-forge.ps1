<#
.SYNOPSIS
  Connect to the EIS Bridge Laravel Forge server via SSH from Windows.

.DESCRIPTION
  Opens an interactive SSH session to RonaldoMijaresServer001a (104.248.150.28).
  Accepts the server host key on first connect (StrictHostKeyChecking=accept-new)
  and optionally uses an SSH private key. Run without a key to connect via password
  if enabled, or use -SetupKey to generate a key and add it in Forge.

.PARAMETER Host
  Server IP or hostname. Default: 104.248.150.28

.PARAMETER User
  SSH user. Default: forge

.PARAMETER KeyPath
  Path to your private key file (passed to ssh -i). If omitted, common defaults
  under %USERPROFILE%\.ssh\ are tried; otherwise ssh runs without -i.

.PARAMETER Site
  Optional site hint: sandbox, api, or marketing. Prints the usual remote path
  before connecting (e.g. cd /home/forge/sandbox.eisbridge.com/api).

.PARAMETER SetupKey
  Run ssh-keygen interactively, then print the public key and Forge instructions.
  Does not open an SSH session.

.EXAMPLE
  .\scripts\connect-forge.ps1

.EXAMPLE
  .\scripts\connect-forge.ps1 -KeyPath "$env:USERPROFILE\.ssh\id_ed25519"

.EXAMPLE
  .\scripts\connect-forge.ps1 -Site sandbox

.EXAMPLE
  .\scripts\connect-forge.ps1 -SetupKey
#>

[CmdletBinding()]
param(
    [Alias('Host')]
    [string]$SshHost = '104.248.150.28',

    [string]$User = 'forge',

    [string]$KeyPath = '',

    [ValidateSet('sandbox', 'api', 'marketing', '')]
    [string]$Site = '',

    [switch]$SetupKey
)

$ErrorActionPreference = 'Stop'

$ServerName = 'RonaldoMijaresServer001a'
$SshDir = Join-Path $env:USERPROFILE '.ssh'
$DefaultKeyCandidates = @(
    (Join-Path $SshDir 'id_ed25519'),
    (Join-Path $SshDir 'id_rsa')
)

$SitePaths = @{
    sandbox   = '/home/forge/sandbox.eisbridge.com/api'
    api       = '/home/forge/api.eisbridge.com/api'
    marketing = '/home/forge/eisbridge.com'
}

function Show-ForgeKeyInstructions {
    param(
        [string]$PublicKeyPath = 'ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIE90Q9ajx8bqJat9NExX8dYTirEUrSF28WCZ3T+6fnbF',
        [string]$SuggestedPrivateKey = (Join-Path $SshDir 'id_ed25519')
    )

    Write-Host ''
    Write-Host '--- Add this key in Laravel Forge ---' -ForegroundColor Cyan
    Write-Host '  1. Forge -> Server (RonaldoMijaresServer001a) -> SSH Keys'
    Write-Host '  2. Paste the public key below (one line, starts with ssh-ed25519 or ssh-rsa)'
    Write-Host '  3. Save, then re-run this script with your private key:'
    Write-Host ('     .\scripts\connect-forge.ps1 -KeyPath "' + $SuggestedPrivateKey + '"') -ForegroundColor Yellow
    Write-Host ''

    if ($PublicKeyPath -and (Test-Path -LiteralPath $PublicKeyPath)) {
        Write-Host 'Public key:' -ForegroundColor Green
        Get-Content -LiteralPath $PublicKeyPath | Write-Host
        Write-Host ''
    }
}

function Show-SshKeyHelp {
    Write-Host ''
    Write-Host 'No SSH private key was used for this session.' -ForegroundColor Yellow
    Write-Host ''
    Write-Host 'To set up key-based login:' -ForegroundColor Cyan
    Write-Host '  1. Generate a key (interactive):'
    Write-Host '       .\scripts\connect-forge.ps1 -SetupKey'
    Write-Host '     Or manually:'
    Write-Host '       ssh-keygen -t ed25519 -C your-email@example.com -f $env:USERPROFILE\.ssh\id_ed25519'
    Write-Host '  2. Forge -> Server -> SSH Keys -> paste the .pub file contents'
    Write-Host '  3. Connect with the key:'
    Write-Host '       .\scripts\connect-forge.ps1 -KeyPath $env:USERPROFILE\.ssh\id_ed25519'
    Write-Host ''
}

function Ensure-SshAvailable {
    $ssh = Get-Command ssh -ErrorAction SilentlyContinue
    if (-not $ssh) {
        Write-Error @(
            'OpenSSH client not found. Install it: Settings -> Apps -> Optional Features -> OpenSSH Client,',
            'or run: Add-WindowsCapability -Online -Name OpenSSH.Client~~~~0.0.1.0'
        )
    }
}

function Invoke-SshKeySetup {
    Ensure-SshAvailable

    if (-not (Test-Path -LiteralPath $SshDir)) {
        New-Item -ItemType Directory -Path $SshDir -Force | Out-Null
        Write-Host "Created $SshDir"
    }

    $privateKey = Join-Path $SshDir 'id_ed25519'
    $publicKey = "$privateKey.pub"

    if (Test-Path -LiteralPath $privateKey) {
        Write-Host "Key already exists: $privateKey" -ForegroundColor Yellow
        $overwrite = Read-Host 'Generate a new key anyway? This overwrites the existing file (y/N)'
        if ($overwrite -notmatch '^[yY]') {
            Show-ForgeKeyInstructions -PublicKeyPath $publicKey -SuggestedPrivateKey $privateKey
            return
        }
    }

    Write-Host ''
    Write-Host 'Starting ssh-keygen (press Enter for defaults, set a passphrase if you want).' -ForegroundColor Cyan
    Write-Host ''

    & ssh-keygen -t ed25519 -f $privateKey

    if ($LASTEXITCODE -ne 0) {
        Write-Error 'ssh-keygen failed.'
    }

    Write-Host ''
    Write-Host 'Key generated successfully.' -ForegroundColor Green
    Show-ForgeKeyInstructions -PublicKeyPath $publicKey -SuggestedPrivateKey $privateKey
}

function Get-SshPrivateKeyPath {
    if ($KeyPath) {
        $resolved = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($KeyPath)
        if (-not (Test-Path -LiteralPath $resolved)) {
            Write-Host "Warning: Key file not found: $resolved" -ForegroundColor Yellow
            $answer = Read-Host 'Continue without -i (password or agent)? (Y/n)'
            if ($answer -match '^[nN]') {
                Write-Host 'Aborted. Generate a key with -SetupKey or fix -KeyPath.' -ForegroundColor Red
                exit 1
            }
            return $null
        }
        return $resolved
    }

    foreach ($candidate in $DefaultKeyCandidates) {
        if (Test-Path -LiteralPath $candidate) {
            Write-Host "Using default key: $candidate" -ForegroundColor DarkGray
            return $candidate
        }
    }

    return $null
}

# --- Main ---

Ensure-SshAvailable

if ($SetupKey) {
    Invoke-SshKeySetup
    exit 0
}

Write-Host ''
Write-Host 'EIS Bridge - Forge SSH' -ForegroundColor Cyan
Write-Host "  Server: $ServerName ($SshHost)"
Write-Host "  User:   $User"
Write-Host ''

if ($Site -and $SitePaths.ContainsKey($Site)) {
    $remotePath = $SitePaths[$Site]
    Write-Host "Site hint ($Site): after login, run:" -ForegroundColor DarkGray
    Write-Host "  cd $remotePath" -ForegroundColor Yellow
    Write-Host ''
}

$resolvedKey = Get-SshPrivateKeyPath
$usingKey = $null -ne $resolvedKey

if (-not $usingKey) {
    Show-SshKeyHelp
}

$sshArgs = @(
    '-o', 'StrictHostKeyChecking=accept-new',
    '-o', ''-o', "UserKnownHostsFile=$(Join-Path $SshDir 'known_hosts')"

)

if ($usingKey) {
    $sshArgs += @('-i', $resolvedKey)
}

$sshArgs += @("${User}@${SshHost}")

Write-Host 'Connecting (host key accepted automatically on first connect)...' -ForegroundColor Green
Write-Host ('  ssh ' + ($sshArgs -join ' ')) -ForegroundColor DarkGray
Write-Host ''

if (-not (Test-Path -LiteralPath $SshDir)) {
    New-Item -ItemType Directory -Path $SshDir -Force | Out-Null
}

& ssh @sshArgs
exit $LASTEXITCODE
