@extends('errors.minimal')

@section('code', '419')
@section('title', 'La sesión expiró')
@section('message', 'Pasó demasiado tiempo desde que abriste esta página. Volvé a intentarlo.')

@section('actions')
    <a class="btn" href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}">Reintentar</a>
@endsection
