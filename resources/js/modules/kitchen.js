import $ from "jquery";

const KEY = "kitchen_sound";
let audio = null;

const readSetting = () => {
    try {
        return localStorage.getItem(KEY) === "on";
    } catch (_) {
        return false;
    }
};

const saveSetting = (on) => {
    try {
        localStorage.setItem(KEY, on ? "on" : "off");
    } catch (_) {
        // Private mode: the choice lasts for this page only.
    }
};

function unlock() {
    const Context = window.AudioContext || window.webkitAudioContext;
    if (!Context) return;

    audio = audio || new Context();
    if (audio.state === "suspended") audio.resume();
}

function beep() {
    if (!audio || audio.state !== "running") return;

    [
        [988, 0],
        [1319, 0.18],
        [988, 0.36],
    ].forEach(([frequency, delay]) => {
        const oscillator = audio.createOscillator();
        const gain = audio.createGain();
        const start = audio.currentTime + delay;

        oscillator.type = "square";
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.25, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.16);
        oscillator.connect(gain);
        gain.connect(audio.destination);
        oscillator.start(start);
        oscillator.stop(start + 0.18);
    });
}

export function initKitchen() {
    const $board = $("#kitchen-board");
    if (!$board.length) return;

    const url = $board.data("url");
    let known = new Set(
        $board
            .find("[data-ticket]")
            .map((_, el) => String(el.dataset.ticket))
            .get(),
    );
    let on = readSetting();

    const render = () => {
        $("[data-kitchen-sound-label]").text("Sound: " + (on ? "on" : "off"));
        $("[data-kitchen-sound]").attr("aria-pressed", String(on));
    };
    render();

    $("[data-kitchen-sound]").on("click", () => {
        on = !on;
        saveSetting(on);
        if (on) {
            unlock();
            beep();
        }
        render();
    });
    $(document).on("click", () => {
        if (on) unlock();
    });

    const apply = (res) => {
        const ids = res.ids.map(String);
        const fresh = ids.some((id) => !known.has(id));

        $board.html(res.html);
        $("[data-ticket-count]").text(ids.length);

        if (fresh && on) beep();
        known = new Set(ids);
    };

    $(document).on("click", "[data-kitchen-done]", function () {
        const $button = $(this).prop("disabled", true);

        $.ajax({ url: $button.data("url"), method: "POST", dataType: "json" })
            .done(apply)
            .fail((xhr) => {
                $button.prop("disabled", false);
                if (xhr.status === 422) $.getJSON(url).done(apply); // someone else already did it: show the current board
            });
    });

    setInterval(() => {
        $.getJSON(url).done(apply);
    }, 8000);
}
