<div class="inline-flex items-center mr-4">
	<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 {{ classes }}@error border-red-500@enderror" {{attributes}}>
	@label
	<label for="{{ id }}" class="ml-2 text-sm/6 font-medium text-gray-900">
		{{ label }}
	</label>
	@endlabel

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="ml-2 text-sm text-red-600" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<p class="ml-2 text-sm text-gray-500">
			{{ help }}
		</p>
	@endhelp

</div>
