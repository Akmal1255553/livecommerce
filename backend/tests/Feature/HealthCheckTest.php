<?php

declare(strict_types=1);

test('health endpoint returns ok with queue running', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'status' => 'ok',
                'database' => 'connected',
                'queue' => 'running',
            ],
        ])
        ->assertJsonStructure([
            'success',
            'data' => [
                'status',
                'database',
                'redis',
                'queue',
            ],
        ]);
});

test('health endpoint includes request id header', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk();
    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});
