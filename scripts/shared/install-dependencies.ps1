$progressMessage = "Installing dependencies"
Write-Progress $progressMessage
# Run Composer
If (!(Test-Path ($PHP_PATH + "vendor") -PathType Container)) {
    Write-Progress $progressMessage -PercentComplete 0 -Status "Running composer"
    $p = Start-Process "composer" -ArgumentList "install" -WorkingDirectory $PHP_PATH -RedirectStandardError $ERROR_FILE -NoNewWindow -Wait -PassThru
    If ($p.ExitCode -ne 0) {
        Write-Error "Composer exitted with error $($p.ExitCode)"
        Write-Host (Get-Content $ERROR_FILE)
        Remove-Item $ERROR_FILE
        Write-Progress "evertide" -Completed
        Exit
    }
}

# Run yarn
If (!(Test-Path ($WEB_PATH + "node_modules") -PathType Container)) {
    Write-Progress $progressMessage -PercentComplete 50 -Status "Running yarn"
    $p = Start-Process "yarn" -WorkingDirectory $WEB_PATH -RedirectStandardError $ERROR_FILE -NoNewWindow -Wait -PassThru
    If ($p.ExitCode -ne 0) {
        Write-Error "yarn exitted with error $($p.ExitCode)"
        Write-Host (Get-Content $ERROR_FILE)
        Remove-Item $ERROR_FILE
        Write-Progress "evertide" -Completed
        Exit
    }
}

Remove-Item $ERROR_FILE -ErrorAction SilentlyContinue
Write-Progress "evertide" -Completed