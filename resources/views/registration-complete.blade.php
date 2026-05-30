<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Complete</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; max-width: 480px; margin: 4rem auto; padding: 0 1.5rem; color: #222; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { line-height: 1.5; color: #555; }
        button { background: #2563eb; color: white; border: 0; padding: 0.75rem 1.5rem; font-size: 1rem; border-radius: 6px; cursor: pointer; margin-top: 1rem; }
        button:hover { background: #1d4ed8; }
        .platform { font-weight: 600; color: #111; }
    </style>
</head>
<body>
    <h1>Registration Complete</h1>
    <p>Your tool has been registered with <span class="platform">{{ $platform->name ?: $platform->issuer }}</span>.</p>
    <p>Click the button below to finish the registration and return to your LMS.</p>
    <button type="button" id="lti-close-button">Complete Registration</button>

    <script>
        document.getElementById('lti-close-button').addEventListener('click', function () {
            (window.opener || window.parent).postMessage(
                { subject: 'org.imsglobal.lti.close' },
                '*'
            );
        });
    </script>
</body>
</html>
