<div class="grid-x grid-margin-x">
	<div class="cell">
		@label
		<label for="{{ id }}">
			{{ label }}
		</label>
		@endlabel

		<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="{{ classes }}@error is-invalid-input@enderror" {{attributes}}>
		{{datalist}}

		<!-- js validation -->
		<span id="has-error-{{ id }}" class="form-error" style="display:{{ error_display }}">@error{{ message }}@enderror</span>

		@help
			<p class="help-text">
				{{ help }}
			</p>
		@endhelp

	</div>
</div>
