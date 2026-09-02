@extends('layouts.error')

@section('title', __('errors.429.title'))

@section('content')
    @include('errors.status', ['status' => 429])
@endsection
