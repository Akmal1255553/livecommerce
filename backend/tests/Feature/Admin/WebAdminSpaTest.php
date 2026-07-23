<?php

declare(strict_types=1);

it('redirects /admin to the spa directory', function (): void {
    $this->get('/admin')
        ->assertRedirect('/admin/');
});

it('ships the thin web admin spa assets', function (): void {
    $html = file_get_contents(public_path('admin/index.html'));
    expect($html)->not->toBeFalse()
        ->and($html)->toContain('LiveCommerce Admin')
        ->and($html)->toContain('app.js')
        ->and($html)->toContain('Overview')
        ->and($html)->toContain('Categories')
        ->and($html)->toContain('Audit');

    expect(is_file(public_path('admin/app.js')))->toBeTrue();
    expect(is_file(public_path('admin/styles.css')))->toBeTrue();
});
