---
name: laravel-lti
description: "Apply this skill when working with the laravel-lti package (refbytes/laravel-lti). Use it for LTI 1.3 launch handling, OIDC login flow, JWT validation, Deep Linking, NRPS (Names and Roles), AGS (grade passback / Assignment and Grade Services), tool key management, platform registration, and multi-tenancy configuration. Triggers when editing LtiLaunchData, LtiPlatform, AgsClient, NrpsClient, DeepLinkingService, event listeners for LtiLaunchValidated or LtiDeepLinkingRequested, or when configuring config/lti.php."
license: MIT
metadata:
  author: refbytes
---

# laravel-lti Package Reference

LTI 1.3 integration for Laravel. Handles the full OIDC login → JWT launch flow, and all three LTI Advantage services: Deep Linking, NRPS, and AGS.

## Quick Reference

### Package Info
- Composer: `refbytes/laravel-lti`
- Facade: `use RefBytes\Lti\Facades\Lti;`
- Service provider auto-discovers; no manual registration needed

### Auto-registered Routes
```
GET|POST /lti/login    → lti.login    (OIDC initiation)
POST     /lti/launch   → lti.launch   (JWT callback)
GET      /lti/jwks     → lti.jwks     (Tool public keys)
```
Routes use no middleware by default (cross-origin POST from LMS requires this).

### Key Classes
| Class | Purpose |
|---|---|
| `LtiLaunchData` | DTO returned by `LtiLaunchValidated` event |
| `LtiPlatform` | Eloquent model for registered platforms |
| `AgsClient` | Grade passback (line items, scores, results) |
| `NrpsClient` | Roster/membership service |
| `DeepLinkingService` | Build Deep Linking responses |
| `PlatformOAuth2Service` | OAuth2 token exchange |
| `ToolKeyService` | RSA keypair management |

---

## Platform Registration

```php
use RefBytes\Lti\Models\LtiPlatform;

LtiPlatform::create([
    'name'         => 'Canvas',
    'issuer'       => 'https://canvas.example.com',
    'client_id'    => '10000000000001',
    'auth_url'     => 'https://canvas.example.com/api/lti/authorize_redirect',
    'token_url'    => 'https://canvas.example.com/login/oauth2/token',
    'jwks_url'     => 'https://canvas.example.com/api/lti/security/jwks',
    'active'       => true,
]);
```

Or via facade: `Lti::registerPlatform([...])`.

---

## Handling Launches

Listen for `LtiLaunchValidated` in `AppServiceProvider`:

```php
use RefBytes\Lti\Events\LtiLaunchValidated;

Event::listen(LtiLaunchValidated::class, function (LtiLaunchValidated $event) {
    $launch = $event->launch; // LtiLaunchData
    $userId = $launch->userId;
    $platform = $launch->platform; // LtiPlatform model
});
```

### LtiLaunchData API

```php
$launch->userId           // string
$launch->platform         // LtiPlatform
$launch->messageType      // 'LtiResourceLinkRequest' | 'LtiDeepLinkingRequest'
$launch->ltiVersion       // '1.3.0'
$launch->deploymentId     // string|null
$launch->targetLinkUri    // string
$launch->resourceLinkId   // string|null
$launch->roles            // array
$launch->claims           // array (full JWT claims)
$launch->launchId         // string|null (when store_launches = true)

// Helpers
$launch->claim('https://purl.imsglobal.org/spec/lti/claim/context') // exact key or dot-notation
$launch->isInstructor()   // bool
$launch->isLearner()      // bool
$launch->hasRole(string)  // bool

// Service discovery
$launch->hasNrps()        // bool
$launch->nrpsServiceInfo()// NrpsServiceInfo|null
$launch->hasAgs()         // bool
$launch->agsServiceInfo() // AgsServiceInfo|null
```

---

## Deep Linking

Listen for `LtiDeepLinkingRequested`:

```php
use RefBytes\Lti\Events\LtiDeepLinkingRequested;
use RefBytes\Lti\DataTransferObjects\ContentItems\LtiResourceLinkItem;

Event::listen(LtiDeepLinkingRequested::class, function (LtiDeepLinkingRequested $event) {
    $item = LtiResourceLinkItem::make('My Tool', 'https://tool.example.com/activity/1')
        ->description('An activity')
        ->custom(['activityId' => '42']);

    return response(Lti::buildDeepLinkingFormResponse($event->launch, [$item]));
});
```

**Content item types:**
- `LtiResourceLinkItem::make(string $title, string $url)` — LTI resource link
- `FileItem::make(string $title, string $url)` — File
- `HtmlItem::make(string $html)` — HTML fragment
- `ImageItem::make(string $url)` — Image
- `LinkItem::make(string $title, string $url)` — Web link

All items have fluent setters; `toArray()` omits null fields.

---

## NRPS (Names and Roles)

```php
use RefBytes\Lti\Services\NrpsClient;

$members = app(NrpsClient::class)->getMembers($launch);

// Or lazy (memory-efficient):
$lazy = app(NrpsClient::class)->getMembersLazy($launch);
$lazy->each(function ($member) { /* ... */ });

// Via facade:
$members = Lti::getMembers($launch);
```

`$member` shape:
```php
[
    'user_id'     => 'string',
    'roles'       => ['array', 'of', 'role', 'urns'],
    'name'        => 'string|null',
    'given_name'  => 'string|null',
    'family_name' => 'string|null',
    'email'       => 'string|null',
]
```

---

## AGS (Assignment and Grade Services)

### Check capability first
```php
if ($launch->hasAgs()) {
    $info = $launch->agsServiceInfo();
    $info->canManageLineItems() // bool — lineitem scope
    $info->canPostScores()      // bool — score scope
    $info->canReadResults()     // bool — result.readonly scope
    $info->canReadLineItems()   // bool — lineitem or lineitem.readonly
}
```

### Line Items (gradebook columns)
```php
use RefBytes\Lti\Services\AgsClient;
use RefBytes\Lti\DataTransferObjects\AgsLineItem;

$client = app(AgsClient::class);

// List
$items = $client->getLineItems($launch);
$items = $client->getLineItems($launch, resourceId: 'r1', tag: 'quiz');

// Get one
$item = $client->getLineItem($launch, $lineItemUrl);

// Create
$newItem = AgsLineItem::make('Quiz 1', 100.0)
    ->tag('quiz')
    ->resourceId('res-1');
$created = $client->createLineItem($launch, $newItem);

// Update
$updated = $client->updateLineItem($launch, $lineItemUrl, $item);

// Delete
$client->deleteLineItem($launch, $lineItemUrl);
```

`AgsLineItem` properties: `id`, `scoreMaximum`, `label`, `resourceId`, `tag`, `resourceLinkId`, `startDateTime`, `endDateTime`

### Scores (grade passback)
```php
use RefBytes\Lti\DataTransferObjects\AgsScore;

$score = AgsScore::make($launch->userId)
    ->scoreGiven(85.0)
    ->scoreMaximum(100.0)
    ->comment('Great work!');
    // Defaults: activityProgress=Completed, gradingProgress=FullyGraded

// Activity progress constants:
// AgsScore::ACTIVITY_INITIALIZED, STARTED, IN_PROGRESS, SUBMITTED, COMPLETED
// Grading progress constants:
// AgsScore::GRADING_FULLY_GRADED, PENDING, PENDING_MANUAL, NOT_READY

$client->submitScore($launch, $lineItemUrl, $score);
```

### Results (read grades back)
```php
$results = $client->getResults($launch, $lineItemUrl);
$results = $client->getResults($launch, $lineItemUrl, userId: 'student-42');
```

`AgsResult` properties: `id`, `scoreOf`, `userId`, `resultScore`, `resultMaximum`, `comment`

---

## Multi-Tenancy

In `config/lti.php`:

```php
'tenant_model'    => App\Models\Organization::class,
'tenant_resolver' => App\Resolvers\TenantResolver::class,
```

Resolver must implement `resolve(Request $request): ?Model`. The resolved tenant is passed to all service calls as an optional last argument:

```php
$client->getLineItems($launch, tenant: $tenant);
Lti::getMembers($launch, tenant: $tenant);
```

---

## Configuration Reference

```php
// config/lti.php
'tenant_model'     => null,        // Eloquent model class for tenants
'tenant_resolver'  => null,        // Resolver class
'route_prefix'     => 'lti',       // URL prefix
'route_middleware' => [],          // Applied to all LTI routes
'cache_store'      => null,        // null = default driver
'cache_prefix'     => 'lti:',
'state_ttl'        => 600,         // OIDC state lifetime (seconds)
'jwks_ttl'         => 86400,       // Platform JWKS cache (seconds)
'store_launches'   => false,       // Persist launches to lti_launches table
'tool' => [
    'name'          => env('LTI_TOOL_NAME'),
    'description'   => env('LTI_TOOL_NAME', ''),
    'domain'        => env('LTI_TOOL_DOMAIN'),
    'key_algorithm' => 'RS256',
    'key_bits'      => 2048,
],
'tool_jwks_ttl'    => 3600,        // Tool JWKS cache (seconds)
```

---

## Database Tables

| Table | Purpose |
|---|---|
| `lti_platforms` | Registered LMS platforms (issuer, client_id, URLs, etc.) |
| `lti_tool_keys` | RSA keypairs for signing JWTs |
| `lti_launches` | Launch records (when `store_launches = true`) |

---

## Artisan Commands

```bash
php artisan lti:generate-key   # Generate a new RSA keypair
php artisan lti:rotate-keys    # Rotate keys (keeps old for validation)
```

---

## Common Patterns

### Redirect to app after launch
```php
Event::listen(LtiLaunchValidated::class, function ($event) {
    session(['lti_user' => $event->launch->userId]);
    return redirect('/dashboard');
});
```

### Guard routes with LTI session
```php
// Middleware checking session set during launch
if (! session('lti_user')) {
    abort(403);
}
```

### Deep Linking + AGS combined workflow
```php
// 1. Deep link sends back a line item URL you stored
// 2. On subsequent resource launch, submit scores:
Event::listen(LtiLaunchValidated::class, function ($event) {
    $launch = $event->launch;
    if ($launch->hasAgs() && $launch->agsServiceInfo()->canPostScores()) {
        $lineItemUrl = $launch->claim('https://purl.imsglobal.org/spec/lti-ags/claim/endpoint')['lineitem'] ?? null;
        if ($lineItemUrl) {
            app(AgsClient::class)->submitScore($launch, $lineItemUrl,
                AgsScore::make($launch->userId)->scoreGiven(100.0)->scoreMaximum(100.0)
            );
        }
    }
});
```

---

## LMS Tool Registration URLs

When registering your tool in Canvas/Blackboard/Moodle:

| Setting | Value |
|---|---|
| OIDC Login URL | `https://yourdomain.com/lti/login` |
| Launch URL | `https://yourdomain.com/lti/launch` |
| JWKS URL | `https://yourdomain.com/lti/jwks` |
| Redirect URIs | `https://yourdomain.com/lti/launch` |
