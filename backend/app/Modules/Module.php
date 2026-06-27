<?php

declare(strict_types=1);

namespace App\Modules;

enum Module: string
{
    case Authentication = 'authentication';
    case Users = 'users';
    case Social = 'social';
    case Video = 'video';
    case Feed = 'feed';
    case Product = 'product';
    case Cart = 'cart';
    case Order = 'order';
    case Store = 'store';
    case LiveStream = 'live_stream';
    case Notification = 'notification';
    case Search = 'search';
    case Recommendation = 'recommendation';
    case Media = 'media';
    case Payment = 'payment';
    case Audit = 'audit';
    case Ai = 'ai';
    case Admin = 'admin';
}
