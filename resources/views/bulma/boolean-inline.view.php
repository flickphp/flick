<div class="field">
    @label
    <label for="{{ id }}" class="{{ type }}">
        <input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="{{ classes }}@error is-danger@enderror" {{attributes}}>
        {{ label }}
    </label>
    @endlabel
	
	<!-- js validation -->
	<div id="has-error-{{ id }}" class="help is-danger" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

    @help
    <div class="help">
        {{ help }}
    </div>
    @endhelp

</div>
