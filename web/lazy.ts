function removeImgSrc() {
    document.querySelectorAll("article li img:not([src='']").forEach(img => {
        const src = img.getAttribute("src");
        img.setAttribute("data-src", src);
        img.setAttribute("src", "");
    })
}
removeImgSrc();
window.addEventListener("load", () => {
    removeImgSrc();
    document
        .querySelectorAll("article>details")
        .forEach((elmt: HTMLDetailsElement) =>
            elmt.addEventListener(
                "toggle",
                () =>
                    elmt.open &&
                    elmt
                        .querySelectorAll("li img[data-src][src='']")
                        .forEach(img =>
                            img.setAttribute("src", img.getAttribute("data-src"))
                        )
            )
        );
});