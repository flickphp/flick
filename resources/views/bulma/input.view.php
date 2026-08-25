<div class="field">
    @label
    <label for="{{ id }}" class="label">
        {{ label }}
    </label>
    @endlabel

    <div class="control">
        <input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="input {{ classes }}@error is-danger@enderror" {{attributes}}>
        {{datalist}}
    </div>

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="help is-danger" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

    @help
    <p class="help">
        {{ help }}
    </p>
    @endhelp

</div>
