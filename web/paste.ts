window.addEventListener("load", () => {
  if (navigator.clipboard && navigator.clipboard.readText) {
    const template = document.getElementsByTagName("template")[0];
    const button = template.content.getElementById("paste");
    const input = template.previousElementSibling as HTMLInputElement;

    const node = document.importNode(button, true);
    node.addEventListener("click", async () => {
      input.value = await navigator.clipboard.readText();
    });
    template.before(node);
  }
});
