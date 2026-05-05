<!DOCTYPE html>
<html lang="en">
<head>
    <title>Weathermap Pre-Install Checker</title>
    <style type="text/css">
        body {
            font-family: 'Lucida Grande', Arial, sans-serif;
            font-size: 10pt;
            margin: 20px;
            background: #f5f5f5;
        }
        p {
            margin-bottom: 10px;
            margin-top: 10px;
        }
        h1, h2 {
            color: #333;
        }
        table {
            margin: 20px 0;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        table tr:hover {
            background: #f9f9f9;
        }
        .critical {
            width: 600px;
            padding: 15px;
            background: #fee;
            border: 1px solid #f88;
            border-radius: 4px;
            margin: 20px 0;
        }
        .noncritical {
            width: 600px;
            padding: 15px;
            background: #ffe;
            border: 1px solid #fb8;
            border-radius: 4px;
            margin: 20px 0;
        }
        .ok {
            width: 600px;
            padding: 15px;
            background: #efe;
            border: 1px solid #8f8;
            border-radius: 4px;
            margin: 20px 0;
        }
        .status-ok {
            color: #080;
            font-weight: bold;
        }
        .status-warning {
            color: #880;
            font-weight: bold;
        }
        .status-critical {
            color: #800;
            font-weight: bold;
        }
        .function-name {
            font-family: monospace;
            font-weight: bold;
        }
        .tick {
            color: #080;
            font-weight: bold;
        }
        .cross {
            color: #800;
            font-weight: bold;
        }
        .minor {
            color: #888;
            font-style: italic;
        }
        ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        code {
            background: #f0f0f0;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>Weathermap Pre-install Checker</h1>

    <p>This page checks for common problems with your PHP and server environment that may stop Weathermap from working.</p>

    <h2>PHP Basics</h2>
    <p>This is PHP Version <strong>{{ $php_version }}</strong> running on "{{ $php_os }}" with a memory_limit of <strong>{{ $mem_allowed }}</strong>.</p>
    @if ($mem_warning)
        <p style="color: #880;">⚠️ {{ $mem_warning }}</p>
    @endif
    <p>The php.ini file was: <code>{{ $ini_file }}</code></p>
    <p>{{ $extra_ini }}</p>

    <h2>PHP Extensions</h2>
    <p>Some parts of Weathermap need special support in your PHP installation to work.</p>
    <p>{{ $gd_string }}</p>

    <table>
        <thead>
            <tr>
                <th>Function</th>
                <th>Available</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($functions as $function => $info)
                <tr>
                    <td class="function-name">{{ $function }}()</td>
                    <td>
                        @if ($info['exists'])
                            <span class="tick">✓ YES</span>
                        @else
                            <span class="cross">✗ NO</span>
                        @endif
                    </td>
                    <td>
                        @if (!$info['exists'])
                            @if ($info['critical'])
                                <strong style="color: #800;">CRITICAL</strong>
                            @elseif (!$info['minor'])
                                <strong style="color: #880;">Non-Critical</strong>
                            @else
                                <span class="minor">Minor</span>
                            @endif
                            —
                        @endif
                        This is required for {{ $info['affects'] }}. It is {{ $info['description'] }}.
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($critical_count > 0 || $noncritical_count > 0)
        <p>If these functions are not found, you may need to:</p>
        <ul>
            <li>Check that the 'extension=' line for that extension is uncommented in your php.ini file (then restart your webserver), or</li>
            <li>Install the extension, if it isn't installed already</li>
        </ul>
        <p>On Debian/Ubuntu systems, you may also need to use the <code>phpenmod</code> command to enable the extension.</p>
        <p>The details of how this is done will depend on your operating system. Usually, you would install a package (apt/yum/dnf) or enable the extension in php.ini. Consult your PHP documentation for more information.</p>
    @endif

    @if ($critical_count > 0)
        <div class="critical">
            <p><strong>⚠️ There are problems with your PHP or server environment that will stop Weathermap from working.</strong></p>
            <p>You need to correct these issues if you wish to use Weathermap.</p>
        </div>
    @elseif ($noncritical_count > 0)
        <div class="noncritical">
            <p><strong>⚠️ Some features of Weathermap will not be available to you</strong>, due to lack of support in your PHP installation.</p>
            <p>You can still proceed with Weathermap though.</p>
        </div>
    @else
        <div class="ok">
            <p><strong>✓ OK! Your PHP and server environment *seems* to have support for ALL of the Weathermap features.</strong></p>
            <p>Make sure you have run this script BOTH as a web page and from the CLI to be sure, however.</p>
        </div>
    @endif

</body>
</html>
