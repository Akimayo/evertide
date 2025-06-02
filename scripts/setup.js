const fs = require("node:fs");
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
    fs.readFile(path, "utf8", (err, data) => {
      if (err) reject(err);
      else resolve(JSON.parse(data));
    })
  );
// #endregion

// Paths
const WEB_PATH = __dirname + "/../web/",
  PHP_PATH = __dirname + "/../php/",
  OPT_GLOBAL_PATH = PHP_PATH + "opt/";

async function main() {
  // Generate assets symlink
  if (!fs.existsSync(PHP_PATH + "assets")) {
    fs.symlink(WEB_PATH, PHP_PATH + "assets", (err) => {
      if (err) {
        console.error(
          "evertide could not set up a symlink for serving assets from PHP"
        );
        console.error(err);
      }
      console.log("created a symlink php/assets -> web");
    });
  }

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

  // Get instance colors from user
  const primaryColor = await question("  Primary color for " + domain + ": "),
    secondaryColor = await question("  Secondary color for " + domain + ": ");
  let displayName = await question(
    "  How do you want the instance to be displayed to others? [" +
      domain +
      "] "
  );

  // Make a safe directory name and create directory if needed
  const domainPath = domain.replaceAll(/[\/<>:"\\|?\*]+/g, "_");
  if (isMultiInstance) {
    let override = true,
      exists = fs.existsSync(OPT_GLOBAL_PATH + domainPath);
    if (exists)
      override = await yesno(
        "Are you sure you want to overwrite the configuration for " +
          domain +
          "?"
      );
    if (!override) return;
    else if (!exists)
      fs.mkdir(OPT_GLOBAL_PATH + domainPath, (err) => {
        if (err) {
          console.error("evertide could not create instance data directory");
          console.error(err);
        }
        console.log("Instance data directory created");
      });
  }
  if (!displayName && domainPath != domain) displayName = domain;

  // Modify webmanifest
  const obj = await getJSON(WEB_PATH + "evertide.template.webmanifest");
  obj.id = "evertide@" + domain;
  obj.scope = instanceUrl;
  obj.share_target.action = instanceUrl + "add";
  obj.theme_color = primaryColor;
  fs.writeFile(
    WEB_PATH + domainPath + ".webmanifest",
    JSON.stringify(obj, null, 2),
    (err) => {
      if (err) {
        console.error("evertide web manifest could not be written");
        console.error(err);
        return;
      }
      console.log("evertide is set up to be hosted at", instanceUrl);
      process.exit();
    }
  );

  // Write config
  fs.writeFile(
    OPT_GLOBAL_PATH + (isMultiInstance ? domainPath + "/" : "") + "config.yml",
    `
# yaml-language-server: $schema=${
      isMultiInstance ? ".." : "."
    }/config.schema.json

instance:
  domain: "${domainPath}"${displayName && '\n  display: "' + displayName + '"'}
  link: "${instanceUrl}"
  primary: "${primaryColor}"
  secondary: "${secondaryColor}"
  `.toString(),
    (err) => {
      if (err) {
        console.error("evertide config is inaccessible to write");
        console.error(err);
        return;
      }
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
    }
  );
}
main();
