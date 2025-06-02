import { decode } from "./node_modules/blurhash/dist/index.mjs";

const generateBlurhash = (canvas: HTMLCanvasElement) => {
  const width = canvas.width,
    height = canvas.height;
  const context = canvas.getContext("2d");
  const imageData = context.createImageData(width, height);
  imageData.data.set(
    decode(canvas.getAttribute("data-blurhash"), width, height)
  );
  context.putImageData(imageData, 0, 0);
  canvas.classList.add("blurhash-ready");
};

window.addEventListener("load", () => {
  document
    .querySelectorAll("article>details")
    .forEach((elmt: HTMLDetailsElement) =>
      elmt.addEventListener(
        "toggle",
        () =>
          elmt.open &&
          elmt
            .querySelectorAll("li>canvas:not(.blurhash-ready)")
            .forEach(generateBlurhash)
      )
    );
  document
    .querySelectorAll(".add-link-preview canvas:not(.blurhash-ready)")
    .forEach(generateBlurhash);
});
