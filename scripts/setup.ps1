param(
    [Parameter(HelpMessage="URL where this instance of evertide will be hosted")]
    [string]$InstanceUrl,
    [Parameter(HelpMessage="Whether there are/will be multiple instances on this installation")]
    [nullable[bool]]$IsMultiInstance,
    [Parameter(HelpMessage="Force overwrite webmanifest and configuration files")]
    [switch]$Force,
    [Parameter(HelpMessage="Primary color for the instance")]
    [string]$PrimaryColor,
    [Parameter(HelpMessage="Secondary color for the instance")]
    [string]$SecondaryColor,
    [Parameter(HelpMessage="Display name for the instance")]
    [string]$DisplayName
)

. ($PSScriptRoot + "\shared\variables.ps1")

# Get instance information form user
If ($IsMultiInstance -eq $Null) { $isMultiInstance = ($Host.UI.PromptForChoice("🌊 evertide", "Are you running multiple instances?", ("&Yes", "&No"), 1)) -eq 0 }
Else { $isMultiInstance = $IsMultiInstance }
If ($instanceUrl -eq $Null -or $instanceUrl.Length -le 0) { $instanceUrl = Read-Host -Prompt ("Please enter the URL where$(If ($isMultiInstance) { " this instance of" } Else { '' }) evertide will be hosted") }
Else { $instanceUrl = $InstanceUrl }

# Parse domain out of URL
If (!$instanceUrl.StartsWith("http://") -and !$instanceUrl.StartsWith("https://")) {
    $instanceUrl = "http://" + $instanceUrl;
}
If (!$instanceUrl.EndsWith("/")) { $instanceUrl += "/" }
$domainStart = $instanceUrl.IndexOf("/") + 2
$domain = $instanceUrl.Substring($domainStart, $instanceUrl.IndexOf("/", 9) - $domainStart)

# Make a sade directory name and create directory if needed
$domainPath = $domain -replace '[\/<>:"\\|\?\*]+', "_"
If ($isMultiInstance) {
    $override = $True
    $dirExists = Test-Path ($OPT_GLOBAL_PATH + $domainPath) -PathType Container
    If ($dirExists) {
        $override = $Host.UI.PromptForChoice($Null, "Are you sure you want to overwrite the configuration for $domain?", ("&Yes", "&No"), 1) -eq 0
    }
    If (!$override) { Exit }
    ElseIf (!$dirExists) {
        Try {
            New-Item ($OPT_GLOBAL_PATH + $domainPath) -ItemType Directory
            Write-Host "Instance data directory created"
        }
        Catch {
            Write-Error "evertide could not create instance data directory"
            Exit
        }
    }
}

# Get instance colors from user
If ($PrimaryColor -eq $Null -or $PrimaryColor.Length -le 0) { $primaryColor = Read-Host -Prompt "Primary color for $domain" }
Else { $primaryColor = $PrimaryColor }
If ($SecondaryColor -eq $Null -or $SecondaryColor.Length -le 0) { $secondaryColor = Read-Host -Promp "Secondary color for $domain" }
Else { $secondaryColor = $SecondaryColor }
If ($DisplayName -eq $Null -or $DisplayName.Length -le 0) { $displayName = Read-Host -Prompt "How do you want the instance to be displayed to others? [$domain]" }
Else { $displayName = $DisplayName }
If (!$displayName -and $domainPath -ne $domain) { $displayName = $domain }

# Modify webmanifest
Try {
    $manifest = Get-Content ($WEB_PATH + "evertide.template.webmanifest") -Encoding UTF8 | ConvertFrom-Json
    $manifest.id = "evertide@$domain"
    $manifest.scope = $instanceUrl
    $manifest.share_target.action = "$($instanceUrl)add"
    $manifest.background_color = $primaryColor
    $manifest | ConvertTo-Json -Compress | Out-File ($WEB_PATH + $domainPath + ".webmanifest") -Encoding utf8
    Write-Host "evertide is set up to be hosted at $instanceUrl"
}
Catch {
    Write-Error "Web manifest could not be written"
    Exit
}

# Write config
Try {
    $configPath = $OPT_GLOBAL_PATH + $(If ($isMultiInstance) { $domainPath + "\" } Else { "" }) + "config.yml"
    @"
# yaml-language-server: `$schema=$(If ($isMultiInstance) { ".." } Else {"."})/config.schema.json

instance:
  domain: "$domainPath"$(If ($displayName) {"`n  display: `"$displayName`""})
  link: "$instanceUrl"
  primary: "$primaryColor"
  secondary: "$secondaryColor"
"@ | Out-File $configPath -Encoding ascii
    Write-Host "evertide config file prepared to set up in $configPath" -ForegroundColor Cyan
    Write-Host "Please open the config file above and modify database connection info."
    Write-Host "By default, evertide will use SQLite."
}
Catch {
    Write-Error "Configuration file could not be written"
    Exit
}