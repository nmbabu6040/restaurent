export function initConfirm() {
    const dialog = document.getElementById("confirm-dialog");
    if (!dialog) return;

    const text = dialog.querySelector("[data-confirm-text]");
    let pending = null;

    document.addEventListener("submit", (e) => {
        const form = e.target.closest("form[data-confirm]");
        if (!form) return;
        e.preventDefault();
        pending = form;
        text.textContent = form.dataset.confirm;
        dialog.showModal();
    });

    dialog.addEventListener("click", (e) => {
        if (e.target.closest("[data-confirm-yes]")) {
            dialog.close();
            if (pending) HTMLFormElement.prototype.submit.call(pending);
        } else if (
            e.target === dialog ||
            e.target.closest("[data-confirm-no]")
        ) {
            dialog.close();
        }
    });
}
