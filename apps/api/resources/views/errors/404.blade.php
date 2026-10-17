@extends('errors.layout')

@section('code', '404')
@section('title', 'This page does not exist')
@section('message', 'The link may be old, or the page may have moved.')
@section('action')
    <a href="{{ url('/') }}">Go to the studio</a>
@endsection
