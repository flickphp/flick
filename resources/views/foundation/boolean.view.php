<div class="grid-x grid-margin-x">
	<div class="cell">
		<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="{{ classes }}@error is-invalid-input@enderror" {{attributes}}>
		@label
		<label for="{{ id }}">
			{{ label }}
		</label>
		@endlabel

		<!-- js validation -->
		<span id="has-error-{{ id }}" class="form-error" style="display:{{ error_display }}">@error{{ message }}@enderror</span>

		@help
			<p class="help-text">
				{{ help }}
			</p>
		@endhelp

	</div>
</div>
