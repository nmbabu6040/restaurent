import $ from "jquery";
import { toast } from "./toast";

const fallbackMessage = () =>
    document.body.dataset.cartError ||
    "Something went wrong. Please try again.";

function setCount(count) {
    $("[data-cart-count]")
        .text(count)
        .toggleClass("hidden", Number(count) === 0);
}

function errorMessage(xhr) {
    const errors = xhr.responseJSON && xhr.responseJSON.errors;
    if (xhr.status === 422 && errors) {
        const first = Object.values(errors)[0];
        if (first && first[0]) return first[0];
    }
    return fallbackMessage();
}

function sendCartRequest(url, method, data) {
    return $.ajax({ url, method, data, dataType: "json" }).done((res) => {
        setCount(res.count);
        if (res.html !== undefined) $("#cart-root").html(res.html);
        if (res.message) toast(res.message);
    });
}

export function initCart() {
    $(document).on("submit", "form[data-cart-add]", function (e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('[type="submit"]');
        $btn.prop("disabled", true);

        $.ajax({
            url: $form.attr("action"),
            method: "POST",
            data: $form.serialize(),
            dataType: "json",
        })
            .done((res) => {
                setCount(res.count);
                toast(res.message);
            })
            .fail((xhr) => toast(errorMessage(xhr), "error"))
            .always(() => $btn.prop("disabled", false));
    });

    $(document).on("change", "input[data-cart-qty]", function () {
        const $input = $(this);
        let qty = parseInt($input.val(), 10);
        if (Number.isNaN(qty) || qty < 0) qty = 0;

        $input.prop("disabled", true);
        sendCartRequest($input.data("url"), "PATCH", { quantity: qty }).fail(
            (xhr) => {
                $input.prop("disabled", false);
                toast(errorMessage(xhr), "error");
            },
        );
    });

    $(document).on("click", "[data-qty-delta]", function () {
        const $input = $(this).siblings("input[data-cart-qty]");
        const max = parseInt($input.attr("max"), 10) || 20;
        const next = Math.max(
            0,
            Math.min(
                max,
                (parseInt($input.val(), 10) || 0) +
                    Number($(this).data("qtyDelta")),
            ),
        );
        $input.val(next).trigger("change");
    });

    $(document).on("submit", "form[data-cart-remove]", function (e) {
        e.preventDefault();
        sendCartRequest($(this).attr("action"), "DELETE").fail((xhr) =>
            toast(errorMessage(xhr), "error"),
        );
    });

    $(document).on("submit", "form[data-cart-coupon]", function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('[type="submit"]').prop("disabled", true);

        sendCartRequest($form.attr("action"), "POST", $form.serialize()).fail(
            (xhr) => {
                $form.find("#coupon-code-error").text(errorMessage(xhr));
                $btn.prop("disabled", false);
            },
        );
    });

    $(document).on("submit", "form[data-cart-coupon-remove]", function (e) {
        e.preventDefault();
        sendCartRequest($(this).attr("action"), "DELETE").fail((xhr) =>
            toast(errorMessage(xhr), "error"),
        );
    });
}
