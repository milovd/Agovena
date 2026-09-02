@extends('layouts.error')

@section('title', __('errors.404.title'))

@section('content')
    @include('errors.status', ['status' => 404])
@endsection
