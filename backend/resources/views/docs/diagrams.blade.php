<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DocSign Hub — diagrams</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            font: 16px/1.6 -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: #f7f8fa;
            color: #1c1e21;
        }
        .wrap { max-width: 960px; margin: 0 auto; padding: 32px 20px 80px; }
        h1, h2 { line-height: 1.25; }
        h1 { font-size: 1.8rem; }
        h2 { margin-top: 2.2rem; border-bottom: 1px solid #e3e5e8; padding-bottom: .3rem; }
        code { background: #eceef0; padding: .1em .35em; border-radius: 4px; font-size: .9em; }
        pre.code { background: #1c1e21; color: #e6e6e6; padding: 14px; border-radius: 8px; overflow-x: auto; }
        pre.mermaid { background: #fff; border: 1px solid #e3e5e8; border-radius: 8px; padding: 16px; text-align: center; }
        a { color: #2563eb; }
        @media (prefers-color-scheme: dark) {
            body { background: #16181c; color: #e6e6e6; }
            h2 { border-color: #2a2d33; }
            code { background: #2a2d33; }
            pre.mermaid { background: #1c1e21; border-color: #2a2d33; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div id="content"></div>
        <textarea id="src" hidden>{{ $markdown }}</textarea>
    </div>

    <script type="module">
        import { marked } from 'https://cdn.jsdelivr.net/npm/marked@14/lib/marked.esm.js';
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';

        const theme = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'default';
        mermaid.initialize({ startOnLoad: false, theme });

        // Блоки ```mermaid отдаём mermaid.js как есть (без HTML-эскейпа), остальной код — экранируем.
        marked.use({
            renderer: {
                code(code, infostring) {
                    const text = (code && typeof code === 'object') ? code.text : code;
                    const lang = (code && typeof code === 'object') ? code.lang : infostring;
                    if ((lang || '').trim() === 'mermaid') {
                        return `<pre class="mermaid">${text}</pre>`;
                    }
                    const esc = String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;');
                    return `<pre class="code">${esc}</pre>`;
                },
            },
        });

        document.getElementById('content').innerHTML = marked.parse(document.getElementById('src').value);
        await mermaid.run({ querySelector: 'pre.mermaid' });
    </script>
</body>
</html>
