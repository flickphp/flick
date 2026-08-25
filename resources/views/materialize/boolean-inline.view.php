<label style="margin-right: 1rem;">
	<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="filled-in {{ classes }}@error invalid@enderror" {{attributes}}>
	@label
	<span>{{ label }}</span>
	@endlabel

	<!-- js validation -->
	<span id="has-error-{{ id }}" class="helper-text red-text" style="display:{{ error_display }}">@error{{ message }}@enderror</span>

	@help
		<span class="helper-text">
			{{ help }}
		</span>
	@endhelp

</label>
