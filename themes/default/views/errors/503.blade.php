@extends('layouts.error')

@section('title', __('errors.503.title'))

@section('content')
    @include('errors.status', ['status' => 503])
@endsection
