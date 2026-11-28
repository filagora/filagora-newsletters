<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Services;

use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Support\Models;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Subscribers
{
    /**
     * The key in a subscription's metadata naming the account it belonged to before that account
     * changed its address.
     */
    public const FORMER_USER = 'former_user_id';

    /**
     * The words a person agrees to when they subscribe from their profile.
     */
    public function profileConsentText(): string
    {
        return (string) (config('filagora-newsletters.consent.profile_text') ?? __('filagora-newsletters::messages.consent', ['name' => config('filagora-newsletters.name')]));
    }

    /**
     * The words a person agrees to when they ask for a subscription from the public form.
     */
    public function publicConsentText(): string
    {
        return (string) (config('filagora-newsletters.consent.public_text') ?? __('filagora-newsletters::messages.consent', ['name' => config('filagora-newsletters.name')]));
    }

    public function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function suppressionHash(string $email): string
    {
        return hash('sha256', $this->normalizeEmail($email));
    }

    public function forUser(Model $user): ?NewsletterSubscriber
    {
        $normalizedEmail = $this->normalizeEmail((string) $this->emailOf($user));

        return Models::class('subscriber')::query()
            ->where(function ($query) use ($user, $normalizedEmail): void {
                $query
                    ->where('user_id', $user->getKey())
                    ->orWhere('normalized_email', $normalizedEmail);
            })
            ->orderByRaw('user_id = ? desc', [$user->getKey()])
            ->latest('id')
            ->first();
    }

    /**
     * Unlink the subscription held under an address the member is leaving, remembering whose it
     * was so that their export and erasure still reach it.
     */
    public function unlinkUserForEmailChange(Model $user, string $oldEmail): int
    {
        $normalizedOldEmail = $this->normalizeEmail($oldEmail);

        if ($normalizedOldEmail === '') {
            return 0;
        }

        $subscribers = Models::class('subscriber')::query()
            ->where('user_id', $user->getKey())
            ->where('normalized_email', $normalizedOldEmail)
            ->get();

        foreach ($subscribers as $subscriber) {
            $subscriber->fill([
                'user_id' => null,
                'metadata' => [...($subscriber->metadata ?? []), self::FORMER_USER => $user->getKey()],
            ])->save();
        }

        return $subscribers->count();
    }

    public function subscribeFromProfile(Model $user, ?string $ip = null, ?string $userAgent = null): NewsletterSubscriber
    {
        $now = now();
        $email = trim((string) $this->emailOf($user));
        $normalizedEmail = $this->normalizeEmail($email);

        return DB::transaction(function () use ($user, $email, $normalizedEmail, $ip, $userAgent, $now): NewsletterSubscriber {
            $subscriber = $this->forEmail($normalizedEmail) ?? new (Models::class('subscriber'));

            $subscriber->fill([
                'user_id' => $user->getKey(),
                'email' => $email,
                'name' => $user->getAttribute('name'),
                'normalized_email' => $normalizedEmail,
                'suppression_hash' => null,
                'status' => NewsletterSubscriberStatus::Subscribed,
                'source' => 'profile',
                'subscribed_at' => $subscriber->subscribed_at ?? $now,
                'confirmed_at' => $subscriber->confirmed_at ?? $now,
                'unsubscribed_at' => null,
                'unsubscribe_reason' => null,
                'consent_text' => $this->profileConsentText(),
                'consent_version' => (string) config('filagora-newsletters.consent.profile_version'),
                'consent_ip' => $ip,
                'consent_user_agent' => $userAgent,
            ]);

            $subscriber->save();

            return $subscriber;
        });
    }

    public function requestPublicSubscription(string $email, ?string $name = null, ?string $ip = null, ?string $userAgent = null): NewsletterSubscriber
    {
        $email = trim($email);
        $normalizedEmail = $this->normalizeEmail($email);

        return DB::transaction(function () use ($email, $normalizedEmail, $name, $ip, $userAgent): NewsletterSubscriber {
            $subscriber = $this->forEmail($normalizedEmail) ?? new (Models::class('subscriber'));

            if ($subscriber->status === NewsletterSubscriberStatus::Subscribed) {
                return $subscriber;
            }

            $subscriber->fill([
                'email' => $email,
                'normalized_email' => $normalizedEmail,
                'suppression_hash' => null,
                'name' => trim((string) $name) !== '' ? trim((string) $name) : $subscriber->name,
                'status' => NewsletterSubscriberStatus::Pending,
                'source' => 'public_signup',
                'consent_text' => $this->publicConsentText(),
                'consent_version' => (string) config('filagora-newsletters.consent.public_version'),
                'consent_ip' => $ip,
                'consent_user_agent' => $userAgent,
            ]);

            $subscriber->save();

            return $subscriber;
        });
    }

    public function confirm(NewsletterSubscriber $subscriber): NewsletterSubscriber
    {
        $subscriber->fill([
            'status' => NewsletterSubscriberStatus::Subscribed,
            'subscribed_at' => $subscriber->subscribed_at ?? now(),
            'confirmed_at' => $subscriber->confirmed_at ?? now(),
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
        ]);
        $subscriber->save();

        return $subscriber;
    }

    /**
     * Every subscription that is this person's: linked to their account, held under their address,
     * or left under an address their account had before and since taken up by no other account.
     *
     * @return Collection<int, NewsletterSubscriber>
     */
    public function allFor(Model $user): Collection
    {
        $normalizedUserEmail = $this->normalizeEmail($this->emailOf($user));

        return Models::class('subscriber')::query()
            ->where(function ($query) use ($user, $normalizedUserEmail): void {
                $query
                    ->where('user_id', $user->getKey())
                    ->orWhere('normalized_email', $normalizedUserEmail)
                    ->orWhere(fn ($former) => $former
                        ->whereNull('user_id')
                        ->where('metadata->'.self::FORMER_USER, $user->getKey()));
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * What was sent to this person: to any of their subscriptions, or to one of their addresses.
     *
     * @param  Collection<int, NewsletterSubscriber>  $subscribers  as `allFor()` returns them
     * @return Collection<int, NewsletterSend>
     */
    public function deliveriesFor(Model $user, Collection $subscribers): Collection
    {
        $addresses = $subscribers->pluck('normalized_email')
            ->push($this->normalizeEmail($this->emailOf($user)))
            ->filter()
            ->unique()
            ->values();

        return Models::class('send')::query()
            ->where(function ($query) use ($subscribers, $addresses): void {
                $query
                    ->whereIn('newsletter_subscriber_id', $subscribers->modelKeys())
                    ->orWhereIn('normalized_email', $addresses->all());
            })
            ->orderBy('id')
            ->get();
    }

    public function anonymizeForErasure(Model $user): int
    {
        $subscribers = $this->allFor($user);
        // Read before the subscriptions lose the addresses their history is found by.
        $sends = $this->deliveriesFor($user, $subscribers);

        foreach ($subscribers as $subscriber) {
            $normalizedEmail = $this->normalizeEmail((string) ($subscriber->normalized_email ?: $subscriber->email));
            $subscriber->fill([
                'user_id' => null,
                'email' => sprintf('erased-subscriber-%d@anonymized.invalid', $subscriber->id),
                'normalized_email' => sprintf('erased-subscriber-%d@anonymized.invalid', $subscriber->id),
                'suppression_hash' => $this->suppressionHash($normalizedEmail),
                'name' => null,
                'status' => NewsletterSubscriberStatus::Unsubscribed,
                'source' => 'dsar_erasure',
                'mailchimp_id' => null,
                'unsubscribed_at' => $subscriber->unsubscribed_at ?? now(),
                'unsubscribe_reason' => 'dsar_erasure',
                'consent_text' => 'Suppression retained after DSAR erasure.',
                'consent_version' => 'dsar-erasure-v1',
                'consent_ip' => null,
                'consent_user_agent' => null,
                'metadata' => null,
            ]);
            $subscriber->save();
        }

        $this->anonymizeSendsForErasure($sends);

        return $subscribers->count();
    }

    /**
     * Anonymizes delivery-history rows (newsletter_sends), redacts raw webhook payloads on their
     * events (may contain the recipient email in provider-specific shapes not covered by the
     * indexed columns), and severs newsletter_link_clicks from the now-anonymized sends. Click
     * rows themselves are not personally identifying (no email/name; ip_hash is a one-way hash)
     * so they're kept, just unlinked, as anonymous engagement analytics.
     *
     * @param  Collection<int, NewsletterSend>  $sends
     */
    private function anonymizeSendsForErasure(Collection $sends): void
    {
        if ($sends->isEmpty()) {
            return;
        }

        $sendIds = $sends->modelKeys();

        Models::class('event')::query()
            ->whereIn('newsletter_send_id', $sendIds)
            ->update(['payload' => null]);

        Models::class('link_click')::query()
            ->whereIn('newsletter_send_id', $sendIds)
            ->update(['newsletter_send_id' => null]);

        foreach ($sends as $send) {
            $send->fill([
                'email' => sprintf('erased-send-%d@anonymized.invalid', $send->id),
                'normalized_email' => sprintf('erased-send-%d@anonymized.invalid', $send->id),
                'name' => null,
                'error' => null,
                'metadata' => null,
            ]);
            $send->save();
        }
    }

    public function unsubscribe(NewsletterSubscriber $subscriber, string $reason = 'signed_link'): NewsletterSubscriber
    {
        $subscriber->fill([
            'status' => NewsletterSubscriberStatus::Unsubscribed,
            'unsubscribed_at' => $subscriber->unsubscribed_at ?? now(),
            'unsubscribe_reason' => $reason,
        ]);
        $subscriber->save();

        return $subscriber;
    }

    public function signedConfirmationUrl(NewsletterSubscriber $subscriber): string
    {
        return URL::temporarySignedRoute('newsletter.confirm', now()->addDays(7), [
            'subscriber' => $subscriber,
        ]);
    }

    public function signedUnsubscribeUrl(NewsletterSubscriber $subscriber): string
    {
        return URL::signedRoute('newsletter.unsubscribe', [
            'subscriber' => $subscriber,
        ]);
    }

    public function unsubscribeFromProfile(Model $user): ?NewsletterSubscriber
    {
        $subscriber = $this->forUser($user);

        if (! $subscriber instanceof NewsletterSubscriber) {
            return null;
        }

        $subscriber->user_id = $subscriber->user_id ?? $user->getKey();

        return $this->unsubscribe($subscriber, 'profile');
    }

    /**
     * The subscriber holding this address, matched on the address or on its suppression hash.
     */
    public function forEmail(string $email): ?NewsletterSubscriber
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $suppressionHash = $this->suppressionHash($normalizedEmail);

        return Models::class('subscriber')::query()
            ->where('normalized_email', $normalizedEmail)
            ->orWhere('suppression_hash', $suppressionHash)
            ->latest('id')
            ->first();
    }

    private function emailOf(Model $user): string
    {
        return (string) $user->getAttribute('email');
    }
}
