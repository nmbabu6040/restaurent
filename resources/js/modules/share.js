import { toast } from "./toast";

export function initShare() {
    if (navigator.share) {
        document
            .querySelectorAll("[data-native-share-wrap]")
            .forEach((el) => el.classList.remove("hidden"));
    }

    document.addEventListener("click", async (e) => {
        const copy = e.target.closest("[data-copy-link]");
        if (copy) {
            try {
                await navigator.clipboard.writeText(copy.dataset.url);
                toast(copy.dataset.done || "Link copied");
            } catch (_) {
                window.prompt("Copy this link:", copy.dataset.url);
            }
            return;
        }

        const native = e.target.closest("[data-native-share]");
        if (native) {
            try {
                await navigator.share({
                    title: native.dataset.title,
                    url: native.dataset.url,
                });
            } catch (_) {
                // The visitor closed the share sheet.
            }
        }
    });
}
