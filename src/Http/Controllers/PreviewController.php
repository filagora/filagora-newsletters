<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Http\Controllers;

use Filagora\Newsletters\Mail\CampaignMail;
use Filagora\Newsletters\Services\DeliveryTransport;
use Filagora\Newsletters\Support\Gates;
use Filagora\Newsletters\Support\Models;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Shows a campaign as the email will look, to staff who manage newsletters.
 */
class PreviewController extends Controller
{
    public function __invoke(string $newsletter, DeliveryTransport $transport): Response
    {
        abort_unless(Gate::allows(Gates::MANAGE), 403);

        $newsletter = Models::class('newsletter')::query()->findOrFail($newsletter);

        $html = (new CampaignMail(
            newsletter: $newsletter,
            unsubscribeUrl: route('newsletter.show'),
            preview: true,
            deliveryProvider: $transport->provider(),
        ))->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
