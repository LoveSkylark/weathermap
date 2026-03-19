<div class="form-group">
    <label for="sort_if_by">Sort interfaces by</label>
    <select name="sort_if_by" id="sort_if_by" class="form-control">
        @foreach ($sort_options as $opt)
            <option value="{{ $opt }}" @selected($sort_if_by === $opt)>{{ $opt }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label for="show_interfaces">Show interfaces for device</label>
    <input type="text"
           name="show_interfaces"
           id="show_interfaces"
           class="form-control"
           value="{{ $show_interfaces }}"
           placeholder="all / none / &lt;device_id&gt;">
    <p class="help-block">
        <code>all</code> — show all devices &nbsp;|&nbsp;
        <code>none</code> — disable interface list &nbsp;|&nbsp;
        integer — pre-select a specific device ID
    </p>
</div>
