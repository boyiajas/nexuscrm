<?php

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Route;

$publicSettings = static fn () => [
    'publicSettings' => SystemSetting::query()->first(),
];

Route::get('/', static fn () => view('public.landing', $publicSettings()))->name('public.landing');
Route::get('/privacy-policy', static fn () => view('public.privacy', $publicSettings()))->name('public.privacy');
Route::get('/compliance', static fn () => view('public.compliance', $publicSettings()))->name('public.compliance');
Route::get('/terms-of-service', static fn () => view('public.terms', $publicSettings()))->name('public.terms');
Route::get('/data-deletion', static fn () => view('public.data-deletion', $publicSettings()))->name('public.data-deletion');

Route::get('{any}', function () {
    return view('app');
})->where('any', '.*');
