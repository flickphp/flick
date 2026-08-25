<div class="flick-field">
	@label
	<label for="{{ id }}" class="flick-label">
		{{ label }}
	</label>
	@endlabel

	<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="flick-input {{ classes }}@error has-error@enderror" {{attributes}}>
	{{datalist}}

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="flick-error" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<div class="flick-help">
			{{ help }}
		</div>
	@endhelp

</div>
