<div class="mb-4">
	@label
	<label for="{{ id }}" class="block text-sm/6 font-medium text-gray-900">
		{{ label }}
	</label>
	@endlabel

	<textarea name="{{ name }}" id="{{ id }}" class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 {{ classes }}@error border-red-500@enderror" {{attributes}}>{{ value }}</textarea>

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="mt-1 text-sm text-red-600" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<p class="mt-1 text-sm text-gray-500">
			{{ help }}
		</p>
	@endhelp

</div>
