export function initPrint() {
    document.addEventListener("click", (e) => {
        if (e.target.closest("[data-print]")) window.print();
    });

    if (document.body.dataset.autoprint === "1") {
        window.addEventListener("load", () =>
            setTimeout(() => window.print(), 300),
        );

        // A ticket or bill that opened by itself closes by itself once it has printed (browsers may refuse; then close it by hand).
        window.addEventListener("afterprint", () =>
            setTimeout(() => window.close(), 200),
        );
    }
}
