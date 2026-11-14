<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('filagora-newsletters::messages.page.unsubscribed_title') }}</title>
</head>
<body>
    <main>
        <h1>{{ __('filagora-newsletters::messages.page.unsubscribed_title') }}</h1>
        <p>{{ __('filagora-newsletters::messages.page.unsubscribed_text', ['name' => config('filagora-newsletters.name')]) }}</p>
        <p><a href="{{ route('newsletter.show') }}">{{ __('filagora-newsletters::messages.page.signup_again') }}</a></p>
    </main>
</body>
</html>
