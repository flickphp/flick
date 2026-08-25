<div class="input-field">
	<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="{{ classes }}@error invalid@enderror" {{attributes}}>
	{{datalist}}
	@label
	<label for="{{ id }}">
		{{ label }}
	</label>
	@endlabel

	<!-- js validation -->
	<span id="has-error-{{ id }}" class="helper-text" data-error="" style="display:{{ error_display }}">@error{{ message }}@enderror</span>

	@help
		<span class="helper-text">
			{{ help }}
		</span>
	@endhelp

</div>
