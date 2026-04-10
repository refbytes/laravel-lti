@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# laravel-lti

- LTI 1.3 launch events: listen for `LtiLaunchValidated` (standard launch) and `LtiDeepLinkingRequested` (Deep Linking). Both carry a `LtiLaunchData $launch` DTO.
- LTI routes (`/lti/login`, `/lti/launch`, `/lti/jwks`) are registered automatically with no middleware. Do not add `web` or `auth` middleware — cross-origin POST from the LMS requires no CSRF protection.
- Use `$launch->claim($key)` to read JWT claims. Pass the full IMS URL as the key (e.g. `https://purl.imsglobal.org/spec/lti/claim/context`). Dot-notation fallback is supported but exact key lookup takes priority.
- Check service availability before using AGS or NRPS: `$launch->hasAgs()`, `$launch->hasNrps()`. Then check capability: `$launch->agsServiceInfo()->canPostScores()`.
- `AgsLineItem` is not `readonly` — it uses a fluent builder. Start with `AgsLineItem::make($label, $scoreMaximum)` then chain setters.
- `AgsScore` defaults: `activityProgress=Completed`, `gradingProgress=FullyGraded`, `timestamp=now()`. Override with fluent setters before submitting.
- Platform records live in the `lti_platforms` table (Eloquent model: `LtiPlatform`). Create one per LMS connection with issuer, client_id, auth/token/jwks URLs.
- A tool keypair is required: run `{{ $assist->artisanCommand('lti:generate-key') }}` after install. The JWKS endpoint at `/lti/jwks` serves the public key to the LMS.
- Multi-tenancy is opt-in via `tenant_model` and `tenant_resolver` in `config/lti.php`. When enabled, pass `tenant: $tenant` as a named argument to all `AgsClient`, `NrpsClient`, and `Lti::` facade calls.
- `store_launches` in `config/lti.php` defaults to `false`. Enable it to persist launches to `lti_launches` for audit or re-use.
