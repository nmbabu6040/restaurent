import $ from "jquery";

export function initCheckout() {
    const $form = $("#checkout-form");
    if (!$form.length) return;

    const totals = $form.data("totals") || {};

    const update = () => {
        const checked = $form.find('input[name="type"]:checked').val();
        const type = checked || $form.find('input[name="type"]').first().val();
        const t = totals[type];
        if (!t) return;

        $("#sum-fee").text(t.fee);
        $("#sum-total").text(t.total);

        const delivery = type === "delivery";
        $("#address-wrap").toggleClass("hidden", !delivery);
        $form.find('[name="address"]').prop("required", delivery);
    };

    $form.on("change", 'input[name="type"]', update);
    update();
}
