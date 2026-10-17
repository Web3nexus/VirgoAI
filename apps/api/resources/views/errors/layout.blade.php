<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') · virgoAi</title>
    <style>
        :root { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #090a0a; color: #eeedf0; font-family: Inter, system-ui, -apple-system, sans-serif; }
        main { max-width: 28rem; padding: 2rem 1rem; text-align: center; }
        .code { font-size: 3.5rem; font-weight: 600; color: #fb23c2; margin: 0; }
        h1 { font-size: 1.25rem; margin: 1rem 0 0.5rem; }
        p { color: #b5b2bc; font-size: 0.9rem; line-height: 1.6; margin: 0 0 1.5rem; }
        a { display: inline-block; border-radius: 999px; background: #eeedf0; color: #090a0a; padding: 0.6rem 1.2rem; font-size: 0.9rem; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        @yield('action')
    </main>
</body>
</html>
