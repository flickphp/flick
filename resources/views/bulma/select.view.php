<div class="field">
    @label
    <label for="{{ id }}" class="label">
        {{ label }}
    </label>
    @endlabel

    <div class="control">
        <div class="select @attributes('multiple') is-multiple @endattributes">
            <select name="{{ name }}" id="{{ id }}" class="{{ classes }}@error is-danger@enderror" {{attributes}}>
                {{ options }}
            </select>
        </div>
    </div>
	
	<!-- js validation -->
	<div id="has-error-{{ id }}" class="help is-danger" style="display:{{ error_display }}">@error{{ message }}@enderror</div>

    @help
    <div class="help">
        {{ help }}
    </div>
    @endhelp

</div>
