@extends('layouts.error')

@section('title', __('errors.500.title'))

@section('content')
    @include('errors.status', ['status' => 500])
@endsection
