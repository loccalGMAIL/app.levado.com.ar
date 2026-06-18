@props(['disabled' => false])
@php
    $name = $attributes->get('name');
    $hasError = $name && $errors->has($name);
@endphp
<input @disabled($disabled) {{ $attributes->merge(['class' => $hasError
    ? 'border-red-500 focus:border-red-500 focus:ring-red-500 rounded-md shadow-sm'
    : 'border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm']) }}>
