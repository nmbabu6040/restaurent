const KEY = "admin_nav_groups";

function load() {
    try {
        return JSON.parse(localStorage.getItem(KEY) || "{}");
    } catch (_) {
        return {};
    }
}

function save(state) {
    try {
        localStorage.setItem(KEY, JSON.stringify(state));
    } catch (_) {
        // Private mode: the open and closed groups are just not remembered.
    }
}

export function initAdminNav() {
    const nav = document.querySelector("[data-admin-nav]");
    if (!nav) return;

    const groups = [...nav.querySelectorAll("details[data-nav-group]")];
    const filter = document.querySelector("[data-nav-filter]");
    const state = load();
    let filtering = false;

    // Remember what was open, but a group with the current page in it always stays open.
    groups.forEach((group) => {
        const slug = group.dataset.navGroup;
        if (!group.querySelector('[aria-current="page"]') && slug in state)
            group.open = state[slug];
        group.addEventListener("toggle", () => {
            if (filtering) return;
            state[slug] = group.open;
            save(state);
        });
    });

    if (!filter) return;

    filter.addEventListener("input", () => {
        const query = filter.value.trim().toLowerCase();
        filtering = query !== "";

        groups.forEach((group) => {
            let matches = 0;
            group.querySelectorAll("[data-nav-item]").forEach((item) => {
                const hit = !filtering || item.dataset.navItem.includes(query);
                item.classList.toggle("hidden", !hit);
                if (hit) matches++;
            });

            group.classList.toggle("hidden", filtering && matches === 0);
            group.open = filtering
                ? matches > 0
                : Boolean(group.querySelector('[aria-current="page"]')) ||
                  (state[group.dataset.navGroup] ?? group.open);
        });
    });

    // Press "/" anywhere to jump to the menu search.
    document.addEventListener("keydown", (e) => {
        if (
            e.key === "/" &&
            !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)
        ) {
            e.preventDefault();
            filter.focus();
        }
    });
}
