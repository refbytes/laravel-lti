# I Built a Laravel Package for LTI 1.3 — and I'm Excited to Use It Everywhere

If you've never heard of LTI, count yourself lucky. But if you work anywhere near the EdTech space — building tools that need to plug into Canvas, Blackboard, Moodle, or any other Learning Management System — you've almost certainly run into it. And if you've tried to implement it in PHP, you know how much of a mess it can be.

I've spent a lot of time navigating that mess. So I built `laravel-lti`, a Laravel package that handles the full LTI 1.3 integration so you don't have to.

---

## What Even Is LTI?

LTI stands for Learning Tools Interoperability. At its core, it answers a simple question: how does a student click a link inside their LMS and land inside your app — already logged in, already in the right context — without having to create a separate account or paste in an enrollment code?

That's a genuinely useful problem to solve, and LTI does solve it. LTI 1.3 is the modern, secure version of the standard. It uses OIDC for identity, JWTs for tamper-proof data transfer, and RSA keypairs so both sides can verify who they're talking to.

The problem is that "modern and secure" also means "a lot of moving parts."

---

## The Mess You'd Have to Deal With Yourself

Before I built this package, connecting a Laravel app to an LMS involved:

- Implementing an OIDC third-party login flow (a multi-step redirect sequence that happens before a user ever lands in your app)
- Fetching the platform's JWKS endpoint, parsing the RSA key, verifying the JWT signature, and validating a handful of required claims
- Generating your *own* RSA keypair, serving your *own* JWKS endpoint so the LMS can verify your signatures
- Doing all of this statelessly, because LTI launches come in as cross-origin POST requests — no cookies, no sessions

And that's just to get a user through the door. If you want to actually *do* something useful — embed resources, read the class roster, send grades back — there are three separate service specs on top of that.

---

## What the Package Takes Care Of

### The Login Flow

The OIDC login initiation is handled entirely by the package. You register one route in your LMS, and the package takes care of generating state, caching it securely, and redirecting back to the platform. By the time a launch reaches your app, all of that has already happened.

### JWT Validation

Once the LMS redirects the user back to your app with a signed JWT, the package verifies the signature against the platform's JWKS endpoint, validates every required claim, and fires an event. Your app just listens:

```php
use RefBytes\Lti\Events\LtiLaunchValidated;

Event::listen(LtiLaunchValidated::class, function (LtiLaunchValidated $event) {
    $launch = $event->launch;

    $userId = $launch->userId;
    $courseName = $launch->claim('https://purl.imsglobal.org/spec/lti/claim/context')['title'];

    if ($launch->isInstructor()) {
        // show instructor view
    }
});
```

That's it. No middleware to wire up, no raw POST body parsing, no JWT library calls scattered through your controller.

### Your Own JWKS Endpoint

The LMS also needs to verify *your* signatures. The package ships with an Artisan command to generate an RSA keypair and automatically serves a JWKS endpoint at `/lti/jwks`. Key rotation is supported too — old keys stay valid while new ones take over.

```bash
php artisan lti:generate-key
```

### Deep Linking

Deep Linking is what lets instructors embed specific content from your tool into their course. Without a package, building the signed JWT response payload is tedious and error-prone. With this one, it looks like this:

```php
use RefBytes\Lti\DataTransferObjects\ContentItems\LtiResourceLinkItem;

$item = LtiResourceLinkItem::make('Chapter 3 Quiz', 'https://yourtool.com/quiz/42')
    ->description('10-question quiz on Chapter 3')
    ->custom(['quizId' => '42']);

return response(Lti::buildDeepLinkingFormResponse($launch, [$item]));
```

The package signs the response, builds the auto-submitting form, and redirects the instructor back to the LMS.

### Grade Passback (AGS)

This is the one that used to fill me with dread. Assignment and Grade Services involves OAuth2 token exchange, creating or finding a line item in the gradebook, and then posting a score with the right content types and status values.

Now it's three lines:

```php
use RefBytes\Lti\DataTransferObjects\AgsScore;

app(AgsClient::class)->submitScore($launch, $lineItemUrl,
    AgsScore::make($launch->userId)->scoreGiven(92.0)->scoreMaximum(100.0)
);
```

The OAuth2 token is fetched and cached automatically. The right content types are handled internally. You just pass in a score.

### Roster Access (NRPS)

Names and Roles Provisioning Service lets you pull the full class roster from the LMS — names, emails, roles — without asking students to manually enroll. The package handles pagination automatically, so even large courses just work:

```php
$members = Lti::getMembers($launch);
// or lazily, for large rosters:
Lti::getMembersLazy($launch)->each(fn ($member) => /* ... */);
```

---

## The Part I'm Most Excited About: Multi-Tenancy

I've built a lot of SaaS tools for the education market, and the pattern is almost always the same: one app, many institutions. Every institution has its own LMS connection — its own issuer URL, its own client ID, its own keys.

This package is designed for that from the ground up. Point it to your tenant resolver in the config, and it passes the resolved tenant through every service call. You don't have to rebuild this wiring in every project.

```php
// config/lti.php
'tenant_model'    => App\Models\Organization::class,
'tenant_resolver' => App\Resolvers\TenantResolver::class,
```

That one config change means the package knows which platform credentials to use for each request, scoped to the right tenant. I've already got two projects in mind where this is exactly what I need.

---

## What's Next

Getting LTI 1.3 right has always felt like the kind of thing you either dedicate a sprint to or outsource to a vendor. I wanted a third option: drop a package in, listen for events, and ship.

I'm genuinely excited about how this turned out. The full LTI Advantage spec — Deep Linking, NRPS, and AGS — is covered, the API feels natural for Laravel developers, and the multi-tenant design means I can reach for it across projects without reinventing the wheel each time.

If you're building anything in the EdTech space with Laravel, give it a try:

```bash
composer require refbytes/laravel-lti
```

I'd love to hear what you're building with it.
