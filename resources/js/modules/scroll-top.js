export function initBackToTop() {
    const wrap = document.querySelector("[data-back-to-top-wrap]");
    if (!wrap) return;

    const update = () => wrap.classList.toggle("hidden", window.scrollY < 400);
    window.addEventListener("scroll", update, { passive: true });
    update();

    wrap.querySelector("button").addEventListener("click", () => {
        const reduce = window.matchMedia(
            "(prefers-reduced-motion: reduce)",
        ).matches;
        window.scrollTo({ top: 0, behavior: reduce ? "auto" : "smooth" });
        document.getElementById("main")?.focus({ preventScroll: true });
    });
}
