<div class="mb-3">
    @label
	<label for="{{ id }}" class="form-label">
        {{ label }}
    </label>
	@endlabel

    <input type="file" name="{{ name }}" id="{{ id }}" class="form-control {{ classes }}@error is-invalid@enderror" {{attributes}}>

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="invalid-feedback" style="display:{{ error_display }}">@error{{ message }}@enderror</div>
	
    @help
    <div class="help-text">
        {{ help }}
    </div>
    @endhelp

</div>
