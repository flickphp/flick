<div class="field">
    @label
    <label for="{{ id }}" class="label">
        {{ label }}
    </label>
    @endlabel

    <div class="control">
        <textarea name="{{ name }}" id="{{ id }}" class="textarea {{ classes }}@error is-danger@enderror" {{attributes}}>{{ value }}</textarea>
    </div>
	
	<!-- js validation -->
	<div id="has-error-{{ id }}" class="help is-danger" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

    @help
    <p class="help">
        {{ help }}
    </p>
    @endhelp

</div>
