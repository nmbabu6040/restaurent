import { toast } from "./toast";

const KEY = "admin_sound";
let audio = null;

function readSetting() {
    try {
        return localStorage.getItem(KEY) === "on";
    } catch (_) {
        return false;
    }
}

function saveSetting(on) {
    try {
        localStorage.setItem(KEY, on ? "on" : "off");
    } catch (_) {
        // Private mode: the choice just lasts for this page.
    }
}

/** Browsers only allow sound after the person has clicked something on the page. */
function unlock() {
    const Context = window.AudioContext || window.webkitAudioContext;
    if (!Context) return;

    audio = audio || new Context();
    if (audio.state === "suspended") audio.resume();
}

function beep() {
    if (!audio || audio.state !== "running") return;

    [
        [880, 0],
        [1175, 0.22],
        [880, 0.44],
    ].forEach(([frequency, delay]) => {
        const oscillator = audio.createOscillator();
        const gain = audio.createGain();
        const start = audio.currentTime + delay;

        oscillator.type = "sine";
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.4, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.18);

        oscillator.connect(gain);
        gain.connect(audio.destination);
        oscillator.start(start);
        oscillator.stop(start + 0.2);
    });
}

export function initAdminAlerts() {
    const root = document.querySelector("[data-alert-url]");
    if (!root) return;

    const url = root.dataset.alertUrl;
    const button = document.querySelector("[data-sound-toggle]");
    const label = document.querySelector("[data-sound-label]");
    const originalTitle = document.title;
    let on = readSetting();
    let seen = null;

    const render = () => {
        if (label)
            label.textContent = "New order alert sound: " + (on ? "on" : "off");
        if (button) button.setAttribute("aria-pressed", String(on));
    };
    render();

    button?.addEventListener("click", () => {
        on = !on;
        saveSetting(on);
        if (on) {
            unlock();
            beep();
        }
        render();
    });

    document.addEventListener("click", () => {
        if (on) unlock();
    });

    window.addEventListener("focus", () => {
        document.title = originalTitle;
    });

    const notify = (message) => {
        toast(message);
        document.title = "🔔 " + message + " | " + originalTitle;
        if (on) beep();
    };

    const check = async () => {
        try {
            const res = await fetch(url, {
                headers: { Accept: "application/json" },
            });
            if (!res.ok) return;

            const data = await res.json();

            if (seen !== null) {
                if (data.latest > seen.latest || data.orders > seen.orders) {
                    notify("New order received");
                } else if (
                    data.reservation_latest > seen.reservation_latest ||
                    data.reservations > seen.reservations
                ) {
                    notify("New reservation request");
                }
            }

            seen = data;
        } catch (_) {
            // Try again on the next round.
        }
    };

    check();
    setInterval(check, 20000);
}
