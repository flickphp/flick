<div class="field">
	<div class="file">
		<label for="{{ id }}" class="file-label">
			<input type="file" name="{{ name }}" id="{{ id }}" class="file-input {{ classes }}@error is-danger@enderror" {{attributes}}>
			<span class="file-cta">
				<span class="file-icon">
					&#8593;
				</span>
				@label
				<span class="file-label">
					{{ label }}
				</span>
				@endlabel
			</span>
		</label>
		
		<!-- js validation -->
		<div id="has-error-{{ id }}" class="help is-danger" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

		@help
		<p class="help">
			{{ help }}
		</p>
		@endhelp

	</div>
</div>
