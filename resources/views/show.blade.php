<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('filagora-newsletters::messages.page.title') }}</title>
</head>
<body>
    <main>
        <h1>{{ __('filagora-newsletters::messages.page.title') }}</h1>

        @if(session('newsletter_status'))
            <p role="status">{{ session('newsletter_status') }}</p>
        @endif

        @auth
            <p>{{ $newsletterSubscriber?->isSendable() ? __('filagora-newsletters::messages.page.subscribed') : __('filagora-newsletters::messages.page.not_subscribed') }}</p>
        @else
            <form method="post" action="{{ route('newsletter.store') }}">
                @csrf
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" hidden aria-hidden="true">
                <label>{{ __('filagora-newsletters::messages.page.name') }} <input name="name" type="text" value="{{ old('name') }}"></label>
                <label>{{ __('filagora-newsletters::messages.page.email') }} <input name="email" type="email" value="{{ old('email') }}" required></label>
                <label><input type="checkbox" name="newsletter_consent" value="1" required> {{ __('filagora-newsletters::messages.consent', ['name' => config('filagora-newsletters.name')]) }}</label>
                @if($errors->any())
                    <p>{{ $errors->first() }}</p>
                @endif
                <button type="submit">{{ __('filagora-newsletters::messages.page.send_link') }}</button>
            </form>
        @endauth
    </main>
</body>
</html>
