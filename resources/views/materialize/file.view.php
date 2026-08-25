<div class="file-field input-field">
	<div class="btn">
		@label
		<span>{{ label }}</span>
		@endlabel
		<input type="file" name="{{ name }}" id="{{ id }}" class="{{ classes }}@error invalid@enderror" {{attributes}}>
	</div>
	<div class="file-path-wrapper">
		<input class="file-path validate" type="text">
	</div>

	<!-- js validation -->
	<span id="has-error-{{ id }}" class="helper-text red-text" style="display:{{ error_display }}">@error{{ message }}@enderror</span>

	@help
		<span class="helper-text">
			{{ help }}
		</span>
	@endhelp

</div>
