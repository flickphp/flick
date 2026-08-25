<div class="mb-4">
	@label
	<label for="{{ id }}" class="block text-sm/6 font-medium text-gray-900">
		{{ label }}
	</label>
	@endlabel

	<select name="{{ name }}" id="{{ id }}" class="w-full appearance-none rounded-md bg-white py-1.5 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 {{ classes }}@error border-red-500@enderror" {{attributes}}>
		{{ options }}
	</select>

	<!-- js validation -->
	<div id="has-error-{{ id }}" class="mt-1 text-sm text-red-600" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

	@help
		<p class="mt-1 text-sm text-gray-500">
			{{ help }}
		</p>
	@endhelp

</div>
