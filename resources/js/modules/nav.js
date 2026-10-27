export function initNav() {
    const toggle = document.getElementById("nav-toggle");
    const panel = document.getElementById("mobile-nav");
    if (!toggle || !panel) return;

    const setOpen = (open) => {
        toggle.setAttribute("aria-expanded", String(open));
        panel.classList.toggle("hidden", !open);
    };

    toggle.addEventListener("click", () => {
        setOpen(toggle.getAttribute("aria-expanded") !== "true");
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") setOpen(false);
    });
}
