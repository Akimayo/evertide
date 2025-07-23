window.addEventListener('load', () =>
    document.querySelectorAll('main article>details')
        .forEach((c: HTMLDetailsElement) =>
            c.addEventListener('toggle', (evt: ToggleEvent) =>
                evt.newState === 'open' &&
                document.querySelectorAll('main article>details[open]')
                    .forEach((elmt: HTMLDetailsElement) => elmt.isEqualNode(c) || (elmt.open = false))
            ))
);
window.addEventListener('keydown', (evt) => {
    let elmt: HTMLDetailsElement;
    if (evt.key === "Escape" && (elmt = document.querySelector('main article>details[open]'))) {
        elmt.open = false;
        (elmt.children[0] as HTMLElement).focus();
    }
});