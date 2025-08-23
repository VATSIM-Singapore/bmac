@extends('layouts.app')

@section('content')
    <x-forms.alert />
    @include('layouts.alert')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ $event->name }} | {{ __('Import Confirmation') }}</div>

                <div class="card-body">
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> {{ __('Invalid Airlines Detected') }}</h5>
                        <p>{{ __('The following airlines in your import file were not recognized in the database:') }}</p>
                        <ul>
                            @foreach($invalidAirlines as $airline)
                                <li><strong>{{ $airline }}</strong></li>
                            @endforeach
                        </ul>
                        <p>{{ __('These airlines will be set to "No airline" during import.') }}</p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <form action="{{ route('admin.bookings.import.process', $event) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fas fa-check"></i> {{ __('Proceed with Import') }}
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <a href="{{ route('admin.bookings.importForm', $event) }}" class="btn btn-danger btn-block">
                                <i class="fas fa-times"></i> {{ __('Cancel Import') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
