export function initPrint() {
    document.addEventListener("click", (e) => {
        if (e.target.closest("[data-print]")) window.print();
    });

    if (document.body.dataset.autoprint === "1") {
        window.addEventListener("load", () =>
            setTimeout(() => window.print(), 300),
        );
    }
}
