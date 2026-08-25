<div class="my-4">
	@label
    <label for="{{ id }}" class="form-label">
        {{ label }}
    </label>
	@endlabel

    <select name="{{ name }}" id="{{ id }}" class="form-control {{ classes }}" {{attributes}}>
        {{ options }}
    </select>
    
    @help
    <div class="help-text">
        {{ help }}
    </div>
    @endhelp

    @error
    <div class="invalid-feedback d-block">
        {{ message }}
    </div>
    @enderror
</div>
