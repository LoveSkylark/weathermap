<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weathermap Editor - Error</title>
    <style type="text/css">
        body {
            font-family: 'Lucida Grande', Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            background: #f5f5f5;
        }
        .error-box {
            background: #fee;
            border: 1px solid #f88;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            max-width: 800px;
        }
        h1 {
            color: #800;
            margin-top: 0;
        }
        code {
            background: #f0f0f0;
            padding: 10px;
            display: block;
            margin: 10px 0;
            border-radius: 3px;
            overflow-x: auto;
        }
        a {
            color: #00a;
        }
    </style>
</head>
<body>
    <h1>Weathermap Editor - Configuration Error</h1>
    
    <div class="error-box">
        <h2>⚠️ Error</h2>
        <p>{{ $error }}</p>
        
        <h3>Troubleshooting</h3>
        <ul>
            <li>Ensure the configuration directory exists and is writable by the web server</li>
            <li>Check directory permissions: <code>ls -la /opt/librenms/app/Plugins/Weathermap/configs/</code></li>
            <li>Try creating the directory if it doesn't exist: <code>mkdir -p /opt/librenms/app/Plugins/Weathermap/configs/</code></li>
            <li>Make it writable by web server: <code>chown -R www-data:www-data /opt/librenms/app/Plugins/Weathermap/configs/</code></li>
        </ul>
    </div>
    
    <p><a href="{{ url('plugin/Weathermap') }}">Back to Weathermap</a></p>
</body>
</html>
