<?php

declare(strict_types=1);

it('serves the thin web admin spa', function (): void {
    $this->get('/admin/')
        ->assertOk()
        ->assertSee('LiveCommerce Admin', false)
        ->assertSee('app.js', false);
});
