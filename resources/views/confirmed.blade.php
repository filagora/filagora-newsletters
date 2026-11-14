<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('filagora-newsletters::messages.page.confirmed_title') }}</title>
</head>
<body>
    <main>
        <h1>{{ __('filagora-newsletters::messages.page.confirmed_title') }}</h1>
        <p>{{ __('filagora-newsletters::messages.page.confirmed_text', ['name' => config('filagora-newsletters.name')]) }}</p>
    </main>
</body>
</html>
