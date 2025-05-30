const fs = require("node:fs");
const rl = require("node:readline").createInterface(process.stdin, process.stdout);

fs.readFile("../web/evertide.template.webmanifest", "utf8", (err, data) => {
    if (err) {
        console.error("evertide web manifest does not exist or is inaccessible");
        console.error(err);
        return;
    }

    const obj = JSON.parse(data);
    rl.question("Please enter the domain where evertide will be hosted: ", site => {
        if (!site.startsWith("http://") && !site.startsWith("https://")) {
            site = "https://" + site;
        }
        if (!site.endsWith("/")) site += "/";
        const domain = site.substring(site.indexOf("/") + 2, site.indexOf("/", 9));

        obj.id = "evertide@" + domain;
        obj.scope = site;
        obj.share_target.action = site + "add";

        fs.writeFile("../web/evertide.webmanifest", JSON.stringify(obj, null, 2), (err) => {
            if (err) {
                console.error("evertide web manifest could not be written");
                console.error(err);
                return;
            }
            console.info("evertide is set up to be hosted at", site);
            process.exit();
        });
    });
});