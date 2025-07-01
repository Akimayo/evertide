. ($PSScriptRoot + "\install-dependencies.ps1")

# Run tsc
$progressMessage = "Building JavaScript"
Write-Progress $progressMessage
$p = Start-Process "node" -ArgumentList ($WEB_PATH + ".\node_modules\typescript\bin\tsc") -WorkingDirectory $WEB_PATH -RedirectStandardError $ERROR_FILE -NoNewWindow -Wait -PassThru
If ($p.ExitCode -ne 0) {
    Write-Error "tsc exitted with error $($p.ExitCode)"
    Write-Host (Get-Content $ERROR_FILE)
    Remove-Item $ERROR_FILE
    Write-Progress "evertide" -Completed
    Exit
}
Write-Progress $progressMessage -PercentComplete 50
$items = Get-ChildItem ($WEB_PATH + "*.js")
$itemPercent = 50.0 / $items.Length
$i = 0
$items | ForEach-Object {
    Write-Progress $progressMessage -PercentComplete (50 + ($i++) * $itemPercent) -Status "Minifying $($_.Name)"
    If ($_.FullName.Substring($_.FullName.Length - 7) -eq ".min.js") { Return }
    $minPath = $_.FullName.Substring(0, $_.FullName.Length - 3) + ".min.js";
    $p = Start-Process "node" -ArgumentList ($WEB_PATH + ".\node_modules\jsmin\bin\jsmin"), $_.FullName -WorkingDirectory $WEB_PATH -RedirectStandardError $ERROR_FILE -RedirectStandardOutput $minPath -NoNewWindow -Wait -PassThru
    If ($p.ExitCode -ne 0) {
        Write-Error "jsmin exitted with error $($p.ExitCode)"
        Write-Host (Get-Content $ERROR_FILE)
        Remove-Item $ERROR_FILE
        Write-Progress "evertide" -Completed
        Exit
    }
    If (Test-Path ($_.FullName + ".map") -PathType Leaf) {
        "//# sourceMappingURL=$($_.Name).map" | Out-File $minPath -Append -Encoding utf8
    }
}

# Run sass
$progressMessage = "Building CSS"
Write-Progress $progressMessage
$items = Get-ChildItem ($WEB_PATH + "*.scss")
$itemPercent = 100.0 / $items.Length
$i = 0
$items | ForEach-Object {
    Write-Progress $progressMessage -PercentComplete (($i++) * $itemPercent) -Status "Compiling $($_.Name)"
    If ($_.Name.StartsWith("_")) { Return; }
    $minPath = $_.FullName.Substring(0, $_.FullName.Length - 5) + ".min.css";
    $p = Start-Process "node" -ArgumentList ($WEB_PATH + ".\node_modules\sass\sass.js"), "$($_.FullName):$($minPath)", "--style", "compressed" -WorkingDirectory $WEB_PATH -RedirectStandardError $ERROR_FILE -NoNewWindow -Wait -PassThru
    if ($p.ExitCode -ne 0) {
        Write-Error "sass exitted with error $($p.ExitCode)"
        Write-Host (Get-Content $ERROR_FILE)
        Remove-Item $ERROR_FILE
        Write-Progress "evertide" -Completed
        Exit
    }
}

Remove-Item $ERROR_FILE -ErrorAction SilentlyContinue
Write-Progress "evertide" -Completed