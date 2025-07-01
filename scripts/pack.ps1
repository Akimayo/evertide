. ($PSScriptRoot + "\build.ps1")

$jsdeps = @("blurhash") # Defines `node_modules` packages to be copied
$phpdirs = @("src", "templates", "vendor") # Defines PHP source directories to be copied

$progressMessage = "Copying files"

# Create build ouput directory
Write-Progress $progressMessage -PercentComplete 0 -Status "Creating build directory"
$BUILD_PATH = $PSScriptRoot + "\..\build\";
New-Item -Path $BUILD_PATH -ItemType Directory -ErrorAction SilentlyContinue | Out-Null

# Copy over PHP source directories and add .htaccess
Write-Progress $progressMessage -PercentComplete 20 -Status "Copying PHP source files"
$phpdirs | ForEach-Object {
    Copy-Item ($PHP_PATH + $_) -Destination ($BUILD_PATH + $_) -Recurse -Force
    "deny from all" | Out-File ($BUILD_PATH + $_ + "\.htaccess") -Force
}

Copy-Item ($PHP_PATH + ".htaccess") -Destination ($BUILD_PATH + ".htaccess") -Force
Get-ChildItem ($PHP_PATH + "*.php") | ForEach-Object {
    Copy-Item $_.FullName -Destination ($BUILD_PATH + $_.Name) -Force
}

# Copy migrations and instance configuration files and add .htaccess
Write-Progress $progressMessage -PercentComplete 40 -Status "Copying instance configuration"
New-Item -Path ($BUILD_PATH + "opt") -ItemType Directory -ErrorAction SilentlyContinue | Out-Null
Copy-Item ($PHP_PATH + "opt\config.schema.json") -Destination ($BUILD_PATH + "opt\config.schema.json") -Force
If (Test-Path ($PHP_PATH + "opt\config.yml") -PathType Leaf) { Copy-Item ($PHP_PATH + "opt\config.yml") -Destination ($BUILD_PATH + "opt\config.yml") -Force }
Get-ChildItem ($PHP_PATH + "opt") -Directory | ForEach-Object {
    If (Test-Path ($_.FullName + "\config.yml") -PathType Leaf) {
        New-Item ($BUILD_PATH + "opt\" + $_.Name) -ItemType Directory -ErrorAction SilentlyContinue | Out-Null
        Copy-Item ($_.FullName + "\config.yml") -Destination ($BUILD_PATH + "opt\" + $_.Name + "\config.yml") -Force
    }
    ElseIf ($_.Name -eq "migrations") {
        Copy-Item $_.FullName -Destination ($BUILD_PATH + "opt\migrations") -Recurse -Force
    }
}
"deny from all" | Out-File ($BUILD_PATH + "opt\.htaccess") -Force

# Copy over language files
Write-Progress $progressMessage -PercentComplete 60 -Status "Copying language files"
Copy-Item ($WEB_PATH + "locale") -Destination ($BUILD_PATH + "assets\locale") -Recurse -Force
# Copy over minified JS and CSS
Write-Progress $progressMessage -PercentComplete 80 -Status "Copying minified JavaScript and CSS"
New-Item -Path ($BUILD_PATH + "assets") -ItemType Directory -ErrorAction SilentlyContinue | Out-Null
Get-ChildItem ($WEB_PATH + '*.min.js') | ForEach-Object {
    Copy-Item $_.FullName -Destination ($BUILD_PATH + "assets\" + $_.Name) -Force
}
Get-ChildItem ($WEB_PATH + '*.js.map') | ForEach-Object {
    Copy-Item $_.FullName -Destination ($BUILD_PATH + "assets\" + $_.Name) -Force
}
Get-ChildItem ($WEB_PATH + '*.min.css*') | ForEach-Object {
    Copy-Item $_.FullName -Destination ($BUILD_PATH + "assets\" + $_.Name) -Force
}
Get-ChildItem ($WEB_PATH + '*.webmanifest') | ForEach-Object {
    If ($_.Name.EndsWith('.template.webmanifest')) { Return }
    Copy-Item $_.FullName -Destination ($BUILD_PATH + "assets\" + $_.Name) -Force
}
# Copy selected `node_modules` packages, limit to only *dist/esm* or *dist* when available
Write-Progress $progressMessage -PercentComplete 100 -Status "Copying JavaScript dependencies"
New-Item -Path ($BUILD_PATH + "assets\node_modules") -ItemType Directory -ErrorAction SilentlyContinue | Out-Null
$jsdeps | ForEach-Object {
    If (Test-Path ($WEB_PATH + "node_modules\$_\dist") -PathType Container) {
        New-Item -Path ($BUILD_PATH + "assets\node_modules\$_") -ItemType Directory -ErrorAction SilentlyContinue | Out-Null
        If (Test-Path ($WEB_PATH + "node_modules\$_\dist\esm") -PathType Container) {
            New-Item -Path ($BUILD_PATH + "assets\node_modules\$_\dist") -ItemType Directory -ErrorAction SilentlyContinue | Out-Null
            Copy-Item ($WEB_PATH + "node_modules\$_\dist\esm") -Destination ($BUILD_PATH + "assets\node_modules\$_\dist\esm") -Recurse -Force
        }
        Else {
            Copy-Item ($WEB_PATH + "node_modules\$_\dist") -Destination ($BUILD_PATH + "assets\node_modules\$_\dist") -Recurse -Force
        }
    }
    Else {
        Copy-Item ($WEB_PATH + "node_modules\$_") -Destination ($BUILD_PATH + "assets\node_modules\$_") -Recurse -Force
    }
}

Write-Progress "evertide" -Completed