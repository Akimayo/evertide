window.addEventListener("load", () => {
  // Synchronize remote links in the background, then replace the <main> element with new links
  fetch("?sync")
    .then((response) => response.text())
    .then(
      (body) =>
        body && (document.querySelector("main.link-grid").outerHTML = body)
    )
    .catch(console.error);
});
