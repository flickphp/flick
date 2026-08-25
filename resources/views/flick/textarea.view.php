<div class="flick-field">
	@label
	<label for="{{ id }}" class="flick-label">
		{{ label }}
	</label>
	@endlabel

	<textarea name="{{ name }}" id="{{ id }}" class="flick-input flick-textarea {{ classes }}@error has-error@enderror" {{attributes}}>{{ value }}</textarea>

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="flick-error" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<div class="flick-help">
			{{ help }}
		</div>
	@endhelp

</div>
