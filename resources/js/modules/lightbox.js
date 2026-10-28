export function initLightbox() {
    const dialog = document.getElementById("lightbox");
    if (!dialog) return;

    const img = dialog.querySelector("img");
    const caption = dialog.querySelector("[data-lightbox-caption]");
    let trigger = null;

    document.addEventListener("click", (e) => {
        const open = e.target.closest("[data-lightbox]");
        if (open) {
            trigger = open;
            img.src = open.dataset.src;
            img.alt = open.dataset.alt || "";
            caption.textContent = open.dataset.alt || "";
            dialog.showModal();
            return;
        }

        if (e.target === dialog || e.target.closest("[data-lightbox-close]")) {
            dialog.close();
        }
    });

    dialog.addEventListener("close", () => {
        img.removeAttribute("src");
        if (trigger) trigger.focus();
    });
}
