<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Http\Controllers;

use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Notifications\ConfirmSubscription;
use Filagora\Newsletters\Services\Subscribers;
use Filagora\Newsletters\Support\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * The public signup page and the signed confirm and unsubscribe links.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly Subscribers $subscribers) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        return view((string) config('filagora-newsletters.routes.views.show'), [
            'newsletterSubscriber' => $user instanceof Model ? $this->subscribers->forUser($user) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'newsletter_consent' => ['accepted'],
            'website' => ['nullable', 'prohibited'],
        ]);

        $subscriber = $this->subscribers->requestPublicSubscription(
            (string) $validated['email'],
            isset($validated['name']) ? (string) $validated['name'] : null,
            $request->ip(),
            (string) $request->userAgent(),
        );

        if ($subscriber->status !== NewsletterSubscriberStatus::Subscribed) {
            Notification::route('mail', $subscriber->email)->notify(new ConfirmSubscription($subscriber));
        }

        return redirect()->route('newsletter.show')
            ->with('newsletter_status', __('filagora-newsletters::messages.signup.sent'));
    }

    public function confirm(string $subscriber): View
    {
        $subscriber = $this->find($subscriber);
        $this->subscribers->confirm($subscriber);

        return view((string) config('filagora-newsletters.routes.views.confirmed'), ['subscriber' => $subscriber]);
    }

    public function unsubscribe(string $subscriber): View
    {
        $subscriber = $this->find($subscriber);
        $this->subscribers->unsubscribe($subscriber);

        return view((string) config('filagora-newsletters.routes.views.unsubscribed'), ['subscriber' => $subscriber]);
    }

    /**
     * Resolved through the configured model class, so a swapped subscriber model is followed.
     */
    private function find(string $key): NewsletterSubscriber
    {
        /** @var NewsletterSubscriber */
        return Models::class('subscriber')::query()->findOrFail($key);
    }
}
