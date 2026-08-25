<div class="form-group">
	@label
	<label for="{{ id }}">
		{{ label }}
	</label>
	@endlabel

	<select name="{{ name }}" id="{{ id }}" class="form-control {{ classes }}@error is-invalid@enderror" {{attributes}}>
		{{ options }}
	</select>

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="invalid-feedback" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<small class="form-text text-muted">
			{{ help }}
		</small>
	@endhelp

</div>
