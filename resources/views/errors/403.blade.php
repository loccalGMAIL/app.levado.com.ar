@extends('errors.minimal')

@section('code', '403')
@section('title', 'No tenés permiso para hacer esto')

@section('message')
@php
    // Mostrar el mensaje del abort() solo si es uno nuestro (en español);
    // el default de Laravel viene en inglés y no aporta nada al usuario.
    $custom = $exception?->getMessage();
    $isDefault = blank($custom) || $custom === 'This action is unauthorized.' || $custom === 'Forbidden';
@endphp
{{ $isDefault ? 'Tu rol no permite realizar esta acción. Si creés que deberías poder, hablá con el dueño del negocio.' : $custom }}
@endsection
