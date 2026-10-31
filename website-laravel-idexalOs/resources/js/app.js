import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

const fontFamily = "'IBM Plex Sans Arabic', 'Instrument Sans', system-ui, sans-serif";

Chart.defaults.font.family = fontFamily;
Chart.defaults.font.size = 11;
Chart.defaults.color = '#5c6859';
Chart.defaults.borderColor = 'rgba(8, 10, 8, 0.08)';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 6;
Chart.defaults.plugins.legend.labels.padding = 18;
Chart.defaults.plugins.tooltip.backgroundColor = '#080a08';
Chart.defaults.plugins.tooltip.padding = 12;
Chart.defaults.plugins.tooltip.cornerRadius = 10;
Chart.defaults.plugins.tooltip.titleFont = { family: fontFamily, weight: '600' };
Chart.defaults.plugins.tooltip.bodyFont = { family: fontFamily };

window.idexalChart = (canvas, config = {}) => {
    if (!canvas) {
        return null;
    }

    const rtl = document.documentElement.dir === 'rtl';

    // Plain ASCII digits on every axis and tooltip, whatever the page
    // language or browser locale.
    if (typeof Chart !== 'undefined') {
        Chart.defaults.locale = 'en-US';
    }

    const defaults = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: { rtl, textDirection: rtl ? 'rtl' : 'ltr' },
        },
        scales: {
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
            },
            y: {
                beginAtZero: true,
                border: { display: false },
                grid: { color: 'rgba(8, 10, 8, 0.06)' },
                ticks: { maxTicksLimit: 5, precision: 0 },
            },
        },
    };

    return new Chart(canvas, {
        ...defaults,
        ...config,
        options: {
            ...defaults.options,
            ...(config.options ?? {}),
            plugins: {
                ...defaults.plugins,
                ...(config.options?.plugins ?? {}),
            },
            scales: {
                ...defaults.scales,
                ...(config.options?.scales ?? {}),
            },
        },
    });
};

window.copyText = async (value) => {
    const text = String(value ?? '');

    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.insetInlineStart = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
    }

    window.dispatchEvent(new CustomEvent('copied'));
};

Alpine.data('copyButton', (value) => ({
    copied: false,
    copy() {
        window.copyText(value);
        this.copied = true;
        setTimeout(() => {
            this.copied = false;
        }, 1800);
    },
}));

Alpine.data('confirmSubmit', (message) => ({
    confirm(event) {
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    },
}));

document.addEventListener('DOMContentLoaded', () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const demoVideos = document.querySelectorAll('video[data-autoplay-loop]');

    // The product demo is a muted loop that only runs while it is on screen and
    // only for someone who has not asked for reduced motion. A visitor who
    // prefers reduced motion keeps the poster frame, a visitor without
    // JavaScript still sees the poster instead of a blank box, and nobody pays
    // for the download until the section is actually in view.
    const playDemo = (video) => {
        if (reducedMotion.matches) return;

        video.play().catch(() => {
            // Autoplay can be refused (battery saver, data saver). The poster
            // stays visible, which is a complete answer on its own.
        });
    };

    const demoObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) playDemo(entry.target);
                else entry.target.pause();
            });
        },
        { threshold: 0.35 },
    );

    demoVideos.forEach((video) => demoObserver.observe(video));

    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches) demoVideos.forEach((video) => video.pause());
        else demoVideos.forEach((video) => video.getBoundingClientRect().top < window.innerHeight && playDemo(video));
    });

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
    );

    document.querySelectorAll('.reveal').forEach((element) => observer.observe(element));
});

Alpine.start();
