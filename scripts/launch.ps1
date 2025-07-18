$instance = Read-Host -Prompt "evertide instance (leave empty for default)"
If ($instance) {
    New-Item Env:\EVERTIDE_INSTANCE -Value $instance | Out-Null
}
$port = Read-Host -Prompt "Port to run evertide on [80]"
If (!$port) { $port = 80 }

. ($PSScriptRoot + "\build.ps1")

& php -S "localhost:$port" "$PSScriptRoot\..\php\src\router.php"