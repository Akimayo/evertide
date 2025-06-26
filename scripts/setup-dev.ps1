. ($PSScriptRoot + "\shared\variables.ps1")

# Generate assets symlink
If (!(Get-Item ($PHP_PATH + "assets") -ErrorAction SilentlyContinue)) {
    Write-Progress "Craeting assets symlink"
    Try {
        New-Item -Path $WEB_PATH -Value ($PHP_PATH + "assets") -ItemType Junction
        Write-Host "Created a symlink php/assets -> web"
    }
    Catch {
        Write-Error "evertide could not set up a symlink for serving assets from PHP"
        Write-Progress "evertide" -Completed
        Exit
    }
}

. ($PSScriptRoot + "\shared\build-assets.ps1")

. ($PSScriptRoot + "\setup.ps1")