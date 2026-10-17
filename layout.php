<?php
// Shared top and bottom of the log-in and sign-up pages.
// Same look and the same light/dark behavior as the main page.

function page_head(string $title): void
{ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - AI SLOP</title>
    <style>
        :root { --bg: #fff; --fg: #000; --line: #ccc; }
        :root.dark { --bg: #121212; --fg: #e8e8e8; --line: #444; color-scheme: dark; }
        body { background: var(--bg); color: var(--fg); }
        :root.dark a { color: #8ab4f8; }
        h2 { text-align: center; }
        form.auth { max-width: 360px; margin: 0 auto 1.5rem; padding: 0 12px; }
        form.auth label { display: block; margin-top: 12px; }
        form.auth input { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--bg); color: var(--fg); font: inherit; }
        form.auth button { margin-top: 16px; width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--fg); color: var(--bg); font: inherit; cursor: pointer; }
        ul.errors { max-width: 360px; margin: 0 auto 1rem; color: #c0392b; }
        :root.dark ul.errors { color: #ff7b72; }
    </style>
    <script>
        (function () {
            var mq = window.matchMedia("(prefers-color-scheme: dark)");
            function apply() {
                var saved = null;
                try { saved = localStorage.getItem("theme"); } catch (e) {}
                var hour = new Date().getHours();
                var night = hour >= 19 || hour < 6;
                document.documentElement.classList.toggle("dark", saved ? saved === "dark" : (night || mq.matches));
            }
            function scheduleNext() {
                var now = new Date(), next = new Date(now), h = now.getHours();
                next.setMinutes(0, 0, 0);
                if (h < 6) next.setHours(6);
                else if (h < 19) next.setHours(19);
                else { next.setDate(next.getDate() + 1); next.setHours(6); }
                setTimeout(function () { apply(); scheduleNext(); }, next - now);
            }
            apply();
            scheduleNext();
            mq.addEventListener("change", apply);
            document.addEventListener("visibilitychange", apply);
        })();
    </script>
</head>
<body>
<h2><?= e($title) ?></h2>

<p style="text-align: center;"><a href="index.html">&larr; back to main page</a></p>

<?php }

function page_foot(): void
{ ?>
</body>
</html>
<?php }
