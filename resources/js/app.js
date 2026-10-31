import $ from "jquery";
import { initNav } from "./modules/nav";
import { initMenu } from "./modules/menu";
import { initAjaxForms } from "./modules/forms";
import { initReservation } from "./modules/reservation";
import { initLightbox } from "./modules/lightbox";
import { initConfirm } from "./modules/confirm";
import { initAdmin } from "./modules/admin";
import { initAdminOptions } from "./modules/admin-options";
import { initAdminShell } from "./modules/admin-shell";
import { initCharts } from "./modules/charts";
import { initRider } from "./modules/rider";
import { initCart } from "./modules/cart";
import { initItemOptions } from "./modules/item-options";
import { initCheckout } from "./modules/checkout";
import { initOrderStatus } from "./modules/order-status";
import { initPrint } from "./modules/print";
import { initAdminAlerts } from "./modules/alerts";
import { initBackToTop } from "./modules/scroll-top";
import { initShare } from "./modules/share";
import { toast } from "./modules/toast";
import { initAdminNav } from "./modules/admin-nav";

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
    initAdminShell();
    initCharts();
    initCart();
    initItemOptions();
    initCheckout();
    initOrderStatus();
    initRider();
    initBackToTop();
    initShare();
    initPrint();
    initAdminAlerts();
    initAdminNav();

    const flash = document.getElementById("flash-message");
    if (flash)
        toast(
            flash.dataset.message,
            flash.dataset.type === "error" ? "error" : "success",
        );
});
