@extends('errors.layout')

@section('code', '401')
@section('title', 'Sign in to continue')
@section('message', 'You need to be signed in to see this page. Your session may have ended.')
@section('action')
    <a href="{{ url('/') }}">Go to the studio</a>
@endsection
