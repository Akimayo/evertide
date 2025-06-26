. ($PSScriptRoot + "\install-dependencies.ps1")

# Run tsc
Write-Progress "Building JavaScript"
$p = Start-Process "node" -ArgumentList ($WEB_PATH + ".\node_modules\typescript\bin\tsc") -WorkingDirectory $WEB_PATH -RedirectStandardError $ERROR_FILE -NoNewWindow -Wait -PassThru
If ($p.ExitCode -ne 0) {
    Write-Error "tsc exitted with error $($p.ExitCode)"
    Write-Host (Get-Content $ERROR_FILE)
    Remove-Item $ERROR_FILE
    Write-Progress "evertide" -Completed
    Exit
}
Get-ChildItem ($WEB_PATH + "*.js") | ForEach-Object {
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
Write-Host "Built and minified JavaScript"

# Run sass
Write-Progress "Building CSS"
Get-ChildItem ($WEB_PATH + "*.scss") | ForEach-Object {
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
Write-Host "Built and minified CSS"

Remove-Item $ERROR_FILE -ErrorAction SilentlyContinue
Write-Progress "evertide" -Completed