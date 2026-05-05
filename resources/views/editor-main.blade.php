<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weathermap Editor: {{ $map }}</title>
    <link rel="stylesheet" type="text/css" media="screen" href="{{ asset('plugins/Weathermap/editor-resources/oldeditor.css') }}" />
    <script type="text/javascript" src="{{ asset('plugins/Weathermap/vendor/jquery/dist/jquery.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('plugins/Weathermap/editor-resources/editor.js') }}"></script>
    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        #editor-container {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        #editor-header {
            background: #f5f5f5;
            border-bottom: 1px solid #ddd;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: bold;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #editor-content {
            flex: 1;
            overflow: auto;
            background: white;
        }
        .editor-toolbar {
            background: white;
            border-bottom: 1px solid #ddd;
            padding: 10px 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .editor-toolbar button {
            padding: 6px 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .editor-toolbar button:hover {
            background: #0056b3;
        }
        .editor-main {
            display: flex;
            gap: 10px;
            padding: 10px;
            height: calc(100% - 60px);
        }
        .editor-canvas {
            flex: 1;
            background: white;
            border: 1px solid #ddd;
            border-radius: 3px;
            overflow: auto;
            position: relative;
        }
        .editor-properties {
            width: 300px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 3px;
            overflow: auto;
            padding: 10px;
        }
        #map-canvas {
            background: white;
            cursor: crosshair;
        }
        .status-bar {
            background: #f5f5f5;
            border-top: 1px solid #ddd;
            padding: 5px 15px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div id="editor-container">
        <div id="editor-header">
            <span>Weathermap Editor: <strong>{{ $map }}</strong></span>
            <a href="{{ url('plugin/Weathermap/editor') }}" style="color: #007bff; text-decoration: none;">← Back to Map List</a>
        </div>

        <div class="editor-toolbar">
            <button id="btn-load">Load Map</button>
            <button id="btn-save">Save Config</button>
            <button id="btn-redraw">Redraw</button>
            <button id="btn-fontsamples">Font Samples</button>
            <button id="btn-reset">Reset</button>
            <button id="btn-cancel">Cancel</button>
        </div>

        <div class="editor-main">
            <div class="editor-canvas">
                <img id="map-canvas" src="{{ $api_url }}/draw/{{ urlencode($map) }}" alt="Map" />
            </div>
            <div class="editor-properties">
                <h4>Properties</h4>
                <div id="properties-panel">
                    <p>Click on the map to edit properties...</p>
                </div>
            </div>
        </div>

        <div class="status-bar">
            <span id="status-text">Ready</span>
        </div>
    </div>

    <script type="text/javascript">
        // Configuration for editor
        const EDITOR_CONFIG = {
            mapname: '{{ $map }}',
            api_url: '{{ $api_url }}',
            asset_url: '{{ $asset_url }}',
        };

        // Initialize editor
        $(document).ready(function() {
            console.log('Initializing editor with config:', EDITOR_CONFIG);
            
            // Placeholder for editor initialization
            // This would load the full editor.js functionality
            updateStatus('Editor ready');
        });

        function updateStatus(msg) {
            $('#status-text').text(msg);
        }

        // Button handlers
        $('#btn-load').click(function() {
            updateStatus('Loading map configuration...');
            // TODO: Implement
        });

        $('#btn-save').click(function() {
            updateStatus('Saving configuration...');
            // TODO: Implement
        });

        $('#btn-redraw').click(function() {
            updateStatus('Redrawing map...');
            location.reload();
        });

        $('#btn-fontsamples').click(function() {
            window.open(EDITOR_CONFIG.api_url + '/font-samples/' + EDITOR_CONFIG.mapname);
        });

        $('#btn-reset').click(function() {
            if (confirm('Reset all changes?')) {
                location.reload();
            }
        });

        $('#btn-cancel').click(function() {
            window.location = '{{ url("plugin/Weathermap/editor") }}';
        });
    </script>
</body>
</html>
