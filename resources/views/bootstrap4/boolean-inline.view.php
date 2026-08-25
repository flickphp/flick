<div class="form-check form-check-inline">
	<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="form-check-input {{ classes }}@error is-invalid@enderror" {{attributes}}>
	@label
	<label for="{{ id }}" class="form-check-label">
		{{ label }}
	</label>
	@endlabel

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="invalid-feedback" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<small class="form-text text-muted">
			{{ help }}
		</small>
	@endhelp

</div>
