<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Enums;

enum NewsletterEventType: string
{
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Unsubscribed = 'unsubscribed';
    case Opened = 'opened';
    case Clicked = 'clicked';
}
