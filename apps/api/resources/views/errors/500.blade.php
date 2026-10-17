@extends('errors.layout')

@section('code', '500')
@section('title', 'Something went wrong on our side')
@section('message', 'We could not finish that. Your work is saved. Try again in a moment, and tell us if it keeps happening.')
@section('action')
    <a href="{{ url('/') }}">Go to the studio</a>
@endsection
