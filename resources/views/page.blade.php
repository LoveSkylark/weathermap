@if (!$writable)
    <div class="alert alert-warning">
        <strong>Warning:</strong> The map config directory is not writable by the web server user.
        You will not be able to edit any files until this is corrected.
    </div>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Installation instructions</h3>
        </div>
        <div class="panel-body">
            <code>{!! $readme !!}</code>
        </div>
    </div>
@else
    <div class="container-fluid">
        <p>
            Click <a href="{{ $editor_url }}">here to access the editor</a>
            where you can create and manage maps.
        </p>
        <div class="row">
            @forelse ($images as $map)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ $map['html_url'] }}">
                        <img class="img-responsive img-thumbnail" src="{{ $map['image_url'] }}" />
                    </a>
                </div>
            @empty
                <div class="col-xs-12">
                    <p class="text-muted">No maps found. Use the editor to create one.</p>
                </div>
            @endforelse
        </div>
    </div>
@endif
