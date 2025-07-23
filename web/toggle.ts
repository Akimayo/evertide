window.addEventListener('load', () =>
    document.querySelectorAll('main article>details')
        .forEach((c: HTMLDetailsElement) =>
            c.addEventListener('toggle', (evt: ToggleEvent) =>
                evt.newState === 'open' &&
                document.querySelectorAll('main article>details[open]')
                    .forEach((elmt: HTMLDetailsElement) => elmt.isEqualNode(c) || (elmt.open = false))
            ))
);