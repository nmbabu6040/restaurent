import $ from "jquery";
import { initNav } from "./modules/nav";
import { initMenu } from "./modules/menu";
import { initAjaxForms } from "./modules/forms";
import { initReservation } from "./modules/reservation";
import { toast } from "./modules/toast";

window.$ = window.jQuery = $;

$.ajaxSetup({
    headers: {
        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        Accept: "application/json",
    },
});

$(() => {
    initNav();
    initMenu();
    initAjaxForms();
    initReservation();

    const flash = document.getElementById("flash-message");
    if (flash) toast(flash.dataset.message);
});
