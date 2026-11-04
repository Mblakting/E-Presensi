@extends('competition::layout')

@section('title', $title)
@section('breadcrumb', 'Status '.$statusCode)

@section('content')
    <div class="card">
        <div class="card-body text-center py-5">
            <p class="text-secondary mb-2">HTTP {{ $statusCode }}</p>
            <h1 class="page-title competition-page-title mb-3">{{ $title }}</h1>
            <p class="text-secondary">{{ $message }}</p>
            <a class="btn btn-primary me-2" href="{{ url('/competition') }}">Pusat fitur lomba</a>
            <a class="btn btn-outline-secondary" href="{{ url('/competition/guide') }}">Panduan juri</a>
        </div>
    </div>
@endsection
