<div class="form-check form-check-inline mb-3">
    <input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="form-check-input {{ classes }}@error is-invalid@enderror" {{attributes}}>

	@label
    <label for="{{ id }}" class="form-check-label">
        {{ label }}
    </label>
	@endlabel
	
	<!-- js validation -->
	<div id="has-error-{{ id }}" class="invalid-feedback" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

    @help
    <div class="help-text">
        {{ help }}
    </div>
    @endhelp

</div>
