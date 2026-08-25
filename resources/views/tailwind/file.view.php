<div class="mb-4">
	@label
	<label for="{{ id }}" class="block text-sm/6 font-medium text-gray-900">
		{{ label }}
	</label>
	@endlabel

	<input type="file" name="{{ name }}" id="{{ id }}" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 {{ classes }}@error border-red-500@enderror" {{attributes}}>

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="mt-1 text-sm text-red-600" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<p class="mt-1 text-sm text-gray-500">
			{{ help }}
		</p>
	@endhelp

</div>
