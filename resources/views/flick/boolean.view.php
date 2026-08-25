<div class="flick-field flick-checkbox">
	<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="flick-checkbox-input {{ classes }}@error has-error@enderror" {{attributes}}>

	@label
	<label for="{{ id }}" class="flick-checkbox-label">
		{{ label }}
	</label>
	@endlabel

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="flick-error" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<div class="flick-help">
			{{ help }}
		</div>
	@endhelp

</div>
