param(
    [Parameter(HelpMessage="Port to run the development server on")]
    [int]$Port = -1,
    [Parameter(HelpMessage="URL of the instance to run")]
    [string]$InstanceUrl
)
if ($InstanceUrl -eq $Null -or $InstanceUrl.Length -le 0) { $instance = Read-Host -Prompt "evertide instance (leave empty for default)" }
Else { $instance = $InstanceUrl }
If ($instance) {
    New-Item Env:\EVERTIDE_INSTANCE -Value $instance -Force | Out-Null
}
If ($Port -lt 0) { $port = Read-Host -Prompt "Port to run evertide on [80]" }
Else { $port = $Port }
If (!$port) { $port = 80 }

. ($PSScriptRoot + "\build.ps1")

& php -S "localhost:$port" "$PSScriptRoot\..\php\src\router.php"