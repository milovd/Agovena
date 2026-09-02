@extends('layouts.error')

@section('title', __('errors.403.title'))

@section('content')
    @include('errors.status', ['status' => 403])
@endsection
