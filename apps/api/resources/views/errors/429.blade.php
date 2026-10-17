@extends('errors.layout')

@section('code', '429')
@section('title', 'Too many requests')
@section('message', 'You have sent a lot of requests in a short time. Wait a minute, then try again.')
@section('action')
    <a href="{{ url('/') }}">Go to the studio</a>
@endsection
