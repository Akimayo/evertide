# Run Composer
If (!(Test-Path ($PHP_PATH + "vendor") -PathType Container)) {
    Write-Progress "Installing PHP dependencies..."
    $p = Start-Process "composer" -ArgumentList "install" -WorkingDirectory $PHP_PATH -RedirectStandardError $ERROR_FILE -NoNewWindow -Wait -PassThru
    If ($p.ExitCode -ne 0) {
        Write-Error "Composer exitted with error $($p.ExitCode)"
        Write-Host (Get-Content $ERROR_FILE)
        Remove-Item $ERROR_FILE
        Write-Progress "evertide" -Completed
        Exit
    }
    Write-Host "Installed PHP dependencies"
}

# Run yarn
If (!(Test-Path ($WEB_PATH + "node_modules") -PathType Container)) {
    Write-Progress "Installing JS dependencies..."
    $p = Start-Process "yarn" -WorkingDirectory $WEB_PATH -RedirectStandardError $ERROR_FILE -NoNewWindow -Wait -PassThru
    If ($p.ExitCode -ne 0) {
        Write-Error "yarn exitted with error $($p.ExitCode)"
        Write-Host (Get-Content $ERROR_FILE)
        Remove-Item $ERROR_FILE
        Write-Progress "evertide" -Completed
        Exit
    }
    Write-Host "Installed JS dependencies"
}

Remove-Item $ERROR_FILE -ErrorAction SilentlyContinue
Write-Progress "evertide" -Completed