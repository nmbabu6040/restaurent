export function initOrderStatus() {
    const el = document.querySelector("[data-order-status-url]");
    if (!el) return;

    const url = el.dataset.orderStatusUrl;
    const current = el.dataset.status;

    const timer = setInterval(async () => {
        try {
            const res = await fetch(url, {
                headers: { Accept: "application/json" },
            });
            if (!res.ok) return;

            const data = await res.json();
            if (data.signature !== current) {
                window.location.reload();
            } else if (data.final) {
                clearInterval(timer);
            }
        } catch (_) {
            // Ignore temporary network errors and try again on the next tick.
        }
    }, 30000);
}
