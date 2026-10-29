const COOKIE = "admin_theme";

function setTheme(theme) {
    document.documentElement.dataset.theme = theme;
    document.cookie =
        `${COOKIE}=${theme}; path=/; max-age=31536000; SameSite=Lax` +
        (location.protocol === "https:" ? "; Secure" : "");
    document.dispatchEvent(new CustomEvent("admin:theme"));
}

export function initAdminShell() {
    const sidebar = document.getElementById("admin-sidebar");
    if (!sidebar) return;

    // First visit: follow the operating system's preference once.
    const hasChoice = document.cookie
        .split("; ")
        .some((c) => c.startsWith(COOKIE + "="));
    if (
        !hasChoice &&
        window.matchMedia("(prefers-color-scheme: dark)").matches
    ) {
        setTheme("dark");
    }

    const overlay = document.querySelector("[data-sidebar-overlay]");
    const toggle = document.querySelector("[data-sidebar-toggle]");

    const setOpen = (open) => {
        sidebar.dataset.open = String(open);
        overlay?.classList.toggle("hidden", !open);
        toggle?.setAttribute("aria-expanded", String(open));
    };

    document.addEventListener("click", (e) => {
        if (e.target.closest("[data-theme-toggle]")) {
            setTheme(
                document.documentElement.dataset.theme === "dark"
                    ? "light"
                    : "dark",
            );
        } else if (e.target.closest("[data-sidebar-toggle]")) {
            setOpen(sidebar.dataset.open !== "true");
        } else if (e.target === overlay) {
            setOpen(false);
        }
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && sidebar.dataset.open === "true") {
            setOpen(false);
            toggle?.focus();
        }
    });

    document.addEventListener("input", (e) => {
        // Picking a color switches "use default" off.
        const picker = e.target.closest('input[type="color"][data-reset]');
        if (picker) {
            const reset = document.getElementById(picker.dataset.reset);
            if (reset) reset.checked = false;
        }

        // Typing report dates switches the period to "Custom dates".
        if (e.target.matches("[data-range-date]")) {
            const select = document.querySelector("[data-range-select]");
            if (select) select.value = "custom";
        }
    });
}
