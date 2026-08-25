<div class="form-group">
	@label
	<label for="{{ id }}">
		{{ label }}
	</label>
	@endlabel

	<div class="custom-file">
		<input type="file" name="{{ name }}" id="{{ id }}" class="custom-file-input {{ classes }}@error is-invalid@enderror" {{attributes}}>
		<label class="custom-file-label" for="{{ id }}">Choose file</label>
	</div>

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="invalid-feedback" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<small class="form-text text-muted">
			{{ help }}
		</small>
	@endhelp

</div>
