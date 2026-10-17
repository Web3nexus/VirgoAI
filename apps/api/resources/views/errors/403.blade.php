@extends('errors.layout')

@section('code', '403')
@section('title', 'You do not have access to this page')
@section('message', 'Your account is not allowed to open it. If you think this is a mistake, contact your workspace owner.')
@section('action')
    <a href="{{ url('/') }}">Go to the studio</a>
@endsection
