<?php

declare(strict_types=1);

use App\Services\Streaming\AgoraProvider;

test('agora provider builds access token 2 for publisher and subscriber', function () {
    // Official Agora sample credentials from DynamicKey docs (32-hex app id/cert).
    $provider = new AgoraProvider(
        '970CA35de60c44645bbae8a215061b33',
        '5CFd2fd1755d40ecb72977518be15d3b',
    );

    $channel = $provider->createChannel('11111111-1111-1111-1111-111111111111', 'Test');
    expect($channel['channel_id'])->toStartWith('agora_');

    $publisher = $provider->generatePublisherToken($channel['channel_id'], 'host-user-1');
    $subscriber = $provider->generateSubscriberToken($channel['channel_id'], 'viewer-user-1');

    expect($publisher)->toStartWith('007')
        ->and($subscriber)->toStartWith('007')
        ->and($publisher)->not->toBe($subscriber)
        ->and($provider->appId())->toBe('970CA35de60c44645bbae8a215061b33');
});
