@extends('layouts.app')

@section('content')
    <x-forms.alert />
    <div class="row justify-content-center mb-3">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ $airline->id ? __('Edit') : __('Add new') }} {{ __('Airline') }}</div>

                <div class="card-body">
                    <x-form
                        :action="$airline->id ? route('admin.airlines.update', $airline) : route('admin.airlines.store')"
                        :method="$airline->id ? 'PATCH' : 'POST'"
                        enctype="multipart/form-data">

                        @bind($airline)
                        <x-form-input name="icao" :label="__('ICAO')" required maxlength="3" />
                        <x-form-input name="name" :label="__('Name')" required />
                        <x-form-input name="callsign" :label="__('Callsign')" />

                        <div class="form-group">
                            <label for="logo">{{ __('Logo') }}</label>
                            <input type="file" class="form-control-file" id="logo" name="logo" accept="image/*">
                            @if($airline->logo_url)
                                <div class="mt-2">
                                    <p class="text-muted">Current logo:</p>
                                    <img src="{{ $airline->logo_url }}" alt="{{ $airline->name }} logo" style="max-height: 100px; max-width: 200px;" class="img-thumbnail">
                                </div>
                            @endif
                            <small class="form-text text-muted">Upload an image file (JPEG, PNG, JPG, GIF) up to 2MB.</small>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                @if ($airline->id)
                                    <i class="fa fa-check"></i> {{ __('Edit') }}
                                @else
                                    <i class="fa fa-plus"></i> {{ __('Add') }}
                                @endif
                            </button>
                            <a href="{{ route('admin.airlines.index') }}" class="btn btn-secondary ml-2">
                                <i class="fa fa-times"></i> {{ __('Cancel') }}
                            </a>
                        </div>
                        @endbind
                    </x-form>
                </div>
            </div>
        </div>
    </div>
@endsection
