const { spawn } = require("node:child_process");
const fs = require("node:fs/promises");
const exists = require("node:fs").existsSync;
const rl = require("node:readline").createInterface(
  process.stdin,
  process.stdout
);
// #region Helper functions
const question = (prompt) =>
  new Promise((resolve) => rl.question(prompt, resolve));
const yesno = async (prompt) => {
  let rsp;
  while ((rsp = await question(prompt + " [yes/no] "))) {
    switch (rsp.toLowerCase()) {
      case "yes":
      case "y":
        return true;
      case "no":
      case "n":
        return false;
      default:
        console.warn("Please try again");
        break;
    }
  }
};
const getJSON = (path) =>
  new Promise((resolve, reject) =>
    fs.readFile(path, "utf8")
      .then(data => resolve(JSON.parse(data)))
      .catch(err => reject(err))
  );
// #endregion

// Paths
const WEB_PATH = __dirname + "/../web/",
  PHP_PATH = __dirname + "/../php/",
  OPT_GLOBAL_PATH = PHP_PATH + "opt/";

async function main() {
  // Generate assets symlink
  if (!exists(PHP_PATH + "assets")) {
    await fs.symlink(WEB_PATH, PHP_PATH + "assets")
      .then(() => console.log("Created a symlink php/assets -> web"))
      .catch(err => {
        console.error(
          "evertide could not set up a symlink for serving assets from PHP"
        );
        console.error(err);
        process.exit(1);
      });
  }

  /*// Run Composer
  if (!exists(PHP_PATH + "vendor")) {
    console.log("Installing PHP dependencies...");
    await new Promise((resolve, reject) => {
      const composer = spawn("composer", ["install"], { cwd: PHP_PATH, env: process.env });
      const stderr = [];
      composer.stderr.on('data', stderr.push);
      composer.on('close', code => {
        if (code === 0) resolve();
        else reject(stderr);
      })
    }).catch(err => {
      console.error("Composer exitted with error");
      console.error(err);
      process.exit(2);
    })
  }
  // Run yarn
  if (!exists(WEB_PATH + "node_modules")) {
    console.log("Installing JS dependencies...");
    await new Promise((resolve, reject) => {
      const yarn = spawn("yarn", [], { cwd: WEB_PATH, env: process.env });
      const stderr = [];
      yarn.stderr.on('data', stderr.push);
      yarn.on('close', code => {
        if (code === 0) resolve();
        else reject(stderr);
      })
    }).catch(err => {
      console.error("yarn exitted with error");
      console.error(err);
      process.exit(3);
    })
  }
  // Run tsc
  if (!exists(WEB_PATH + "index.js")) {
    console.log("Building JavaScript...");
    // const yarn_bin = await new Promise((resolve, reject) => {
    //   const yarn = spawn("yarn", ["global", "bin"], { env: process.env });
    //   const stdout = [], stderr = [];
    //   yarn.stdout.on('data', stdout.push);
    //   yarn.stderr.on('data', stderr.push);
    //   yarn.on('close', code => {
    //     if (code === 0) resolve(stdout[stdout.length - 1]);
    //     else reject(stderr);
    //   });
    // }).catch(err => {
    //   console.error("yarn exitted with error");
    //   console.error(err);
    //   process.exit(3);
    // });
    await new Promise((resolve, reject) => {
      const tsc = spawn("tsc", [], { cwd: WEB_PATH, env: process.env });
      const stderr = [];
      tsc.stderr.on('data', stderr.push);
      tsc.on('close', code => {
        if (code === 0) resolve();
        else reject(stderr);
      })
    }).catch(err => {
      console.error("tsc exitted with error");
      console.error(err);
      process.exit(4);
    })
  }*/

  // Get instance information from user
  const isMultiInstance = await yesno("Are you running multiple instances?");
  let instanceUrl = await question(
    "Please enter the URL where" +
    (isMultiInstance ? " this instance of" : "") +
    " evertide will be hosted: "
  );

  // Parse domain out of URL
  if (
    !instanceUrl.startsWith("http://") &&
    !instanceUrl.startsWith("https://")
  ) {
    instanceUrl = "http://" + instanceUrl;
  }
  if (!instanceUrl.endsWith("/")) instanceUrl += "/";
  const domain = instanceUrl.substring(
    instanceUrl.indexOf("/") + 2,
    instanceUrl.indexOf("/", 9)
  );

  // Make a safe directory name and create directory if needed
  const domainPath = domain.replaceAll(/[\/<>:"\\|?\*]+/g, "_");
  if (isMultiInstance) {
    let override = true,
      dirExists = exists(OPT_GLOBAL_PATH + domainPath);
    if (dirExists)
      override = await yesno(
        "Are you sure you want to overwrite the configuration for " +
        domain +
        "?"
      );
    if (!override) process.exit();
    else if (!dirExists)
      await fs.mkdir(OPT_GLOBAL_PATH + domainPath)
        .then(() => console.log("Instance data directory created"))
        .catch((err) => {
          console.error("evertide could not create instance data directory");
          console.error(err);
          process.exit(5);
        });
  }

  // Get instance colors from user
  const primaryColor = await question("  Primary color for " + domain + ": "),
    secondaryColor = await question("  Secondary color for " + domain + ": ");
  let displayName = await question(
    "  How do you want the instance to be displayed to others? [" +
    domain +
    "] "
  );
  if (!displayName && domainPath != domain) displayName = domain;

  // Modify webmanifest
  const obj = await getJSON(WEB_PATH + "evertide.template.webmanifest");
  obj.id = "evertide@" + domain;
  obj.scope = instanceUrl;
  obj.share_target.action = instanceUrl + "add";
  obj.theme_color = primaryColor;
  await fs.writeFile(
    WEB_PATH + domainPath + ".webmanifest",
    JSON.stringify(obj, null, 2)
  )
    .then(() => console.log("evertide is set up to be hosted at", instanceUrl))
    .catch(err => {
      console.error("Web manifest could not be written");
      console.error(err);
      process.exit(6);
    });

  // Write config
  await fs.writeFile(
    OPT_GLOBAL_PATH + (isMultiInstance ? domainPath + "/" : "") + "config.yml",
    `
# yaml-language-server: $schema=${isMultiInstance ? ".." : "."
      }/config.schema.json

instance:
  domain: "${domainPath}"${displayName && '\n  display: "' + displayName + '"'}
  link: "${instanceUrl}"
  primary: "${primaryColor}"
  secondary: "${secondaryColor}"
  `.toString()
  )
    .then(() => {
      console.log(
        "evertide config file prepared to set up in " +
        OPT_GLOBAL_PATH +
        (isMultiInstance ? domainPath + "/" : "") +
        "config.yml"
      );
      console.info(
        "Please open the config file above and modify database connection info."
      );
      console.info("By default, evertide will use SQLite");
    }).catch(err => {
      console.error("Configuration file could not be written");
      console.error(err);
      process.exit(7);
    });
  process.exit();
}
main();
