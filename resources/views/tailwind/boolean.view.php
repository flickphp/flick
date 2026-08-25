<div class="mb-4">
	<div class="flex items-start">
		<div class="flex h-5 items-center">
			<input type="{{ type }}" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 {{ classes }}@error border-red-500@enderror" {{attributes}}>
		</div>
		@label
		<div class="ml-3 text-sm">
			<label for="{{ id }}" class="text-sm/6 font-medium text-gray-900">
				{{ label }}
			</label>
		</div>
		@endlabel
	</div>

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="mt-1 text-sm text-red-600" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<p class="mt-1 text-sm text-gray-500">
			{{ help }}
		</p>
	@endhelp

</div>
