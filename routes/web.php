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
Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');

// Logo de l'organisation : public + cacheable (affiché sur les pages login).
Route::get('/org/logo', [\App\Http\Controllers\OrganizationController::class, 'serveLogo']);
// Rate-limit : 5 tentatives / minute / IP pour limiter le bruteforce.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/login/2fa',  [AuthController::class, 'show2fa']);
Route::post('/login/2fa', [AuthController::class, 'verify2fa'])->middleware('throttle:10,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
// Rate-limit : 3 inscriptions / minute / IP.
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
// Logout :
//  - POST /logout : action réelle (CSRF-safe via token Inertia)
//  - GET /logout : page intermédiaire avec form POST auto-submit
//    (permet d'être bookmark / tapé directement sans réintroduire le bug
//    CSRF logout via <img src=/logout> qui ne déclenche pas le POST).
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/logout', [AuthController::class, 'logoutConfirm']);

// Avatar : public + cacheable (sert l'image ou un SVG d'initiales).
Route::get('/webmail/avatar/{hash}', [\App\Http\Controllers\AccountController::class, 'serveAvatar'])
    ->where('hash', '[a-f0-9]{32}')
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    ]);

Route::middleware(\App\Http\Middleware\EnsureMailbox::class)->group(function () {
    // Espace utilisateur : mot de passe, 2FA, tokens SMTP, avatar
    Route::get('/account',                     [\App\Http\Controllers\AccountController::class, 'index']);
    Route::post('/account/profile',            [\App\Http\Controllers\AccountController::class, 'updateProfile']);
    Route::post('/account/password',           [\App\Http\Controllers\AccountController::class, 'changePassword']);
    Route::post('/account/avatar',             [\App\Http\Controllers\AccountController::class, 'uploadAvatar']);
    Route::delete('/account/avatar',           [\App\Http\Controllers\AccountController::class, 'deleteAvatar']);
    Route::post('/account/2fa/start',          [\App\Http\Controllers\AccountController::class, 'start2fa']);
    Route::post('/account/2fa/confirm',        [\App\Http\Controllers\AccountController::class, 'confirm2fa']);
    Route::post('/account/2fa/disable',        [\App\Http\Controllers\AccountController::class, 'disable2fa']);
    Route::post('/account/tokens',             [\App\Http\Controllers\AccountController::class, 'createToken']);
    Route::delete('/account/tokens/{id}',      [\App\Http\Controllers\AccountController::class, 'deleteToken'])->whereNumber('id');

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
    Route::get('/webmail/smtp-tokens',          [MailController::class, 'smtpTokens']);
    Route::post('/webmail/smtp-tokens',         [MailController::class, 'createSmtpToken']);
    Route::delete('/webmail/smtp-tokens/{id}',  [MailController::class, 'deleteSmtpToken'])->whereNumber('id');
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
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    ]);

// Annuaire de l'organisation + carnet d'adresses partagé (tous membres).
Route::middleware(\App\Http\Middleware\EnsureMailbox::class)->group(function () {
    Route::get('/people',         [\App\Http\Controllers\PeopleController::class, 'index']);
    Route::get('/contacts',       [\App\Http\Controllers\ContactsController::class, 'index']);
    // Autocomplete style O365 pour le composer webmail (carnet personnel + orga)
    Route::get('/api/contacts/suggest', [\App\Http\Controllers\ContactsController::class, 'suggest']);
    // Carnet organisation (owner/admin créent, tous lisent)
    Route::post('/contacts',      [\App\Http\Controllers\ContactsController::class, 'store']);
    Route::patch('/contacts/{id}', [\App\Http\Controllers\ContactsController::class, 'update'])->whereNumber('id');
    Route::delete('/contacts/{id}',[\App\Http\Controllers\ContactsController::class, 'destroy'])->whereNumber('id');
    // Carnet personnel (privé à chaque user)
    Route::post('/contacts/personal',         [\App\Http\Controllers\ContactsController::class, 'storePersonal']);
    Route::patch('/contacts/personal/{id}',   [\App\Http\Controllers\ContactsController::class, 'updatePersonal'])->whereNumber('id');
    Route::delete('/contacts/personal/{id}',  [\App\Http\Controllers\ContactsController::class, 'destroyPersonal'])->whereNumber('id');

    // Gestion de l'organisation (owner+admin+support en lecture, owner pour writes).
    Route::middleware('admin')->group(function () {
        Route::get('/org',                          [\App\Http\Controllers\OrganizationController::class, 'settings']);
        Route::post('/org',                         [\App\Http\Controllers\OrganizationController::class, 'updateSettings']);
        Route::post('/org/logo',                    [\App\Http\Controllers\OrganizationController::class, 'uploadLogo']);
        Route::delete('/org/logo',                  [\App\Http\Controllers\OrganizationController::class, 'deleteLogo']);
        Route::post('/org/branding',                [\App\Http\Controllers\OrganizationController::class, 'updateBranding']);
        Route::get('/org/members',                  [\App\Http\Controllers\OrganizationController::class, 'members']);
        Route::post('/org/members',                 [\App\Http\Controllers\OrganizationController::class, 'addMember']);
        Route::patch('/org/members/{id}',           [\App\Http\Controllers\OrganizationController::class, 'updateMemberRole'])->whereNumber('id');
        Route::delete('/org/members/{id}',          [\App\Http\Controllers\OrganizationController::class, 'removeMember'])->whereNumber('id');
    });
});

// Admin panel — accès limité aux rôles owner/admin/support de l'organisation.
// + 2FA OBLIGATOIRE (middleware force.2fa).
Route::middleware([\App\Http\Middleware\EnsureMailbox::class, 'admin', 'force.2fa'])->prefix('admin')->group(function () {
    Route::get('/',                         [AdminController::class, 'dashboard']);
    Route::get('/domains',                  [AdminController::class, 'domains']);
    Route::post('/domains',                 [AdminController::class, 'createDomain']);
    Route::delete('/domains/{id}',          [AdminController::class, 'deleteDomain'])->whereNumber('id');
    Route::get('/domains/{id}/dns',         [AdminController::class, 'domainDns'])->whereNumber('id');
    Route::get('/accounts',                 [AdminController::class, 'accounts']);
    Route::post('/accounts',                [AdminController::class, 'createAccount']);
    Route::patch('/accounts/{id}/toggle',   [AdminController::class, 'toggleAccount'])->whereNumber('id');
    Route::patch('/accounts/{id}',          [AdminController::class, 'updateAccount'])->whereNumber('id');
    Route::patch('/domains/{id}/primary',   [AdminController::class, 'setPrimaryDomain'])->whereNumber('id');
    Route::patch('/accounts/{id}/password', [AdminController::class, 'resetPassword'])->whereNumber('id');
    Route::patch('/accounts/{id}/quota',    [AdminController::class, 'updateQuota'])->whereNumber('id');
    Route::delete('/accounts/{id}',         [AdminController::class, 'deleteAccount'])->whereNumber('id');
    Route::get('/aliases',                  [AdminController::class, 'aliases']);
    Route::post('/aliases',                 [AdminController::class, 'createAlias']);
    Route::patch('/aliases/{id}/toggle',    [AdminController::class, 'toggleAlias'])->whereNumber('id');
    Route::delete('/aliases/{id}',          [AdminController::class, 'deleteAlias'])->whereNumber('id');

    // Mailing lists (listes de diffusion)
    Route::get('/lists',                              [AdminController::class, 'lists']);
    Route::post('/lists',                             [AdminController::class, 'createList']);
    Route::get('/lists/{id}',                         [AdminController::class, 'showList'])->whereNumber('id');
    Route::post('/lists/{id}/members',                [AdminController::class, 'addListMember'])->whereNumber('id');
    Route::delete('/lists/{id}/members/{memberId}',   [AdminController::class, 'removeListMember'])->whereNumber('id')->whereNumber('memberId');
    Route::patch('/lists/{id}/scope',                 [AdminController::class, 'updateListScope'])->whereNumber('id');
    Route::patch('/lists/{id}/toggle',                [AdminController::class, 'toggleList'])->whereNumber('id');
    Route::delete('/lists/{id}',                      [AdminController::class, 'deleteList'])->whereNumber('id');

    // Shared mailboxes (boîtes partagées)
    Route::get('/shared',                             [AdminController::class, 'sharedMailboxes']);
    Route::post('/shared',                            [AdminController::class, 'createSharedMailbox']);
    Route::get('/shared/{id}',                        [AdminController::class, 'showSharedMailbox'])->whereNumber('id');
    Route::post('/shared/{id}/acls',                  [AdminController::class, 'grantSharedAccess'])->whereNumber('id');
    Route::delete('/shared/{id}/acls/{aclId}',        [AdminController::class, 'revokeSharedAccess'])->whereNumber('id')->whereNumber('aclId');
    Route::delete('/shared/{id}',                     [AdminController::class, 'deleteSharedMailbox'])->whereNumber('id');
    Route::get('/settings',                 [AdminController::class, 'settings']);
    Route::post('/settings',                [AdminController::class, 'saveSettings']);
    Route::get('/audit',                    [AdminController::class, 'auditLog']);
    Route::get('/rspamd',                   [AdminController::class, 'rspamd']);

    // Quarantaine spam (Postfix HOLD queue)
    Route::get('/spam',                              [AdminController::class, 'spamQueue']);
    Route::get('/spam/{queueId}/show',               [AdminController::class, 'spamShow']);
    Route::post('/spam/{queueId}/release',           [AdminController::class, 'spamRelease']);
    Route::post('/spam/release-all',                 [AdminController::class, 'spamReleaseAll']);
    Route::delete('/spam/{queueId}',                 [AdminController::class, 'spamDelete']);

    // Migrations IMAP (imapsync)
    Route::get('/migrations',               [AdminController::class, 'migrations']);
    Route::post('/migrations',              [AdminController::class, 'startMigration']);
    Route::post('/migrations/{id}/cancel',  [AdminController::class, 'cancelMigration'])->whereNumber('id');
    Route::get('/migrations/{id}/log',      [AdminController::class, 'migrationLog'])->whereNumber('id');
});

Route::get('/up', fn () => response('OK'));
