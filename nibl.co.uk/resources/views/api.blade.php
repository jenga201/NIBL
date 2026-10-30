<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NIBL API</title>
    <link rel="stylesheet" href="/vendor/swagger-ui/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="/vendor/swagger-ui/swagger-ui-bundle.js"></script>
    <script src="/vendor/swagger-ui/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function () {
            window.ui = SwaggerUIBundle({
                url: "/docs/api.json",
                dom_id: "#swagger-ui",
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
                layout: "StandaloneLayout",
            });
        };
    </script>
</body>
</html>
