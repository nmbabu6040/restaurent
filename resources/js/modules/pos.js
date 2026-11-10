import $ from "jquery";
import { toast } from "./toast";

const fail = (xhr) =>
    toast(
        (xhr.responseJSON && xhr.responseJSON.message) ||
            "Something went wrong. Please try again.",
        "error",
    );

function initPoll() {
    const root = document.querySelector("[data-pos-poll-url]");
    if (!root) return;

    setInterval(async () => {
        if (document.hidden) return;

        try {
            const res = await fetch(root.dataset.posPollUrl, {
                headers: { Accept: "application/json" },
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data.signature !== root.dataset.signature)
                window.location.reload();
        } catch (_) {
            // Try again on the next round.
        }
    }, 15000);
}

function initOrderScreen() {
    const $root = $("#pos-root");
    if (!$root.length) return;

    const addUrl = $root.data("addUrl");
    const $ticket = $("#pos-ticket");
    const dialog = document.getElementById("pos-options");
    const swap = (res) => {
        if (res && res.html !== undefined) $ticket.html(res.html);
    };
    const call = (url, method, data) =>
        $.ajax({ url, method, data, dataType: "json" }).done(swap).fail(fail);

    // Search and category filter happen in the browser, the menu is already on the page.
    let category = "";
    const filter = () => {
        const q = String($("[data-pos-search]").val() || "").toLowerCase();

        $("[data-pos-item]").each(function () {
            const show =
                (!category || this.dataset.cat === category) &&
                (!q || this.dataset.name.toLowerCase().includes(q));
            this.classList.toggle("hidden", !show);
        });
    };
    $("[data-pos-search]").on("input", filter);
    $(document).on("click", "[data-pos-cat]", function () {
        category = this.dataset.posCat || "";
        $("[data-pos-cat]").attr("aria-current", "false");
        this.setAttribute("aria-current", "true");
        filter();
    });

    const openDialog = (el, options) => {
        const form = document.getElementById("pos-options-form");
        form.querySelector('[name="item_id"]').value = el.dataset.id;
        form.querySelector('[name="quantity"]').value = 1;
        document.getElementById("pos-options-title").textContent =
            el.dataset.name;

        const body = form.querySelector("[data-pos-options-body]");
        body.replaceChildren();

        const group = (legendText, inputs) => {
            const fieldset = document.createElement("fieldset");
            const legend = document.createElement("legend");
            legend.className = "font-medium text-navy";
            legend.textContent = legendText;
            fieldset.append(legend, ...inputs);
            body.append(fieldset);
        };

        const row = (type, name, value, text, checked) => {
            const label = document.createElement("label");
            label.className = "mt-2 flex items-center gap-2";
            const input = document.createElement("input");
            input.type = type;
            input.name = name;
            input.value = value;
            input.checked = checked;
            input.className = "accent-navy";
            label.append(input, document.createTextNode(text));
            return label;
        };

        if ((options.variations || []).length) {
            group(
                "Size",
                options.variations.map((v, i) =>
                    row(
                        "radio",
                        "variation_id",
                        v.id,
                        `${v.name} (${v.price})`,
                        i === 0,
                    ),
                ),
            );
        }
        if ((options.addons || []).length) {
            group(
                "Extras",
                options.addons.map((a) =>
                    row(
                        "checkbox",
                        "addons[]",
                        a.id,
                        `${a.name} (+${a.price})`,
                        false,
                    ),
                ),
            );
        }

        dialog.showModal();
    };

    $(document).on("click", "[data-pos-item]", function () {
        const options = JSON.parse(this.dataset.options || "{}");
        const hasOptions =
            (options.variations || []).length || (options.addons || []).length;

        if (hasOptions) {
            openDialog(this, options);
        } else {
            call(addUrl, "POST", { item_id: this.dataset.id, quantity: 1 });
        }
    });

    $("#pos-options-form").on("submit", function (e) {
        e.preventDefault();
        call(addUrl, "POST", $(this).serialize());
        dialog.close();
    });
    $("[data-pos-options-close]").on("click", () => dialog.close());

    // Ticket buttons are replaced after every change, so they are handled on the document.
    $(document).on("click", "[data-line-set]", function () {
        call($(this).data("url"), "PATCH", { quantity: this.dataset.qty });
    });
    $(document).on("click", "[data-line-remove]", function () {
        call($(this).data("url"), "DELETE");
    });
    $(document).on("submit", "form[data-pos-form]", function (e) {
        e.preventDefault();
        call($(this).attr("action"), "POST", $(this).serialize());
    });
    // The kitchen ticket opens in a new tab; refresh this page so the "sent" marks show.
    $(document).on("submit", "form[data-pos-send]", () => {
        setTimeout(() => window.location.reload(), 1500);
    });
}

function initPayScreen() {
    const $form = $("#pos-pay-form");
    if (!$form.length) return;

    const due = parseFloat($form.attr("data-due")) || 0;
    const symbol = $form.attr("data-symbol") || "";
    const $rows = $("#pay-rows");
    const template = document.getElementById("pay-row-tpl");
    let next = $rows.children().length;
    const fmt = (n) =>
        `${symbol} ${n.toLocaleString("en-US", { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;

    const recalc = () => {
        let paid = 0;
        let change = 0;

        $rows.children().each(function () {
            const $r = $(this);
            const amount = parseFloat($r.find("[data-pay-amount]").val()) || 0;
            const cash = $r.find("[data-pay-method]").val() === "cash";
            paid += amount;
            $r.find("[data-tender-wrap]").toggleClass("hidden", !cash);

            const tendered = parseFloat($r.find("[data-pay-tendered]").val());
            if (cash && !Number.isNaN(tendered) && tendered > amount)
                change += tendered - amount;
        });

        $("[data-pay-remaining]").text(
            fmt(Math.max(0, Math.round((due - paid) * 100) / 100)),
        );
        $("[data-pay-change]").text(fmt(Math.round(change * 100) / 100));
    };

    $form.on("input change", "input, select", recalc);
    $("#pay-add").on("click", () => {
        $rows.append(
            template.innerHTML.replaceAll("__INDEX__", String(next++)),
        );
        recalc();
    });
    $form.on("click", "[data-pay-remove]", function () {
        if ($rows.children().length > 1)
            $(this).closest("[data-pay-row]").remove();
        recalc();
    });
    $form.on("click", "[data-pay-fill]", function () {
        const $row = $(this).closest("[data-pay-row]");
        let others = 0;
        $rows
            .children()
            .not($row)
            .each(function () {
                others +=
                    parseFloat($(this).find("[data-pay-amount]").val()) || 0;
            });
        $row.find("[data-pay-amount]").val(
            Math.max(0, Math.round((due - others) * 100) / 100).toFixed(2),
        );
        recalc();
    });

    recalc();
}

export function initPos() {
    initPoll();
    initOrderScreen();
    initPayScreen();
}
