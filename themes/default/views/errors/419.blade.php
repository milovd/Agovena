@extends('layouts.error')

@section('title', __('errors.419.title'))

@section('content')
    @include('errors.status', ['status' => 419])
@endsection
