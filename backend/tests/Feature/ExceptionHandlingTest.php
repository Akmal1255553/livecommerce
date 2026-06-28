<?php

declare(strict_types=1);

use App\Exceptions\Domain\ResourceNotFoundException;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

test('api returns business exception as json envelope', function () {
    Route::get('/api/v1/__test/not-found', function () {
        throw new ResourceNotFoundException('Video not found.');
    });

    $response = $this->getJson('/api/v1/__test/not-found');

    $response->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Video not found.',
        ]);
});

test('api response success envelope', function () {
    $response = ApiResponse::success(['id' => 1]);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe([
            'success' => true,
            'data' => ['id' => 1],
        ]);
});

test('api response error envelope', function () {
    $response = ApiResponse::error('Validation failed.', 422, [
        'email' => ['The email field is required.'],
    ]);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => [
                'email' => ['The email field is required.'],
            ],
        ]);
});
