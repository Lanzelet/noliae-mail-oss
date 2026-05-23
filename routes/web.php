<?php

use App\Http\Controllers\MailController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

/*
 * Noliae Mail OSS — webmail souverain.
 * Auth locale (email + mot de passe) — pas de SSO externe.
 */
Route::get('/', [AuthController::class, 'landing'])->name('landing');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/logout', [AuthController::class, 'logout']);

Route::middleware(\App\Http\Middleware\EnsureMailbox::class)->group(function () {
    Route::get('/webmail', [MailController::class, 'index']);
    Route::post('/webmail/send', [MailController::class, 'send']);
    Route::post('/webmail/folders', [MailController::class, 'createFolder']);
    Route::delete('/webmail/folders', [MailController::class, 'deleteFolder']);
    Route::post('/webmail/move', [MailController::class, 'move']);
    Route::post('/webmail/trash', [MailController::class, 'trash']);
    Route::post('/webmail/archive', [MailController::class, 'archive']);
    Route::post('/webmail/spam', [MailController::class, 'spam']);
    Route::post('/webmail/seen', [MailController::class, 'seen']);
    Route::post('/webmail/bulk', [MailController::class, 'bulk']);
    Route::post('/webmail/star', [MailController::class, 'star']);
    Route::post('/webmail/label', [MailController::class, 'label']);
    Route::get('/webmail/attachment', [MailController::class, 'attachment']);
    Route::get('/webmail/raw', [MailController::class, 'raw']);
    Route::post('/webmail/snooze', [MailController::class, 'snooze']);
    Route::post('/webmail/wake', [MailController::class, 'wakeSnoozed']);
    Route::post('/webmail/inline-image', [MailController::class, 'inlineImage']);
    Route::post('/webmail/draft', [MailController::class, 'saveDraft']);
    Route::post('/webmail/settings', [MailController::class, 'saveSettings']);
    Route::post('/webmail/receipt', [MailController::class, 'sendReceipt']);
    Route::get('/webmail/pgp/contacts', [MailController::class, 'pgpContacts']);
    Route::post('/webmail/pgp/import',  [MailController::class, 'pgpImport']);
    Route::delete('/webmail/pgp/import', [MailController::class, 'pgpForget']);
    Route::get('/webmail/pgp/lookup',   [MailController::class, 'pgpLookup']);
    Route::post('/webmail/pgp/decrypt', [MailController::class, 'pgpDecrypt']);
});

// Annuaire PGP public (sans auth) pour permettre l'encryption externe.
Route::get('/webmail/pgp/{hash}.asc', [MailController::class, 'pgpPublicKey'])
    ->where('hash', '[a-f0-9]{64}');

// Proxy d'images mail — hors session pour cache CDN.
Route::get('/webmail/img', [MailController::class, 'img'])
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ]);

// Admin panel — accès limité au compte ADMIN_EMAIL.
Route::middleware(\App\Http\Middleware\EnsureMailbox::class)->prefix('admin')->group(function () {
    Route::get('/',                         [AdminController::class, 'dashboard']);
    Route::get('/domains',                  [AdminController::class, 'domains']);
    Route::post('/domains',                 [AdminController::class, 'createDomain']);
    Route::delete('/domains/{id}',          [AdminController::class, 'deleteDomain'])->whereNumber('id');
    Route::get('/domains/{id}/dns',         [AdminController::class, 'domainDns'])->whereNumber('id');
    Route::get('/accounts',                 [AdminController::class, 'accounts']);
    Route::post('/accounts',                [AdminController::class, 'createAccount']);
    Route::patch('/accounts/{id}/toggle',   [AdminController::class, 'toggleAccount'])->whereNumber('id');
    Route::patch('/accounts/{id}/password', [AdminController::class, 'resetPassword'])->whereNumber('id');
    Route::delete('/accounts/{id}',         [AdminController::class, 'deleteAccount'])->whereNumber('id');
    Route::get('/settings',                 [AdminController::class, 'settings']);
    Route::post('/settings',                [AdminController::class, 'saveSettings']);
});

Route::get('/up', fn () => response('OK'));
