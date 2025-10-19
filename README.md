# 🌊 evertide<br /><font size="4">surf the web with hyperlinks again</font>

> [!CAUTION]
> evertide is pre-alpha software. Functionality can be broken in between versions and federation compatibility is not guaranteed between versions. Migration guides will not be provided in between releases. Use at your own risk of data loss.

## Setting up

> [!WARNING]
> Even though the documentation suggests you can use databases other than SQLite, the code does not support any other database at this moment.

### Manual setup
Requirements:
- Smart editor (like VSCode, to help you edit YAML and JSON files)

Please see [manual setup instructions](./scripts/manual_setup.md).

### Automated setup
Requirements:
- NodeJS
- Smart editor (like VSCode, to help you edit YAML files)

Run `node scripts/setup.js` and follow the instructions.

By default, **evertide** will store its data in a SQLite database. If you wish to change the database, make sure to change the generated `config.yml` file (the schema will help you).

### Development/testing setup
Requirements:
- NodeJS (`node`)
- TypeScript compiler (`tsc`)
- PHP (`php`)

Proceed by instructions for [automated setup](#automated-setup).

If you make any changes to the JS code in _`web/index.ts`_, you will need to run `tsc` in the _`web`_ directory. All other changes to the code take effect immediately.

Run the development server by running
```cmd
php -S localhost[:<port>] ./php/src/router.php
```

### Multiple instances
**evertide** supports running multiple instances on the same infrastructure. If you want to run multiple instances, proceed as normal, but in the automated setup, make sure to respond _yes_ to the multiple instance prompt.

The backend only needs an environment variable called `EVERTIDE_INSTANCE`. Set it to the same value as the domain.

In production, this will be done in the Apache config file
```htaccess
<VirtualHost <address>>
    SetEnv EVERTIDE_INSTANCE "< domain name >"
    ...
</>
```

In development, set it in your shell by running
```sh
EVERTIDE_INSTANCE=<domain> php -S ...
```
or
```ps1
New-Item Env:/EVERTIDE_INSTANCE -Value <domain>
php -S ...
```

<details>
<summary>Full PowerShell example</summary>

Shell 1:
```ps1
node .\scripts\setup.js # Multiple instances: yes, URL: localhost
New-Item Env:/EVERTIDE_INSTANCE -Value "localhost"
php -S localhost:80 .\php\src\router.php
```

Shell 2:
```ps1
node .\scripts\setup.js # Multiple instances: yes, URL: localhost:81
New-Item Env:/EVERTIDE_INSTANCE -Value "localhost:81"
php -S localhost:80 .\php\src\router.php
```

</details>