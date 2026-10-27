import $ from "jquery";
import { toast } from "./toast";

function clearErrors($form) {
    $form.find("[data-error-for]").text("");
    $form.find("[aria-invalid]").removeAttr("aria-invalid");
}

function showErrors($form, errors) {
    let first = null;
    Object.entries(errors).forEach(([field, messages]) => {
        const $input = $form.find('[name="' + field + '"]');
        $input.attr("aria-invalid", "true");
        $form.find('[data-error-for="' + field + '"]').text(messages[0]);
        if (!first && $input.length) first = $input[0];
    });
    if (first) first.focus();
}

export function initAjaxForms() {
    $(document).on("submit", "form[data-ajax-form]", function (e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('[type="submit"]');
        const genericError =
            $form.data("msgError") || "Something went wrong. Please try again.";

        clearErrors($form);
        $btn.prop("disabled", true).attr("aria-busy", "true");

        $.ajax({
            url: $form.attr("action"),
            method: "POST",
            data: $form.serialize(),
            dataType: "json",
        })
            .done((res) => {
                if (res.redirect) {
                    window.location.href = res.redirect;
                    return;
                }
                $form[0].reset();
                toast(res.message || "Done");
            })
            .fail((xhr) => {
                if (
                    xhr.status === 422 &&
                    xhr.responseJSON &&
                    xhr.responseJSON.errors
                ) {
                    showErrors($form, xhr.responseJSON.errors);
                    return;
                }
                if (xhr.status === 429) {
                    toast($form.data("msgThrottled") || genericError, "error");
                    return;
                }
                // 419 (expired session), 5xx and network failures (status 0)
                toast(genericError, "error");
            })
            .always(() => {
                $btn.prop("disabled", false).removeAttr("aria-busy");
            });
    });
}
