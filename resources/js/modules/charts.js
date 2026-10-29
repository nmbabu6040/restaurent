const PALETTE = [
    "#6366F1",
    "#F59E0B",
    "#10B981",
    "#EF4444",
    "#06B6D4",
    "#EC4899",
    "#8B5CF6",
    "#84CC16",
];

let ChartLib = null;
let charts = [];

function themeColors() {
    const dark = document.documentElement.dataset.theme === "dark";

    return {
        text: dark ? "#C5CEDD" : "#475569",
        grid: dark ? "rgba(255,255,255,0.10)" : "rgba(15,23,42,0.08)",
        surface: dark ? "#131C2E" : "#FFFFFF",
    };
}

function formatValue(cfg, value) {
    const n = Number(value) || 0;
    const text = n.toLocaleString("en-US", {
        maximumFractionDigits: cfg.money ? 2 : 0,
    });

    return cfg.money ? `${cfg.symbol} ${text}` : text;
}

function compact(value) {
    return new Intl.NumberFormat("en-US", {
        notation: "compact",
        maximumFractionDigits: 1,
    }).format(value);
}

function build(canvas, cfg, colors) {
    const type = cfg.type;
    const horizontal = Boolean(cfg.horizontal);
    const stacked = Boolean(cfg.stacked);
    const reduceMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
    ).matches;

    const datasets = cfg.datasets.map((ds, i) => {
        const color = ds.color || PALETTE[i % PALETTE.length];

        if (type === "doughnut") {
            return {
                label: ds.label,
                data: ds.data,
                backgroundColor: cfg.labels.map(
                    (_, j) =>
                        (ds.colors && ds.colors[j]) ||
                        PALETTE[j % PALETTE.length],
                ),
                borderColor: colors.surface,
                borderWidth: 2,
            };
        }

        if (type === "line") {
            return {
                label: ds.label,
                data: ds.data,
                borderColor: color,
                backgroundColor: color + "33",
                pointBackgroundColor: color,
                fill: true,
                tension: 0.35,
                pointRadius: 3,
            };
        }

        return {
            label: ds.label,
            data: ds.data,
            backgroundColor: ds.colors && ds.colors.length ? ds.colors : color,
            borderRadius: 6,
            maxBarThickness: 36,
        };
    });

    const valueAxis = horizontal ? "x" : "y";
    const labelAxis = horizontal ? "y" : "x";

    const scales =
        type === "doughnut"
            ? {}
            : {
                  [valueAxis]: {
                      beginAtZero: true,
                      stacked,
                      ticks: {
                          color: colors.text,
                          precision: 0,
                          callback: (v) => (cfg.money ? compact(v) : v),
                      },
                      grid: { color: colors.grid },
                  },
                  [labelAxis]: {
                      stacked,
                      ticks: {
                          color: colors.text,
                          maxRotation: 0,
                          autoSkip: true,
                      },
                      grid: { display: false },
                  },
              };

    return new ChartLib(canvas, {
        type,
        data: { labels: cfg.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? "y" : "x",
            cutout: type === "doughnut" ? "62%" : undefined,
            animation: reduceMotion ? false : { duration: 600 },
            plugins: {
                legend: {
                    display: type === "doughnut" || cfg.datasets.length > 1,
                    position: "bottom",
                    labels: { color: colors.text, boxWidth: 12 },
                },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const raw =
                                type === "doughnut"
                                    ? ctx.parsed
                                    : horizontal
                                      ? ctx.parsed.x
                                      : ctx.parsed.y;
                            const name =
                                type === "doughnut"
                                    ? ctx.label
                                    : ctx.dataset.label;

                            return `${name}: ${formatValue(cfg, raw)}`;
                        },
                    },
                },
            },
            scales,
        },
    });
}

async function mount() {
    const canvases = document.querySelectorAll("canvas[data-chart]");
    if (!canvases.length) return;

    if (!ChartLib) ChartLib = (await import("chart.js/auto")).default;

    const colors = themeColors();
    ChartLib.defaults.font.family = getComputedStyle(document.body).fontFamily;
    ChartLib.defaults.color = colors.text;

    canvases.forEach((canvas) => {
        try {
            charts.push(
                build(canvas, JSON.parse(canvas.dataset.chart), colors),
            );
        } catch (_) {
            // A broken chart should never break the page.
        }
    });
}

export function initCharts() {
    mount();

    // Rebuild with matching colors when the admin switches between light and dark.
    document.addEventListener("admin:theme", () => {
        charts.forEach((chart) => chart.destroy());
        charts = [];
        mount();
    });
}
