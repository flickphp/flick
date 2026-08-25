<div class="grid-x grid-margin-x">
	<div class="cell">
		@label
		<label for="{{ id }}" class="button">
			{{ label }}
		</label>
		@endlabel

		<input type="file" name="{{ name }}" id="{{ id }}" class="show-for-sr {{ classes }}@error is-invalid-input@enderror" {{attributes}}>

		<!-- js validation -->
		<span id="has-error-{{ id }}" class="form-error" style="display:{{ error_display }}">@error{{ message }}@enderror</span>

		@help
			<p class="help-text">
				{{ help }}
			</p>
		@endhelp

	</div>
</div>
