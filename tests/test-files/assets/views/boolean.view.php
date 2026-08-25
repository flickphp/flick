<div class="form-check my-4">
	<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="form-check-input {{ classes }}" {{attributes}}>

	@label
	<label for="{{ id }}" class="form-check-label">
		{{ label }}
	</label>
	@endlabel

	@help
	<div class="help-text">
		{{ help }}
	</div>
	@endhelp

	@error
	<div class="invalid-feedback d-block">
		{{ message }}
	</div>
	@enderror
</div>
