<?php

declare(strict_types=1);

namespace App\Enums;

enum LiveAnalyticsEventType: string
{
    case LiveStarted = 'live_started';
    case LiveEnded = 'live_ended';
    case LiveJoined = 'live_joined';
    case LiveLeft = 'live_left';
    case ProductPinned = 'product_pinned';
    case ProductUnpinned = 'product_unpinned';
    case ProductClicked = 'product_clicked';
    case ProductAddedToCart = 'product_added_to_cart';
    case CheckoutFromLive = 'checkout_from_live';
    case PurchaseFromLive = 'purchase_from_live';
}
