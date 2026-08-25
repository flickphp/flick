<div class="grid-x grid-margin-x">
	<div class="cell">
		@label
		<label for="{{ id }}">
			{{ label }}
		</label>
		@endlabel

		<textarea name="{{ name }}" id="{{ id }}" class="{{ classes }}@error is-invalid-input@enderror" {{attributes}}>{{ value }}</textarea>

		<!-- js validation -->
		<span id="has-error-{{ id }}" class="form-error" style="display:{{ error_display }}">@error{{ message }}@enderror</span>

		@help
			<p class="help-text">
				{{ help }}
			</p>
		@endhelp

	</div>
</div>
