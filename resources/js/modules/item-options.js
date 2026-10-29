import $ from "jquery";

export function initItemOptions() {
    const $form = $("form[data-item-options]");
    if (!$form.length) return;

    const base = parseFloat($form.attr("data-base")) || 0;
    const symbol = $form.attr("data-currency") || "";

    const format = (n) => {
        const whole = Math.abs(n - Math.round(n)) < 0.005;
        return (
            symbol +
            " " +
            n.toLocaleString("en-US", {
                minimumFractionDigits: whole ? 0 : 2,
                maximumFractionDigits: 2,
            })
        );
    };

    const update = () => {
        const $variation = $form.find('input[name="variation_id"]:checked');
        let unit = $variation.length
            ? parseFloat($variation.attr("data-price")) || 0
            : base;

        $form.find('input[name="addons[]"]:checked').each(function () {
            unit += parseFloat($(this).attr("data-price")) || 0;
        });

        const qty = Math.max(
            1,
            parseInt($form.find('[name="quantity"]').val(), 10) || 1,
        );
        $("#option-total").text(format(unit * qty));
    };

    $form.on("change input", "input", update);
    update();
}
