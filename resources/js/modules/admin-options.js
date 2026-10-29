import $ from "jquery";

function applyStockMode() {
    const select = document.querySelector("[data-stock-mode]");
    if (!select) return;

    document.querySelectorAll("[data-show-for]").forEach((el) => {
        el.classList.toggle(
            "hidden",
            !el.dataset.showFor.split(" ").includes(select.value),
        );
    });
}

export function initAdminOptions() {
    $(document).on("click", "[data-add-option]", function () {
        const field = $(this).attr("data-add-option");
        const $list = $('[data-option-list="' + field + '"]');
        const template = document.getElementById("tpl-" + field);
        if (!$list.length || !template) return;

        const index = Number($list.attr("data-next-index") || 0);
        $list.attr("data-next-index", index + 1);
        $list.append(template.innerHTML.replaceAll("__INDEX__", String(index)));
        $list
            .find("[data-option-row]")
            .last()
            .find("input, select")
            .first()
            .trigger("focus");
    });

    $(document).on("click", "[data-remove-option]", function () {
        $(this).closest("[data-option-row]").remove();
    });

    document.addEventListener("change", (e) => {
        if (e.target.matches("[data-stock-mode]")) applyStockMode();
    });
    applyStockMode();
}
