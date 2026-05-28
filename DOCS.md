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

These routes intentionally have no CSRF middleware since LTI launches are cross-origin POST requests from the LMS platform.

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
