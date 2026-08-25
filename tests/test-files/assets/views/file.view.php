<div class="my-4">
    @label
	<label for="{{ id }}" class="form-label">
        {{ label }}
    </label>
	@endlabel

    <input type="file" name="{{ name }}" id="{{ id }}" value="{{ value }}" class="form-control {{ classes }}" {{attributes}}>

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
