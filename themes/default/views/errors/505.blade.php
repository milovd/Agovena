@extends('layouts.error')

@section('title', __('errors.505.title'))

@section('content')
    @include('errors.status', ['status' => 505])
@endsection
