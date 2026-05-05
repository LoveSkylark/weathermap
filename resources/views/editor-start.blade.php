<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Weathermap Editor</title>
    <link rel="stylesheet" type="text/css" media="screen" href="{{ asset('plugins/Weathermap/editor-resources/oldeditor.css') }}" />
    <script type="text/javascript" src="{{ asset('plugins/Weathermap/vendor/jquery/dist/jquery.min.js') }}"></script>
    <style type="text/css">
        body {
            font-family: 'Lucida Grande', Arial, sans-serif;
            font-size: 10pt;
            margin: 20px;
            background: #f5f5f5;
        }
        .editor-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .welcome-box {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1, h2 {
            color: #333;
        }
        .note {
            background: #055;
            border: 2px dashed red;
            color: white;
            padding: 10px;
            font-size: 90%;
            margin: 15px 0;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 3px;
        }
        .section h3 {
            margin-top: 0;
            color: #333;
        }
        input[type="text"],
        input[type="submit"],
        input[type="button"] {
            padding: 5px 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        input[type="submit"],
        input[type="button"] {
            background: #007bff;
            color: white;
            cursor: pointer;
            border: none;
        }
        input[type="submit"]:hover,
        input[type="button"]:hover {
            background: #0056b3;
        }
        .maps-list {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
        }
        .map-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .map-item:last-child {
            border-bottom: none;
        }
        .map-item a {
            color: #007bff;
            text-decoration: none;
        }
        .map-item a:hover {
            text-decoration: underline;
        }
        .map-status {
            font-size: 12px;
            color: #666;
        }
        small {
            color: #666;
            font-size: 90%;
        }
    </style>
</head>
<body>
    <div class="editor-container">
        <div class="welcome-box">
            <h1>PHP Weathermap Editor</h1>
            
            <p>Welcome to the PHP Weathermap editor.</p>
            
            <div class="note">
                <b>NOTE:</b> This editor is not finished! There are many features of Weathermap 
                that you will be missing out on if you choose to use the editor only. These include: 
                curves, node offsets, font definitions, colour changing, per-node/per-link settings 
                and image uploading. You CAN use the editor without damaging these features if you 
                added them by hand, however.
            </div>

            <div class="section">
                <h3>Create A New Map</h3>
                <form method="GET" action="{{ $create_action }}">
                    <p>
                        Map name: <input type="text" name="mapname" size="30" placeholder="mymap.conf">
                        <input type="submit" value="Create">
                    </p>
                    <p><small>Note: filenames must contain no spaces and end in .conf</small></p>
                </form>
            </div>

            @if (count($maps) > 0)
                <div class="section">
                    <h3>Existing Maps</h3>
                    <div class="maps-list">
                        @foreach ($maps as $map)
                            <div class="map-item">
                                <div>
                                    <div>
                                        <strong>{{ $map['name'] }}</strong>
                                        @if (!$map['writable'])
                                            <span class="map-status">(read-only)</span>
                                        @endif
                                    </div>
                                    <small>{{ $map['title'] }}</small>
                                </div>
                                <div>
                                    @if ($map['readable'])
                                        <a href="{{ $map['edit_url'] }}">Edit</a>
                                    @else
                                        <span class="map-status">Not readable</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="section" style="background: #f9f9f9; color: #666;">
                    <p>No existing maps found. Create a new one above to get started!</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
