<!DOCTYPE html>
<html>
<head>
    <title>Redirecting...</title>
</head>
<body>
    <form id="ltideeplinking" method="POST" action="{{ $returnUrl }}">
        <input type="hidden" name="JWT" value="{{ $jwt }}" />
    </form>
    <script>document.getElementById('ltideeplinking').submit();</script>
</body>
</html>
