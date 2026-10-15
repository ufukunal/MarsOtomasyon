@props(['field'])
@error($field)
    <small {{ $attributes->class(['v65-input-error']) }} role="alert">{{ $message }}</small>
@enderror
