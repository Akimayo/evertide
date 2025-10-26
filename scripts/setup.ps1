param(
    [Parameter(HelpMessage="URL where this instance of evertide will be hosted")]
    [string]$InstanceUrl,
    [Parameter(HelpMessage="Whether there are/will be multiple instances on this installation")]
    [nullable[bool]]$IsMultiInstance,
    [Parameter(HelpMessage="Whether the multiple instances are hosted on different domains, or distinguished by path")]
    [nullable[bool]]$IsMultiDomain,
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

Write-Host "evertide" -ForegroundColor DarkBlue

# Get instance information form user
If ($IsMultiInstance -eq $Null) {
    Write-Host @"
  evertide supports hosting multiple instances (e.g. evertide for multiple
people) on the same installation. If you're installing evertide only for
yourself, this is not needed, but if you plan on sharing this installation of
evertide with others, evertide will need to know how to handle routing
between different instances.
"@ -ForegroundColor DarkGray
    $isMultiInstance = ($Host.UI.PromptForChoice($Null, "Are you running multiple instances?", ("&Yes", "&No"), 1)) -eq 0
}
Else { $isMultiInstance = $IsMultiInstance }
If ($instanceUrl -eq $Null -or $instanceUrl.Length -le 0) { $instanceUrl = Read-Host -Prompt ("Please enter the URL where$(If ($isMultiInstance) { " this instance of" } Else { '' }) evertide will be hosted") }
Else { $instanceUrl = $InstanceUrl }

# Parse domain out of URL
If (!$instanceUrl.StartsWith("http://") -and !$instanceUrl.StartsWith("https://")) { $instanceUrl = "http://" + $instanceUrl; }
If (!$instanceUrl.EndsWith("/")) { $instanceUrl += "/" }
$domainStart = $instanceUrl.IndexOf("/") + 2
$domainEnd = $instanceUrl.IndexOf("/", $domainStart);
$domain = $instanceUrl.Substring($domainStart, $domainEnd - $domainStart)
$path = $instanceUrl.Substring($domainEnd + 1, $instanceUrl.Length - $domainEnd - 2)

If ($isMultiInstance -and $IsMultiDomain -eq $Null) {
    Write-Host @"
  There are two ways to how to use evertide to host multiple instances.
The easier one is having them on a single domain with instances separated by
just the 'path string', i.e. 'evertide.example.com/john' and
'evertide.example.com/bob'. In this case, evertide will handle routing on its
own, but each instance has to have the same domain.
  The other option is multiple (sub)domains, i.e. 'john.evertide.example.com'
and 'bob.evertide.example.com'. Routing here depends on the hosting provider,
or rather the way you have your web server set up, therefore it will likely
involve some manual intervention into your web server's routing. In this case,
please refer to the docs.
"@ -ForegroundColor DarkGray
    $isMultiDomain = ($Host.UI.PromptForChoice($Null, "Are the instances hosted on different domains or different paths on the same domain?", ("&Multiple domains", "&Same domain"), 1)) -eq 0
}
Else { $isMultiDomain = $IsMultiDomain }

# Make a safe directory name and create directory if needed
If ($isMultiDomain) { $instancePath = $domain } Else { $instancePathBase = $path }
$instancePathBase = $instancePathBase -replace '[\/<>:"\\|\?\*]+', "_"
$instancePath = $OPT_GLOBAL_PATH + $instancePath

If ($isMultiInstance) {
    $override = $True
    $dirExists = Test-Path $instancePath -PathType Container
    If ($dirExists -and !$Force) {
        $instancePath = (Get-Item $instancePath).FullName
        Write-Host @"
  A directory for this instance already exists: $instancePath
  If you continue, the configuration in this directory will be lost and
replaced with a new configuration.
"@ -ForegroundColor DarkGray
        $override = $Host.UI.PromptForChoice($Null, "Are you sure you want to overwrite the configuration for $domain$(If ($path.Length -gt 0) { '/' + $path } Else { '' })?", ("&Yes", "&No"), 1) -eq 0
    }
    If (!$override) { Exit }
    ElseIf (!$dirExists) {
        Try {
            $instancePath = (New-Item $instancePath -ItemType Directory).FullName
            Write-Host "Instance data directory created in $instancePath"
        }
        Catch {
            Write-Error "evertide could not create instance data directory in $instancePath"
            Exit
        }
    }
}

# Get instance colors from user
If (($PrimaryColor -eq $Null -or $PrimaryColor.Length -le 0) -and ($SecondaryColor -eq $Null -or $SecondaryColor.Length -le 0) -and ($DisplayName -eq $Null -or $DisplayName.Length -le 0)) {
    Write-Host @"
  evertide is big on per-instance theming. It uses two colours to distinguish
your instance from others, along with an optional display name.
  There are also sticker options, which can be later set manually in the
configuration file.
"@ -ForegroundColor DarkGray
}
If ($PrimaryColor -eq $Null -or $PrimaryColor.Length -le 0) { $primaryColor = Read-Host -Prompt "Primary color for $domain" }
Else { $primaryColor = $PrimaryColor }
If ($SecondaryColor -eq $Null -or $SecondaryColor.Length -le 0) { $secondaryColor = Read-Host -Promp "Secondary color for $domain" }
Else { $secondaryColor = $SecondaryColor }
If ($DisplayName -eq $Null -or $DisplayName.Length -le 0) { $displayName = Read-Host -Prompt "How do you want the instance to be displayed to others? [$domain]" }
Else { $displayName = $DisplayName }
If (!$displayName -and $instancePath -ne $domain) {
    If ($isMultiDomain) { $displayName = $domain }
    Else { $displayName = $path }
}

# Modify webmanifest
Try {
    $manifest = Get-Content ($WEB_PATH + "evertide.template.webmanifest") -Encoding UTF8 | ConvertFrom-Json
    $manifest.id = "evertide@$domain$(If ($path.Length -gt 0) { '/' + $path } Else { '' })"
    $manifest.scope = $instanceUrl
    $manifest.share_target.action = "$($instanceUrl)add"
    $manifest.background_color = $primaryColor
    $manifest | ConvertTo-Json -Compress | Out-File ($WEB_PATH + $instancePathBase + ".webmanifest") -Encoding utf8
    Write-Host "evertide is set up to be hosted at $instanceUrl"
}
Catch {
    Write-Error "Web manifest could not be written"
    Exit
}

# Write config
Try {
    $configPath = $OPT_GLOBAL_PATH + $(If ($isMultiInstance) { $instancePathBase + "\" } Else { "" }) + "config.yml"
    @"
# yaml-language-server: `$schema=$(If ($isMultiInstance) { ".." } Else {"."})/config.schema.json

instance:
  domain: "$instancePathBase"$(If ($displayName) {"`n  display: `"$displayName`""})
  link: "$instanceUrl"
  primary: "$primaryColor"
  secondary: "$secondaryColor"
"@ | Out-File $configPath -Encoding ascii
    $configPath = (Get-Item $configPath).FullName
    Write-Host "evertide config file prepared to set up in $configPath" -ForegroundColor White
    Write-Host "Please open the config file above and modify database connection info."
    Write-Host "By default, evertide will use SQLite."
}
Catch {
    Write-Error "Configuration file could not be written"
    Exit
}