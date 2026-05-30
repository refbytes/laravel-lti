# Laravel LTI - Usage Guide

This guide walks through integrating `laravel-lti` into a Laravel application and connecting it to an LMS platform like Canvas, Blackboard, or Moodle.

## Installation

```bash
composer require refbytes/laravel-lti
```

Publish the config and migrations, then run them:

```bash
php artisan vendor:publish --tag="lti-config"
php artisan vendor:publish --tag="lti-migrations"
php artisan migrate
```

Generate a tool keypair (required for LTI 1.3):

```bash
php artisan lti:generate-key
```

## Configuration

After publishing, edit `config/lti.php`:

```php
return [
    // Multi-tenancy (null disables it)
    'tenant_model' => null,
    'tenant_resolver' => null,

    // Routes
    'route_prefix' => 'lti',
    'route_middleware' => [],

    // Cache
    'cache_store' => null,       // null = default cache store
    'cache_prefix' => 'lti:',
    'state_ttl' => 600,          // OIDC state lifetime (seconds)
    'jwks_ttl' => 86400,         // Platform JWKS cache (seconds)

    // Launch persistence
    'store_launches' => false,

    // Tool metadata
    'tool' => [
        'name' => env('LTI_TOOL_NAME'),
        'description' => env('LTI_TOOL_DESCRIPTION', ''),
        'domain' => env('LTI_TOOL_DOMAIN'),
        'key_algorithm' => 'RS256',
        'key_bits' => 2048,
    ],
    'tool_jwks_ttl' => 3600,
];
```

## Routes

The package registers three routes under the configured prefix (default `lti`):

| Method | URI | Name | Purpose |
|--------|-----|------|---------|
| GET/POST | `/lti/login` | `lti.login` | OIDC login initiation |
| POST | `/lti/launch` | `lti.launch` | Launch callback (JWT validation) |
| GET | `/lti/jwks` | `lti.jwks` | Tool's public JWKS |
| GET | `/lti/register` | `lti.register` | LTI 1.3 Dynamic Registration initiation |

These routes intentionally have no CSRF middleware since LTI launches are cross-origin POST requests from the LMS platform.

## Dynamic Registration

LTI 1.3 Dynamic Registration eliminates the manual copy-paste install flow. Instead of creating a developer key, configuring scopes, and pasting URLs by hand, an LMS admin can paste a single URL — your tool's registration initiation URL — and the rest happens automatically. The package fetches the LMS's OpenID configuration, POSTs a registration request with your tool's metadata, and creates an `LtiPlatform` record for you. The admin clicks one button and they're done.

### What the admin pastes

```
https://yourdomain.com/lti/register
```

That's the entire install instruction for any LMS that supports Dynamic Registration (Canvas, Moodle, Brightspace, and others). Once pasted, your tool and the LMS negotiate the configuration; the admin sees a "Registration Complete" page and clicks **Complete Registration** to finish.

### Configuring your tool's metadata

The metadata sent during registration comes from `config/lti.php` under the `tool` array:

```php
'tool' => [
    'name'              => env('LTI_TOOL_NAME'),         // Required — shown in the LMS
    'description'       => env('LTI_TOOL_DESCRIPTION'),
    'domain'            => env('LTI_TOOL_DOMAIN'),       // e.g. tool.example.com
    'logo_uri'          => env('LTI_TOOL_LOGO_URI'),     // Shown in LMS app catalog
    'client_uri'        => env('LTI_TOOL_CLIENT_URI'),   // Public homepage
    'policy_uri'        => env('LTI_TOOL_POLICY_URI'),   // Privacy policy
    'tos_uri'           => env('LTI_TOOL_TOS_URI'),      // Terms of service
    'contacts'          => ['support@example.com'],
    'default_scopes'    => [
        'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
        'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
        'https://purl.imsglobal.org/spec/lti-ags/scope/score',
        'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly',
    ],
    'deep_linking_enabled' => true,    // Set false if your tool doesn't support Deep Linking
    'deep_linking_label'   => 'Add Activity',
],
```

`initiate_login_uri`, `redirect_uris`, and `jwks_uri` are derived from the package's named routes (`lti.login`, `lti.launch`, `lti.jwks`) — you do not need to configure them.

### What gets stored

After a successful registration, a new row appears in `lti_platforms` with:
- `issuer`, `auth_url`, `token_url`, `jwks_url` — from the LMS's OpenID configuration
- `client_id` — assigned by the LMS during registration
- `name` — the LMS product family code (e.g. `canvas`, `moodle`)
- `version` — `'1.3'`
- `registered_at` — timestamp of registration

Manually-registered platforms (via `Lti::registerPlatform()`) continue to work alongside dynamically-registered ones. They differ only in that `registered_at` will be null on manual registrations.

### Tenant scoping

If multi-tenancy is enabled (`tenant_model` + `tenant_resolver` set in config), the registration route uses the same tenant resolver as the rest of the package. A platform registered while a tenant is resolved is scoped to that tenant — the consuming app might serve `/lti/register` from `https://tenant-a.example.com/lti/register` and the new platform record will be associated with tenant A.

## Connecting to an LMS

### Step 1: Register your tool in the LMS

Every LMS has an admin UI for registering external tools. You'll need to provide:

| Field | Value |
|-------|-------|
| Login URL | `https://yourdomain.com/lti/login` |
| Launch URL | `https://yourdomain.com/lti/launch` |
| JWKS URL | `https://yourdomain.com/lti/jwks` |
| Redirect URI | `https://yourdomain.com/lti/launch` |

The LMS will give you back a **client_id** and provide its platform URLs.

### Step 2: Register the platform in your app

```php
use RefBytes\Lti\Facades\Lti;

Lti::registerPlatform([
    'issuer' => 'https://canvas.instructure.com',          // Platform issuer
    'client_id' => '10000000000001',                        // From the LMS
    'deployment_id' => '1',                                 // From the LMS (optional)
    'auth_url' => 'https://canvas.instructure.com/api/lti/authorize_redirect',
    'token_url' => 'https://canvas.instructure.com/login/oauth2/token',
    'jwks_url' => 'https://canvas.instructure.com/api/lti/security/jwks',
    'name' => 'Canvas Production',
]);
```

**Common platform URLs:**

Canvas:
- Issuer: `https://canvas.instructure.com`
- Auth: `https://{domain}/api/lti/authorize_redirect`
- Token: `https://{domain}/login/oauth2/token`
- JWKS: `https://{domain}/api/lti/security/jwks`

Blackboard:
- Issuer: `https://blackboard.com`
- Auth: `https://developer.blackboard.com/api/v1/gateway/oidcauth`
- Token: `https://developer.blackboard.com/api/v1/gateway/oauth2/jwttoken`
- JWKS: `https://developer.blackboard.com/api/v1/management/applications/{appId}/jwks.json`

Moodle:
- Issuer: `https://{yourmoodle.example.com}`
- Auth: `https://{domain}/mod/lti/auth.php`
- Token: `https://{domain}/mod/lti/token.php`
- JWKS: `https://{domain}/mod/lti/certs.php`

### Step 3: Handle launches

Listen for the `LtiLaunchValidated` event in your application:

```php
// app/Listeners/HandleLtiLaunch.php
namespace App\Listeners;

use RefBytes\Lti\Events\LtiLaunchValidated;

class HandleLtiLaunch
{
    public function handle(LtiLaunchValidated $event): void
    {
        $launch = $event->launch;

        // Find or create a user from the LTI launch
        $user = User::firstOrCreate(
            ['lti_user_id' => $launch->userId],
            [
                'name' => $launch->claim('name'),
                'email' => $launch->claim('email'),
            ],
        );

        // Log the user in
        auth()->login($user);

        // Store launch data in session for later use
        session(['lti_launch' => $launch]);
    }
}
```

Register the listener in your `EventServiceProvider`:

```php
protected $listen = [
    \RefBytes\Lti\Events\LtiLaunchValidated::class => [
        \App\Listeners\HandleLtiLaunch::class,
    ],
];
```

Note: The `LaunchController` returns a JSON response by default. To redirect users to your app after launch, you can override the controller behavior by registering your own route that calls the `LaunchValidationService` directly, or handle the redirect in your event listener using a terminable middleware.

## LTI 1.1 Support

The package also supports LTI 1.1 launches (OAuth 1.0a signed form-POSTs) alongside LTI 1.3. The same `/lti/launch` endpoint, the same `LtiLaunchValidated` event, and the same `LtiLaunchData` DTO are used — version is detected automatically based on whether the request carries `id_token` (1.3) or `oauth_consumer_key` (1.1).

### Why LTI 1.1?

LTI 1.1 is the only standard that allows **teachers to install your tool without LMS admin involvement**. Teachers paste your launch URL, a consumer key, and a shared secret directly into a course's External Tools settings. The trade-off is reduced capability:

| Capability | LTI 1.1 | LTI 1.3 |
|---|---|---|
| Teacher self-install (no admin) | ✅ | ❌ |
| OAuth 1.0a (HMAC-SHA1) | ✅ | — |
| JWT-based auth, OIDC login | — | ✅ |
| Grade passback (single score) | ✅ via Basic Outcomes | ✅ via AGS |
| Create gradebook columns | ❌ | ✅ |
| Read scores back | Limited | ✅ |
| Roster access (NRPS) | ❌ | ✅ |
| Deep Linking | ❌ (not yet supported by this package) | ✅ |
| Future-proof | ⚠️ deprecated by 1EdTech | ✅ |

Pick the version per consuming app — or support both and let the LMS decide.

### Registering a 1.1 platform

```php
use RefBytes\Lti\Facades\Lti;

Lti::registerPlatform([
    'name'          => 'Self-install consumer',
    'client_id'     => 'your-consumer-key',     // doubles as oauth_consumer_key
    'shared_secret' => 'your-shared-secret',    // stored encrypted at rest
    'version'       => 'LTI-1p0',
]);
```

The `auth_url`, `token_url`, `jwks_url`, `issuer`, and `deployment_id` fields are unused for 1.1 and can be omitted.

### Teacher install instructions

Teachers paste these three values into their course's External Tool / External App configuration:

| Field | Value |
|---|---|
| Launch URL | `https://yourdomain.com/lti/launch` |
| Consumer Key | The `client_id` value you stored |
| Shared Secret | The `shared_secret` value you stored |

That's it — no developer key, no admin involvement.

### Reading 1.1 claims

LTI 1.1 sends data as flat form params (no namespaced URLs like 1.3). Access them via the same `claim()` helper:

```php
$launch->ltiVersion;                               // 'LTI-1p0'
$launch->userId;                                   // mapped from user_id
$launch->resourceLinkId;                           // mapped from resource_link_id
$launch->isInstructor();                           // handles 1.1 short roles + LIS URNs
$launch->claim('context_title');                   // 'Algebra 101'
$launch->claim('lis_person_contact_email_primary');// 'student@example.com'
$launch->hasBasicOutcomes();                       // true if grade passback is available
```

### Grade passback with the unified `sendScore()` facade

When you want a single grade-passback call that works for both versions, use `Lti::sendScore()`:

```php
use RefBytes\Lti\Facades\Lti;

// Works for 1.3 (uses AGS) AND 1.1 (uses Basic Outcomes Service)
Lti::sendScore($launch, scoreGiven: 85.0, scoreMaximum: 100.0, comment: 'Great work!');
```

Routing rules:
- LTI 1.3 → uses the launch's AGS `lineitem` URL via `AgsClient::submitScore()`. Throws `LtiFeatureNotSupportedException` if the launch lacks an AGS endpoint or `lineitem` URL.
- LTI 1.1 → uses the launch's `lis_outcome_service_url` + `lis_result_sourcedid` via `BasicOutcomesClient::replaceResult()`. Throws `LtiFeatureNotSupportedException` if either is missing.

For advanced 1.3 workflows (creating line items, posting partial progress, reading results, etc.) the existing `Lti::createLineItem()`, `Lti::submitScore($launch, $lineItemUrl, $score)`, and `Lti::getResults()` remain available — they are 1.3-only.

For lower-level 1.1 access (delete a score, read a score back), use the `BasicOutcomesClient` directly:

```php
use RefBytes\Lti\Services\BasicOutcomesClient;

$normalized = app(BasicOutcomesClient::class)->readResult($launch);  // 0..1, or null
app(BasicOutcomesClient::class)->deleteResult($launch);
```

### Security

LTI 1.1 launches are protected by:

- **OAuth 1.0a HMAC-SHA1 signature verification** against the platform's stored `shared_secret`
- **Timestamp tolerance** (default ±5 minutes, see `lti.oauth1_timestamp_tolerance`)
- **Nonce replay protection** — used nonces are cached for `lti.oauth1_nonce_ttl` seconds (default 10 minutes)

The shared secret is stored encrypted on the model via Laravel's `encrypted` cast.

## Working with Launch Data

The `LtiLaunchData` object provides typed access to the validated JWT claims:

```php
$launch->userId;          // Platform user ID (sub claim)
$launch->messageType;     // 'LtiResourceLinkRequest' or 'LtiDeepLinkingRequest'
$launch->roles;           // Array of role URIs
$launch->targetLinkUri;   // Where the launch was aimed
$launch->resourceLinkId;  // The resource being launched
$launch->deploymentId;    // Deployment identifier
$launch->platform;        // The LtiPlatform model instance

// Access any JWT claim
$launch->claim('name');
$launch->claim('email');
$launch->claim('https://purl.imsglobal.org/spec/lti/claim/context');

// Role helpers
$launch->isInstructor();  // true if instructor role
$launch->isLearner();     // true if learner role
$launch->hasRole('http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor');

// Service discovery
$launch->hasNrps();               // true if NRPS is available
$launch->hasAgs();                // true if AGS (grade passback) is available
$launch->isDeepLinkingRequest();  // true if this is a deep linking request
```

## Deep Linking

Deep linking lets instructors browse and select content from your tool to embed in their course.

### Handling deep linking requests

Listen for the `LtiDeepLinkingRequested` event:

```php
use RefBytes\Lti\Events\LtiDeepLinkingRequested;

class HandleDeepLinking
{
    public function handle(LtiDeepLinkingRequested $event): void
    {
        $settings = $event->settings;

        // What content types the platform accepts
        $settings->acceptTypes;          // ['ltiResourceLink', 'link', ...]
        $settings->acceptMultiple;       // Can the user select multiple items?
        $settings->acceptsType('link');  // Check a specific type

        // Store launch data so you can build the response later
        session([
            'deep_linking_launch' => $event->launch,
        ]);
    }
}
```

### Building the response

After the user selects content in your UI, build and return the response:

```php
use RefBytes\Lti\DataTransferObjects\ContentItems\LtiResourceLinkItem;
use RefBytes\Lti\DataTransferObjects\ContentItems\LinkItem;
use RefBytes\Lti\Facades\Lti;

$launch = session('deep_linking_launch');

$contentItems = [
    LtiResourceLinkItem::make('https://yourtool.com/activity/42')
        ->title('Week 3 Quiz')
        ->text('Complete this quiz by Friday')
        ->lineItem(100.0, 'Quiz Score')
        ->custom(['activity_id' => '42']),

    LinkItem::make('https://yourtool.com/resources/guide')
        ->title('Study Guide'),
];

// Returns an auto-submitting HTML form that POSTs back to the LMS
return Lti::buildDeepLinkingFormResponse($launch, $contentItems);
```

### Content item types

```php
use RefBytes\Lti\DataTransferObjects\ContentItems\{
    LtiResourceLinkItem,
    LinkItem,
    HtmlItem,
    ImageItem,
    FileItem,
};

// LTI Resource Link (launches back into your tool)
LtiResourceLinkItem::make('https://yourtool.com/launch/42')
    ->title('Assignment 1')
    ->lineItem(100.0, 'Assignment Score')
    ->custom(['module' => 'quiz'])
    ->available('2026-04-01T00:00:00Z')
    ->submission('2026-04-30T23:59:59Z');

// External link
LinkItem::make('https://example.com/article')
    ->title('Required Reading')
    ->icon('https://example.com/icon.png', 32, 32);

// HTML fragment
HtmlItem::make('<p>Embedded content here</p>')
    ->title('Instructions');

// Image
ImageItem::make('https://example.com/diagram.png')
    ->title('System Architecture')
    ->width(800)
    ->height(600);

// File (URL must be accessible to the platform)
FileItem::make('https://yourtool.com/files/syllabus.pdf')
    ->title('Syllabus')
    ->expiresAt('2026-04-10T00:00:00Z');
```

## Names and Roles Provisioning Service (NRPS)

NRPS lets your tool fetch the course roster from the platform.

### Check availability and fetch members

```php
use RefBytes\Lti\Facades\Lti;

if ($launch->hasNrps()) {
    // Fetch all members (auto-follows pagination)
    $result = Lti::getMembers($launch);

    $result->context->id;     // Course ID
    $result->context->title;  // Course title

    foreach ($result->members as $member) {
        $member->userId;
        $member->name;
        $member->email;
        $member->roles;
        $member->status;       // 'Active' or 'Inactive'

        $member->isInstructor();
        $member->isLearner();
    }
}
```

### Filtering

```php
// Only learners
$result = Lti::getMembers($launch, role: 'http://purl.imsglobal.org/vocab/lis/v2/membership/Learner');

// Limit page size
$result = Lti::getMembers($launch, limit: 50);

// Filter by resource link
$result = Lti::getMembers($launch, resourceLinkId: 'link-42');
```

### Lazy iteration for large rosters

For courses with thousands of students, use lazy iteration to avoid loading everything into memory:

```php
$members = Lti::getMembersLazy($launch);

$members->each(function ($member) {
    // Process one member at a time
    // Pages are fetched on demand
});

// Or collect only what you need
$emails = Lti::getMembersLazy($launch)
    ->filter(fn ($m) => $m->isLearner())
    ->map(fn ($m) => $m->email)
    ->all();
```

## Assignment and Grade Services (AGS)

AGS lets your tool manage gradebook columns (line items), submit scores for students, and read results back from the platform.

### Check availability

```php
if ($launch->hasAgs()) {
    $ags = $launch->agsServiceInfo();

    $ags->canManageLineItems();  // Can create/update/delete line items
    $ags->canReadLineItems();    // Can list and read line items
    $ags->canPostScores();       // Can submit scores
    $ags->canReadResults();      // Can read results
}
```

### Managing line items (gradebook columns)

```php
use RefBytes\Lti\DataTransferObjects\AgsLineItem;
use RefBytes\Lti\Facades\Lti;

// List all line items
$lineItems = Lti::getLineItems($launch);

foreach ($lineItems as $item) {
    $item->id;             // Platform-assigned URL
    $item->label;          // Display name
    $item->scoreMaximum;   // Max points
    $item->resourceId;     // Your tool's resource identifier
    $item->tag;            // Metadata tag
}

// Filter line items
$quizItems = Lti::getLineItems($launch, tag: 'quiz');
$myItems = Lti::getLineItems($launch, resourceLinkId: $launch->resourceLinkId);

// Get a single line item
$item = Lti::getLineItem($launch, $lineItemUrl);

// Create a new line item
$newItem = AgsLineItem::make('Midterm Exam', 100.0)
    ->tag('exam')
    ->resourceId('midterm-2026')
    ->resourceLinkId($launch->resourceLinkId)
    ->startDateTime('2026-04-15T00:00:00Z')
    ->endDateTime('2026-04-15T23:59:59Z');

$created = Lti::createLineItem($launch, $newItem);
// $created->id now contains the platform-assigned URL

// Update a line item
$updated = AgsLineItem::make('Midterm Exam (Revised)', 120.0);
$result = Lti::updateLineItem($launch, $created->id, $updated);

// Delete a line item
Lti::deleteLineItem($launch, $created->id);
```

### Submitting scores

```php
use RefBytes\Lti\DataTransferObjects\AgsScore;
use RefBytes\Lti\Facades\Lti;

// Submit a fully graded score
$score = AgsScore::make($launch->userId)
    ->scoreGiven(85.0)
    ->scoreMaximum(100.0)
    ->comment('Well done!');

Lti::submitScore($launch, $lineItemUrl, $score);

// Submit with custom progress states
$score = AgsScore::make($studentUserId)
    ->scoreGiven(0)
    ->scoreMaximum(100.0)
    ->activityProgress(AgsScore::ACTIVITY_IN_PROGRESS)
    ->gradingProgress(AgsScore::GRADING_PENDING);

Lti::submitScore($launch, $lineItemUrl, $score);
```

**Activity progress values:** `Initialized`, `Started`, `InProgress`, `Submitted`, `Completed`

**Grading progress values:** `FullyGraded`, `Pending`, `PendingManual`, `NotReady`

By default, `AgsScore::make()` sets `activityProgress` to `Completed`, `gradingProgress` to `FullyGraded`, and `timestamp` to the current time.

### Reading results

```php
use RefBytes\Lti\Facades\Lti;

// Get all results for a line item
$results = Lti::getResults($launch, $lineItemUrl);

foreach ($results as $result) {
    $result->userId;
    $result->resultScore;    // Current grade (may be null)
    $result->resultMaximum;  // Max possible score
    $result->comment;        // Instructor feedback
}

// Filter by specific user
$results = Lti::getResults($launch, $lineItemUrl, userId: $studentId);
```

### Common workflow: Deep Linking + AGS

When you create content via deep linking with a `lineItem`, the platform creates the gradebook column automatically. Later, during a resource link launch, you can submit scores:

```php
// 1. During deep linking: create content with a grade column
$item = LtiResourceLinkItem::make('https://yourtool.com/quiz/42')
    ->title('Chapter 5 Quiz')
    ->lineItem(100.0, 'Quiz Score');

return Lti::buildDeepLinkingFormResponse($launch, [$item]);

// 2. During a resource link launch: submit the student's score
if ($launch->hasAgs()) {
    $lineItems = Lti::getLineItems($launch, resourceLinkId: $launch->resourceLinkId);

    if (! empty($lineItems)) {
        $score = AgsScore::make($launch->userId)
            ->scoreGiven($studentScore)
            ->scoreMaximum(100.0);

        Lti::submitScore($launch, $lineItems[0]->id, $score);
    }
}
```

## Tool Key Management

Your tool needs RSA keys to sign JWTs. The platform verifies these using your JWKS endpoint.

```bash
# Generate a new key
php artisan lti:generate-key

# List all keys
php artisan lti:list-keys

# Rotate: generate new key and deactivate all previous ones
php artisan lti:generate-key --deactivate-previous

# Deactivate a specific key
php artisan lti:deactivate-key {kid}
```

Or programmatically:

```php
use RefBytes\Lti\Facades\Lti;

$key = Lti::generateToolKey();
$key = Lti::rotateToolKeys(deactivatePrevious: true);
```

Key rotation is safe because:
- New and old keys overlap (both active) until you explicitly deactivate
- The JWKS endpoint serves all active keys
- Platforms cache JWKS and will pick up new keys on their next refresh

## Multi-Tenancy

If your application serves multiple tenants (e.g., organizations, schools), each tenant can have its own platform registrations and tool keys.

### Configure the tenant model and resolver

```php
// config/lti.php
'tenant_model' => \App\Models\Team::class,
'tenant_resolver' => \App\Lti\TeamTenantResolver::class,
```

### Implement the resolver

```php
namespace App\Lti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RefBytes\Lti\Contracts\TenantResolver;

class TeamTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Model
    {
        // Resolve from subdomain, header, or however your app identifies tenants
        $subdomain = explode('.', $request->getHost())[0];

        return Team::where('subdomain', $subdomain)->first();
    }
}
```

### Scope operations to a tenant

```php
// Register a platform for a specific tenant
Lti::registerPlatform([
    'tenant_id' => $team->id,
    'issuer' => 'https://canvas.instructure.com',
    // ...
]);

// Generate keys for a tenant
Lti::generateToolKey($team);

// Fetch members with tenant context
Lti::getMembers($launch, tenant: $team);
```

Platform lookups during launches are automatically scoped to the resolved tenant.

## Persisting Launches

To store launch records in the database for auditing or later retrieval:

```php
// config/lti.php
'store_launches' => true,
```

When enabled, each validated launch creates a record in the `lti_launches` table. The `LtiLaunchData` object will have a `launchId` (UUID) that you can use to look up the record:

```php
use RefBytes\Lti\Models\LtiLaunch;

$record = LtiLaunch::find($launch->launchId);
$record->claims;  // Full JWT claims as array
```

## Platform Access Tokens

For making authenticated API calls back to the platform (AGS, NRPS, etc.), get an access token:

```php
use RefBytes\Lti\Facades\Lti;

$token = Lti::getAccessToken($launch->platform, [
    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
    'https://purl.imsglobal.org/spec/lti-ags/scope/score',
]);

// Use $token as a Bearer token in HTTP requests to the platform
```

Tokens are automatically cached for their lifetime minus a 30-second buffer.

## Error Handling

The package throws typed exceptions that extend `RefBytes\Lti\Exceptions\LtiException`:

| Exception | When |
|-----------|------|
| `LtiPlatformNotFoundException` | No matching platform registration for the issuer/client_id |
| `LtiStateException` | OIDC state is missing, expired, or already consumed |
| `LtiJwtException` | JWT signature invalid, claims validation failed |
| `LtiException` | General errors (missing NRPS claim, no active keys, etc.) |

The built-in controllers catch `LtiException` and return 400 JSON responses. For custom error handling, catch these in your own code:

```php
use RefBytes\Lti\Exceptions\LtiException;

try {
    $result = Lti::getMembers($launch);
} catch (LtiException $e) {
    // Handle the error
}
```
