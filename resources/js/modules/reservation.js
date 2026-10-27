import $ from "jquery";
import { toast } from "./toast";

export function initReservation() {
    const $form = $("#reservation-form");
    if (!$form.length) return;

    const $date = $form.find('[name="date"]');
    const $party = $form.find('[name="party_size"]');
    const $time = $form.find('[name="time"]');
    const url = $form.data("availabilityUrl");
    let xhr = null;

    const refresh = () => {
        const date = $date.val();
        const party = $party.val();
        if (!date || !party) return;

        const previous = $time.val() || $time.data("old") || "";
        if (xhr) xhr.abort();
        $time.prop("disabled", true);

        xhr = $.getJSON(url, { date, party_size: party })
            .done((res) => {
                let any = false;
                $time.empty().append(new Option($form.data("msgChoose"), ""));
                res.slots.forEach((slot) => {
                    const label = slot.available
                        ? slot.label
                        : slot.label + " - " + $form.data("msgFull");
                    const option = new Option(label, slot.time);
                    option.disabled = !slot.available;
                    if (slot.available) any = true;
                    $time.append(option);
                });
                $time.val(previous);
                if (!any) toast($form.data("msgNone"), "error");
            })
            .fail((jq, status) => {
                if (status !== "abort")
                    toast($form.data("msgLoadFailed"), "error");
            })
            .always(() => {
                $time.prop("disabled", false);
            });
    };

    $date.on("change", refresh);
    $party.on("change", refresh);
    if ($date.val()) refresh();
}
