@extends('layouts.app')

@section('content')
    <h3>{{ $airline->name }} [{{ $airline->icao }}]</h3>
    <hr>
    @include('layouts.alert')
    
    <div class="row">
        <div class="col-md-6">
            <table class="table">
                <tr>
                    <th>ICAO Code:</th>
                    <td>{{ $airline->icao }}</td>
                </tr>
                <tr>
                    <th>Name:</th>
                    <td>{{ $airline->name }}</td>
                </tr>
                <tr>
                    <th>Created:</th>
                    <td>{{ $airline->created_at->format('Y-m-d H:i:s') }}</td>
                </tr>
                <tr>
                    <th>Updated:</th>
                    <td>{{ $airline->updated_at->format('Y-m-d H:i:s') }}</td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            @if($airline->logo_url)
                <h5>Logo</h5>
                <img src="{{ $airline->logo_url }}" alt="{{ $airline->name }} logo" class="img-fluid img-thumbnail" style="max-height: 200px;">
            @else
                <p class="text-muted">No logo uploaded</p>
            @endif
        </div>
    </div>
    
    <div class="mt-3">
        <a href="{{ route('admin.airlines.edit', $airline) }}" class="btn btn-primary">
            <i class="fa fa-edit"></i> Edit Airline
        </a>
        <a href="{{ route('admin.airlines.index') }}" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Back to Airlines
        </a>
    </div>
@endsection
