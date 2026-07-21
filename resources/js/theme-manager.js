const tokenInputs = document.querySelectorAll('.theme-colour-input');

for (const group of tokenInputs) {
    const colour = group.querySelector('input[type="color"]');
    const text = group.querySelector('input:not([type="color"])');
    const panel = group.closest('.theme-editor-panel');

    if (!colour || !text || !panel) {
        continue;
    }

    const tokenName = text.name.match(/^tokens\[([^\]]+)]$/)?.[1];

    const apply = (value) => {
        const normalized = String(value).trim().toLowerCase();

        if (!/^#[0-9a-f]{6}$/.test(normalized)) {
            return;
        }

        colour.value = normalized;
        text.value = normalized;

        if (tokenName) {
            panel.style.setProperty(`--${tokenName.replaceAll('_', '-')}`, normalized);
        }
    };

    colour.addEventListener('input', () => apply(colour.value));
    text.addEventListener('input', () => apply(text.value));
}
