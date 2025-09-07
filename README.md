# 🌊 evertide<br /><font size="4">surf the web with hyperlinks again</font>

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

Run `.\scripts\setup.ps1` and follow the instructions.

By default, **evertide** will store its data in a SQLite database. If you wish to change the database, make sure to change the generated `config.yml` file (the schema will help you).

After you are done with tweaking the configuration, run `.\scripts\pack.ps1`. This will create a _`build/`_ folder, copy its contents to your hosting.

### Development/testing setup
Requirements:
- NodeJS (`node`)
- PHP (`php`)
- Smart editor

Run `.\scripts\setup-dev.ps1` and follow the instructions.

If you make any changes to the SCSS or TS files in the _`web/`_ folder, `.\scripts\build.ps1`. All other changes to the code take effect immediately.

Run the development server by running `.\scripts\launch.ps1`.

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

In development, run the launch script in a terminal for every instance and give each the appropriate domain name and port.

<details>
<summary>Full PowerShell example</summary>

Shell 1:
```ps1
.\scripts\setup-dev.ps1 # Multiple instances: yes, URL: localhost
.\scripts\launch.ps1 # Instance: localhost, port: (default)
```

Shell 2:
```ps1
.\scripts\setup-dev.ps1 # Multiple instances: yes, URL: localhost:81
.\scripts\launch.ps1 # Instance: localhost:81, port: 81
```

</details>