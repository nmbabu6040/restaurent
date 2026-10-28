import $ from "jquery";
import { toast } from "./toast";

export function initAdmin() {
    $(document).on("change", "input[data-toggle-url]", function () {
        const $box = $(this);
        const checked = $box.is(":checked");

        $box.prop("disabled", true);

        $.ajax({
            url: $box.data("toggleUrl"),
            method: "PATCH",
            data: { is_available: checked ? 1 : 0 },
            dataType: "json",
        })
            .done((res) => toast(res.message))
            .fail(() => {
                $box.prop("checked", !checked);
                toast("Could not update. Please try again.", "error");
            })
            .always(() => $box.prop("disabled", false));
    });
}
