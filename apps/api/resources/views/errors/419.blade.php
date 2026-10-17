@extends('errors.layout')

@section('code', '419')
@section('title', 'Your session has expired')
@section('message', 'For your safety we signed you out. Sign in again, and your work will still be there.')
@section('action')
    <a href="{{ url('/') }}">Go to the studio</a>
@endsection
