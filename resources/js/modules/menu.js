import $ from "jquery";

export function initMenu() {
    const $root = $("#menu-root");
    if (!$root.length) return;

    const $grid = $("#menu-grid");
    const $form = $("#menu-filter");
    const $search = $form.find('input[name="q"]');
    const $category = $form.find('input[name="category"]');
    let xhr = null;
    let timer = null;

    const currentUrl = () => {
        const qs = $.param(
            $form.serializeArray().filter((f) => f.value !== ""),
        );
        return $form.attr("action") + (qs ? "?" + qs : "");
    };

    const setActiveCategory = (slug) => {
        $category.val(slug);
        $root.find("[data-category]").each(function () {
            $(this).attr(
                "aria-current",
                String($(this).data("category") === slug),
            );
        });
    };

    const load = (url, push = true) => {
        if (xhr) xhr.abort();
        $grid.attr("aria-busy", "true").addClass("opacity-50");

        xhr = $.ajax({
            url,
            method: "GET",
            dataType: "html",
            headers: { "X-Requested-With": "XMLHttpRequest" },
        })
            .done((html) => {
                $grid.html(html);
                if (push) history.pushState({}, "", url);
            })
            .fail((jq, status) => {
                if (status === "abort") return;
                window.location.href = url; // graceful fallback to a normal page load
            })
            .always(() => {
                $grid.attr("aria-busy", "false").removeClass("opacity-50");
            });
    };

    $search.on("input", () => {
        clearTimeout(timer);
        timer = setTimeout(() => load(currentUrl()), 300);
    });

    $form.on("submit", (e) => {
        e.preventDefault();
        clearTimeout(timer);
        load(currentUrl());
    });

    $root.on("click", "[data-category]", function (e) {
        e.preventDefault();
        setActiveCategory(String($(this).data("category")));
        load(currentUrl());
    });

    $grid.on("click", "a[data-ajax-page]", function (e) {
        e.preventDefault();
        load($(this).attr("href"));
        const reduce = window.matchMedia(
            "(prefers-reduced-motion: reduce)",
        ).matches;
        $root[0].scrollIntoView({
            behavior: reduce ? "auto" : "smooth",
            block: "start",
        });
    });

    window.addEventListener("popstate", () => {
        const params = new URLSearchParams(window.location.search);
        $search.val(params.get("q") || "");
        setActiveCategory(params.get("category") || "");
        load(window.location.href, false);
    });
}
