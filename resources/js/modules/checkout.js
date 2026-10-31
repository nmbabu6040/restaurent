import $ from "jquery";

export function initCheckout() {
    const $form = $("#checkout-form");
    if (!$form.length) return;

    const totals = $form.data("totals") || {};
    const zones = $form.data("zones") || null; // null when the restaurant has no delivery areas

    const update = () => {
        const checked = $form.find('input[name="type"]:checked').val();
        const type = checked || $form.find('input[name="type"]').first().val();
        const delivery = type === "delivery";
        const useZones = delivery && zones !== null;

        $("#address-wrap").toggleClass("hidden", !delivery);
        $form.find('[name="address"]').prop("required", delivery);
        $form.find('[name="delivery_zone_id"]').prop("required", useZones);

        if (useZones) {
            const zone = zones[$form.find('[name="delivery_zone_id"]').val()];
            $("#sum-fee").text(zone ? zone.fee : "—");
            $("#sum-total").text(zone ? zone.total : "—");
            $("#zone-note").text(zone && zone.note ? zone.note : "");

            return;
        }

        const t = totals[type];
        if (!t) return;

        $("#sum-fee").text(t.fee);
        $("#sum-total").text(t.total);
        $("#zone-note").text("");
    };

    $form.on("change", 'input[name="type"], [name="delivery_zone_id"]', update);
    update();
}
