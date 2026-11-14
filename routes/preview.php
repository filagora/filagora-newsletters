<?php

declare(strict_types=1);

use Filagora\Newsletters\Http\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/{newsletter}/preview', PreviewController::class)->name('newsletters.preview');
