<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers no public or preview routes when the host turns them off', function () {
    foreach (['newsletter.show', 'newsletter.store', 'newsletter.confirm', 'newsletter.unsubscribe', 'newsletter.link-click', 'newsletters.preview'] as $name) {
        expect(Route::has($name))->toBeFalse($name);
    }
});
