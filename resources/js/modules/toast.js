let region;

function ensureRegion() {
    if (region) return region;
    region = document.createElement("div");
    region.setAttribute("role", "status");
    region.setAttribute("aria-live", "polite");
    region.className =
        "fixed bottom-4 right-4 z-50 flex max-w-sm flex-col gap-2";
    document.body.appendChild(region);
    return region;
}

export function toast(message, type = "success") {
    const el = document.createElement("div");
    el.className =
        "rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg " +
        (type === "error" ? "bg-red-700" : "bg-navy");
    el.textContent = message;
    ensureRegion().appendChild(el);
    setTimeout(() => el.remove(), 6000);
}
