<div class="input-field">
	<select name="{{ name }}" id="{{ id }}" class="{{ classes }}@error invalid@enderror" {{attributes}}>
		{{ options }}
	</select>
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
