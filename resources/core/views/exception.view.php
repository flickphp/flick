<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ heading }}</title>
    <style>
        /* Same family as exception-debug.view.php: warm neutrals (hue 55),
           coral accent (hue 27). Do not adjust to taste. No webfont loads: the
           page must stay self-contained, so the Manrope / JetBrains Mono names
           below only bind if locally installed.

           This page is what a site's own visitors see, so it carries no Flick
           branding and no theme switcher — dark mode follows the visitor's OS
           setting silently and nothing is written to their browser. */
        :root {
            --bg: oklch(98% 0.006 55);
            --card-bg: oklch(100% 0 0);
            --code-bg: oklch(96.5% 0.007 55);
            --text: oklch(24% 0.012 55);
            --text-muted: oklch(46% 0.012 55);
            --border: oklch(89% 0.007 55);
            --accent: oklch(52% 0.16 27);
            --card-shadow: 0 1px 3px oklch(0% 0 0 / 0.04);
            --font-sans: 'Manrope', ui-sans-serif, system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: oklch(22% 0.012 55);
                --card-bg: oklch(27% 0.013 55);
                --code-bg: oklch(19% 0.011 55);
                --text: oklch(93% 0.006 55);
                --text-muted: oklch(68% 0.012 55);
                --border: oklch(34% 0.014 55);
                --accent: oklch(70% 0.15 27);
                --card-shadow: none;
            }
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

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 24px 28px;
            box-shadow: var(--card-shadow);
        }

        h1 {
            font-family: var(--font-mono);
            font-size: 26px;
            font-weight: 600;
            line-height: 1.35;
            margin: 0;
            word-break: break-word;
            color: var(--accent);
        }

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
    </style>
</head>
<body>
<main>
    <section class="card">
        <h1>{{ headline }}</h1>
        <p class="message">{{ message }}</p>
        {{ help }}
        {{ code }}
        {{ link }}
    </section>
</main>
</body>
</html>
