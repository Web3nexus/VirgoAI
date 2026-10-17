@extends('errors.layout')

@section('code', '503')
@section('title', 'The studio is taking a short break')
@section('message', 'We are doing maintenance. Runs already started are kept. Try again shortly.')
@section('action')
    <a href="{{ url('/') }}">Go to the studio</a>
@endsection
