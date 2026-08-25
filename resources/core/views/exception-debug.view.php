<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ heading }}</title>
    <script>
        // apply a saved theme choice before first paint
        try {
            var flickTheme = localStorage.getItem('flick-exception-theme');
            if (flickTheme === 'light' || flickTheme === 'dark') {
                document.documentElement.dataset.theme = flickTheme;
            }
        } catch (e) {}
    </script>
    <style>
        /* Flick exception page palette — warm neutrals (hue 55), coral accent
           (hue 27). Do not adjust to taste. No webfont loads: the page must
           stay self-contained, so the Manrope / JetBrains Mono names below
           only bind if locally installed. */
        :root {
            --bg: oklch(98% 0.006 55);
            --panel-bg: oklch(99% 0.004 55);
            --card-bg: oklch(100% 0 0);
            --code-bg: oklch(96.5% 0.007 55);
            --text: oklch(24% 0.012 55);
            --text-muted: oklch(46% 0.012 55);
            --text-faint: oklch(58% 0.012 55);
            --border: oklch(89% 0.007 55);
            --border-strong: oklch(80% 0.009 55);
            --accent: oklch(52% 0.16 27);
            --accent-soft: oklch(94% 0.03 27);
            --btn-bg: oklch(95% 0.006 55);
            --btn-bg-active: oklch(90% 0.008 55);
            --card-shadow: 0 1px 3px oklch(0% 0 0 / 0.04);
            --font-sans: 'Manrope', ui-sans-serif, system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace;
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --bg: oklch(22% 0.012 55);
                --panel-bg: oklch(26% 0.013 55);
                --card-bg: oklch(27% 0.013 55);
                --code-bg: oklch(19% 0.011 55);
                --text: oklch(93% 0.006 55);
                --text-muted: oklch(68% 0.012 55);
                --text-faint: oklch(52% 0.012 55);
                --border: oklch(34% 0.014 55);
                --border-strong: oklch(42% 0.015 55);
                --accent: oklch(70% 0.15 27);
                --accent-soft: oklch(32% 0.05 27);
                --btn-bg: oklch(30% 0.013 55);
                --btn-bg-active: oklch(40% 0.02 55);
                --card-shadow: none;
            }
        }

        :root[data-theme="dark"] {
            --bg: oklch(22% 0.012 55);
            --panel-bg: oklch(26% 0.013 55);
            --card-bg: oklch(27% 0.013 55);
            --code-bg: oklch(19% 0.011 55);
            --text: oklch(93% 0.006 55);
            --text-muted: oklch(68% 0.012 55);
            --text-faint: oklch(52% 0.012 55);
            --border: oklch(34% 0.014 55);
            --border-strong: oklch(42% 0.015 55);
            --accent: oklch(70% 0.15 27);
            --accent-soft: oklch(32% 0.05 27);
            --btn-bg: oklch(30% 0.013 55);
            --btn-bg-active: oklch(40% 0.02 55);
            --card-shadow: none;
        }

        * { box-sizing: border-box; }

        @keyframes flickFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-sans);
            transition: background-color .2s, color .2s;
            animation: flickFadeIn .4s ease;
        }

        main {
            max-width: 880px;
            margin: 0 auto;
            padding: 48px 24px 80px;
        }

        .page-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 36px;
        }

        .wordmark {
            font-family: var(--font-mono);
            font-weight: 600;
            font-size: 16px;
        }

        .wordmark span { color: var(--accent); }

        .wordmark svg {
            height: 30px;
            width: auto;
            display: block;
        }

        .theme-toggle {
            display: flex;
            gap: 2px;
            background: var(--btn-bg);
            padding: 3px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .theme-toggle button {
            border: none;
            border-radius: 6px;
            padding: 5px 12px;
            font-size: 12.5px;
            font-family: var(--font-sans);
            font-weight: 600;
            cursor: pointer;
            color: var(--text-muted);
            background: transparent;
        }

        .theme-toggle button.active {
            background: var(--btn-bg-active);
            color: var(--text);
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 24px 28px;
            box-shadow: var(--card-shadow);
        }

        .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .eyebrow {
            display: inline-block;
            font-family: var(--font-mono);
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent);
            background: var(--accent-soft);
            padding: 4px 9px;
            border-radius: 5px;
        }

        .copy-btn {
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            font-family: var(--font-sans);
            font-size: 12.5px;
            font-weight: 600;
            padding: 5px 11px;
            border-radius: 6px;
            cursor: pointer;
        }

        h1 {
            font-family: var(--font-mono);
            font-size: 26px;
            font-weight: 600;
            line-height: 1.35;
            margin: 16px 0 0;
            word-break: break-word;
            color: var(--accent);
        }

        h1 .ns { color: var(--text-muted); }

        .message {
            font-size: 17px;
            line-height: 1.6;
            margin: 14px 0 0;
        }

        .message code, .help code {
            font-family: var(--font-mono);
            font-size: 0.88em;
            background: var(--code-bg);
            color: var(--accent);
            padding: 1px 6px;
            border-radius: 4px;
        }

        .help {
            font-size: 15px;
            line-height: 1.6;
            margin: 14px 0 0;
            color: var(--text-muted);
        }

        .code {
            background: var(--code-bg);
            font-family: var(--font-mono);
            font-size: 13.5px;
            line-height: 1.7;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0 0;
            white-space: pre;
            overflow-x: auto;
        }

        .link { margin-top: 20px; font-size: 14px; }

        .link a {
            color: var(--accent);
            text-decoration: none;
        }

        .link a:hover { text-decoration: underline; }

        .file-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            font-family: var(--font-mono);
            font-size: 13px;
            color: var(--text-muted);
        }

        .file-row .sep { color: var(--border-strong); }

        .panel {
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-top: 20px;
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            border-bottom: 1px solid var(--border);
            font-family: var(--font-mono);
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .panel-header .faint { color: var(--text-faint); }

        .excerpt {
            font-family: var(--font-mono);
            font-size: 14px;
            line-height: 1.7;
            overflow-x: auto;
            padding: 10px 0;
        }

        .excerpt-row {
            display: flex;
            padding: 1px 20px 1px 17px;
            border-left: 3px solid transparent;
        }

        .excerpt-row.hl {
            background: var(--accent-soft);
            border-left-color: var(--accent);
        }

        .gutter {
            color: var(--text-faint);
            min-width: 34px;
            text-align: right;
            user-select: none;
            flex-shrink: 0;
        }

        .excerpt-text {
            white-space: pre;
            padding-left: 16px;
        }

        .frame { padding: 14px 20px; }

        .frame + .frame { border-top: 1px solid var(--border); }

        #more-frames .frame { border-top: 1px solid var(--border); }

        .frame-call {
            font-family: var(--font-mono);
            font-size: 13.5px;
            font-weight: 500;
            word-break: break-word;
        }

        .frame-loc {
            font-family: var(--font-mono);
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 3px;
            word-break: break-word;
        }

        .more-btn {
            width: 100%;
            border: none;
            border-top: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            font-family: var(--font-sans);
            font-size: 12.5px;
            font-weight: 600;
            padding: 10px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<main>
    <div class="page-top">
        <div class="wordmark">{{ logo }}</div>
        <div class="theme-toggle" id="theme-toggle">
            <button type="button" data-mode="system">Auto</button>
            <button type="button" data-mode="light">Light</button>
            <button type="button" data-mode="dark">Dark</button>
        </div>
    </div>

    <section class="card">
        <div class="card-top">
            <span class="eyebrow">Uncaught Exception</span>
            <button type="button" class="copy-btn" id="copy-btn" data-copy="{{ copyText }}">Copy</button>
        </div>
        <h1>{{ headline }}</h1>
        <p class="message">{{ message }}</p>
        {{ help }}
        {{ code }}
        {{ link }}
        {{ fileRow }}
    </section>

    {{ excerpt }}
    {{ trace }}
</main>
<script>
    (function () {
        var toggle = document.getElementById('theme-toggle');
        var buttons = toggle.querySelectorAll('button');

        function sync() {
            var mode = document.documentElement.dataset.theme || 'system';
            buttons.forEach(function (b) {
                b.classList.toggle('active', b.dataset.mode === mode);
            });
        }

        buttons.forEach(function (b) {
            b.addEventListener('click', function () {
                var mode = b.dataset.mode;
                if (mode === 'system') {
                    delete document.documentElement.dataset.theme;
                } else {
                    document.documentElement.dataset.theme = mode;
                }
                try {
                    if (mode === 'system') {
                        localStorage.removeItem('flick-exception-theme');
                    } else {
                        localStorage.setItem('flick-exception-theme', mode);
                    }
                } catch (e) {}
                sync();
            });
        });

        sync();
    })();

    (function () {
        var btn = document.getElementById('copy-btn');

        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy');

            function done() {
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1600);
            }

            function fallback() {
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                ta.remove();
            }

            // the clipboard API needs a secure context; plain-http dev sites
            // fall back to the textarea trick
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done, function () { fallback(); done(); });
            } else {
                fallback();
                done();
            }
        });
    })();

    (function () {
        var btn = document.getElementById('more-frames-btn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            document.getElementById('more-frames').hidden = false;
            btn.remove();
        });
    })();
</script>
</body>
</html>
