import $ from "jquery";
import { initNav } from "./modules/nav";
import { initMenu } from "./modules/menu";
import { initAjaxForms } from "./modules/forms";
import { initReservation } from "./modules/reservation";
import { initLightbox } from "./modules/lightbox";
import { initConfirm } from "./modules/confirm";
import { initAdmin } from "./modules/admin";
import { initAdminOptions } from "./modules/admin-options";
import { initCart } from "./modules/cart";
import { initItemOptions } from "./modules/item-options";
import { initCheckout } from "./modules/checkout";
import { initOrderStatus } from "./modules/order-status";
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
    initLightbox();
    initConfirm();
    initAdmin();
    initAdminOptions();
    initCart();
    initItemOptions();
    initCheckout();
    initOrderStatus();

    const flash = document.getElementById("flash-message");
    if (flash)
        toast(
            flash.dataset.message,
            flash.dataset.type === "error" ? "error" : "success",
        );
});
