export function initRider() {
    const root = document.querySelector("[data-rider-poll-url]");
    if (!root) return;

    const url = root.dataset.riderPollUrl;
    const current = root.dataset.signature;

    setInterval(async () => {
        if (document.hidden) return;

        try {
            const res = await fetch(url, {
                headers: { Accept: "application/json" },
            });
            if (!res.ok) return;

            const data = await res.json();
            if (data.signature !== current) window.location.reload();
        } catch (_) {
            // Try again on the next tick.
        }
    }, 20000);
}
