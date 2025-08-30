@extends('layouts.app')

@section('content')
    <x-forms.alert />
    @include('layouts.alert')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ $booking->event->name }} | {{ __('Edit Booking') }}</div>

                <div class="card-body">
                    <x-form :action="route('admin.bookings.update', $booking)" method="PATCH">
                        @bind($booking)
                        <x-form-group name="is_editable" :label="__('Editable?')" inline>
                            <x-form-radio name="is_editable" value="0" :label="__('No')" required />
                            <x-form-radio name="is_editable" value="1" :label="__('Yes')" required />
                            @slot('help')
                                <small class="form-text text-muted">
                                    {{ __('Choose if you want the booking to be editable (Callsign and Aircraft Code only) by users. This is useful when using \'import only\', but want to add extra slots') }}
                                </small>
                            @endslot
                        </x-form-group>

                        <x-form-input name="callsign" :label="__('Callsign')" maxlength="7" />
                        <x-form-input name="acType" :label="__('Aircraft code')" minlength="3" maxlength="4" />
                        <x-form-select name="airline_id" :label="__('Airline (optional)')" :options="$airlines" :placeholder="__('Choose airline...')" :value="$booking->airline_id" />

                        @bind($flight)

                        <x-form-group inline>
                            <x-form-input name="ctot" :bind="false" value="{{ $flight->ctot?->format('H:i') }}" type="time" :label="'<i class=\'fa fa-clock\'></i> ' . __('STD')">
                                @slot('append')
                                    z
                                @endslot
                            </x-form-input>
                            <x-form-input name="eta" :bind="false" value="{{ $flight->eta?->format('H:i') }}" type="time" :label="'<i class=\'fa fa-clock\'></i> ' . __('STA')">
                                @slot('append')
                                    z
                                @endslot
                            </x-form-input>
                        </x-form-group>

                        <x-form-select name="dep" :label="__('Departure airport')" :options="$airports"
                            :placeholder="__('Choose...')" required />

                        <x-form-select name="arr" :label="__('Arrival airport')" :options="$airports"
                            :placeholder="__('Choose...')" required />

                        @if ($booking->event->event_type_id == \App\Enums\EventType::REALFLIGHTOPS->value)
                            <x-form-select name="dep_bay" :label="__('Departure Bay (Optional)')" :options="$depBays ?? ['' => '-- No Bay --']"
                                id="dep_bay_select" :value="$flight->dep_bay" :bind="false" />

                            <x-form-select name="arr_bay" :label="__('Arrival Bay (Optional)')" :options="$arrBays ?? ['' => '-- No Bay --']"
                                id="arr_bay_select" :value="$flight->arr_bay" :bind="false" />
                        @endif

                        <x-form-group :label="__('PIC')">
                            {{ $booking->user ? $booking->user->pic : '-' }}
                        </x-form-group>

                        <x-form-textarea name="route" :label="__('Route')" />

                        @if ($booking->event->is_oceanic_event)
                            <x-form-input name="oceanicTrack" :label="__('Track')" maxlength="2" />
                        @endif

                        <x-form-input name="oceanicFL"
                            :label="$booking->event->is_oceanic_event ? __('Oceanic Entry Level') : __('Cruise FL')">
                            @slot('prepend')
                                FL
                            @endslot
                        </x-form-input>

                        <x-form-textarea name="notes" :label="__('Notes')" />
                        @endbind

                        @if ($booking->user_id)
                            <x-form-textarea name="message" :label="__('Message')" />

                            <x-form-group>
                                <x-form-checkbox name="notify_user" checked :label="__('Notify user?')" />
                            </x-form-group>
                        @endif

                        <x-form-submit>
                            <i class="fas fa-check"></i> {{ __('Update') }}
                        </x-form-submit>
                        @endbind
                    </x-form>
                </div>
            </div>
        </div>
    </div>

    @if ($booking->event->event_type_id == \App\Enums\EventType::REALFLIGHTOPS->value)
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const depAirportSelect = document.querySelector('select[name="dep"]');
                const arrAirportSelect = document.querySelector('select[name="arr"]');
                const depBaySelect = document.getElementById('dep_bay_select');
                const arrBaySelect = document.getElementById('arr_bay_select');

                function loadBays(airportId, baySelect, selectedValue = null) {
                    if (!airportId) {
                        baySelect.innerHTML = '<option value="">-- No Bay --</option>';
                        return;
                    }

                    fetch(`/api/bays/by-airport?airport_id=${airportId}`)
                        .then(response => response.json())
                        .then(bays => {
                            // Start with "No Bay" option
                            baySelect.innerHTML = '<option value="">-- No Bay --</option>';
                            
                            // Add bay options
                            bays.forEach(bay => {
                                const option = document.createElement('option');
                                option.value = bay.id;
                                option.textContent = bay.name;
                                if (selectedValue && bay.id == selectedValue) {
                                    option.selected = true;
                                }
                                baySelect.appendChild(option);
                            });
                            
                            // Select "No Bay" if no bay is currently selected
                            if (!selectedValue || selectedValue === '') {
                                baySelect.value = '';
                            }
                        })
                        .catch(error => {
                            console.error('Error loading bays:', error);
                            baySelect.innerHTML = '<option value="">Error loading bays</option>';
                        });
                }

                if (depAirportSelect && depBaySelect) {
                    const currentDepBay = '{{ $flight->dep_bay }}';
                    
                    depAirportSelect.addEventListener('change', function() {
                        loadBays(this.value, depBaySelect);
                    });

                    // Load bays if airport is already selected
                    if (depAirportSelect.value) {
                        loadBays(depAirportSelect.value, depBaySelect, currentDepBay);
                    }
                }

                if (arrAirportSelect && arrBaySelect) {
                    const currentArrBay = '{{ $flight->arr_bay }}';
                    
                    arrAirportSelect.addEventListener('change', function() {
                        loadBays(this.value, arrBaySelect);
                    });

                    // Load bays if airport is already selected
                    if (arrAirportSelect.value) {
                        loadBays(arrAirportSelect.value, arrBaySelect, currentArrBay);
                    }
                }
            });
        </script>
        @endpush
    @endif

@endsection
