@extends('layouts.error')

@section('title', __('errors.405.title'))

@section('content')
    @include('errors.status', ['status' => 405])
@endsection
