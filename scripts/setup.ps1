. ($PSScriptRoot + "\shared\variables.ps1")

# Get instance information form user
$isMultiInstance = ($Host.UI.PromptForChoice("🌊 evertide", "Are you running multiple instances?", ("&Yes", "&No"), 1)) -eq 0
$instanceUrl = Read-Host -Prompt ("Please enter the URL where$(If ($isMultiInstance) { " this instance of" } Else { '' }) evertide will be hosted")

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
$primaryColor = Read-Host -Prompt "Primary color for $domain"
$secondaryColor = Read-Host -Promp "Secondary color for $domain"
$displayName = Read-Host -Prompt "How do you want the instance to be displayed to others? [$domain]"
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
    Write-Error "Web manifest could not be writted"
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
    Write-Host "evertide config file prepared to set up in $configPath"
    Write-Host "Please open the config file above and modify database connection info."
    Write-Host "By default, evertide will use SQLite."
}
Catch {
    Write-Error "Configuration file could not be written"
    Exit
}