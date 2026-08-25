<div class="form-group">
	@label
	<label for="{{ id }}">
		{{ label }}
	</label>
	@endlabel

	<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="form-control {{ classes }}@error is-invalid@enderror" {{attributes}}>
	{{datalist}}

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="invalid-feedback" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<small class="form-text text-muted">
			{{ help }}
		</small>
	@endhelp

</div>
