@extends('layouts.app')

@section('content')
    <x-forms.alert />
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ $event->name }} | {{ $bulk ? __('Add Timeslots') : __('Add Slot') }}</div>

                <div class="card-body">
                    <x-form :action="route('admin.bookings.store',$event)" method="POST">
                        <input type="hidden" name="id" value="{{ $event->id }}">
                        <input type="hidden" name="bulk" value="{{ $bulk ? 1 : 0 }}">

                        <x-form-group :label="__('Event')">
                            {{ $event->name }} [{{ $event->startEvent->format('d-m-Y') }} |
                            {{ $event->startEvent->format('Hi') }}z -
                            {{ $event->endEvent->format('Hi') }}z]
                        </x-form-group>

                        <x-form-group name="is_editable" :label="__('Editable?')" inline>
                            <x-form-radio name="is_editable" value="0" :label="__('No')" required />
                            <x-form-radio name="is_editable" value="1" :label="__('Yes')" required />
                            @slot('help')
                                <small class="form-text text-muted">
                                    {{ __('Choose if you want the booking to be editable (Callsign and Aircraft Code only) by users. This is useful when using \'import only\', but want to add extra slots') }}
                                </small>
                            @endslot
                        </x-form-group>

                        @if (!$bulk)
                            <x-form-input name="callsign" :label="__('Callsign')" maxlength="7" />
                            <x-form-input name="acType" :label="__('Aircraft code')" minlength="3" maxlength="4" />
                            <x-form-select name="airline_id" :label="__('Airline (optional)')" :options="$airlines" :placeholder="__('Choose airline...')" />
                        @endif

                        <x-form-select name="dep" :label="__('Departure airport')" :options="$airports"
                            :placeholder="__('Choose...')" required :default="$event->dep" />

                        <x-form-select name="arr" :label="__('Arrival airport')" :options="$airports"
                            :placeholder="__('Choose...')" required :default="$event->dep" />

                        @if ($event->event_type_id == \App\Enums\EventType::REALFLIGHTOPS->value)
                            <x-form-select name="dep_bay" :label="__('Departure Bay (Optional)')" :options="['' => '-- No Bay --']"
                                id="dep_bay_select" />

                            <x-form-select name="arr_bay" :label="__('Arrival Bay (Optional)')" :options="['' => '-- No Bay --']"
                                id="arr_bay_select" />
                        @endif

                        @if ($bulk)
                            <x-form-group inline>
                                <x-form-input name="start" type="time"
                                    :label="'<i class=\'fa fa-clock\'></i> ' . __('Start')">
                                    @slot('append')
                                        z
                                    @endslot
                                </x-form-input>
                                <x-form-input name="end" type="time" :label="'<i class=\'fa fa-clock\'></i> ' . __('End')">
                                    @slot('append')
                                        z
                                    @endslot
                                </x-form-input>

                                <x-form-input name="separation" type="number" :label="__('Separation (in minutes)')" />
                            </x-form-group>
                        @else
                            <x-form-group inline>
                                <x-form-input name="ctot" type="time"
                                    :label="'<i class=\'fa fa-clock\'></i> ' . __('STD')">
                                    @slot('append')
                                        z
                                    @endslot
                                </x-form-input>
                                <x-form-input name="eta" type="time" :label="'<i class=\'fa fa-clock\'></i> ' . __('STA')">
                                    @slot('append')
                                        z
                                    @endslot
                                </x-form-input>

                            </x-form-group>

                            <x-form-textarea name="route" :label="__('Route')" />


                            <x-form-input name="oceanicFL"
                                :label="$event->is_oceanic_event ? __('Oceanic Entry Level') : __('Cruise FL')">
                                @slot('prepend')
                                    FL
                                @endslot
                            </x-form-input>

                        @endif

                        <x-form-textarea name="notes" :label="__('Notes')" />

                        <x-form-submit>
                            @if ($bulk)
                                <i class="fa fa-plus"></i> {{ __('Add timeslots') }}
                            @else
                                <i class="fa fa-plus"></i> {{ __('Add slot') }}
                            @endif
                        </x-form-submit>
                    </x-form>

                </div>
            </div>
        </div>


    </div>

    @if ($event->event_type_id == \App\Enums\EventType::REALFLIGHTOPS->value)
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const depAirportSelect = document.querySelector('select[name="dep"]');
                const arrAirportSelect = document.querySelector('select[name="arr"]');
                const depBaySelect = document.getElementById('dep_bay_select');
                const arrBaySelect = document.getElementById('arr_bay_select');

                function loadBays(airportId, baySelect) {
                    if (!airportId) {
                        baySelect.innerHTML = '<option value="">-- No Bay --</option>';
                        return;
                    }

                    // Get event ID from the hidden input
                    const eventId = document.querySelector('input[name="id"]').value;
                    const url = `/api/bays/by-airport?airport_id=${airportId}${eventId ? `&event_id=${eventId}` : ''}`;

                    fetch(url)
                        .then(response => response.json())
                        .then(bays => {
                            baySelect.innerHTML = '<option value="">-- No Bay --</option>';
                            bays.forEach(bay => {
                                const option = document.createElement('option');
                                option.value = bay.id;
                                option.textContent = bay.name;
                                baySelect.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Error loading bays:', error);
                            baySelect.innerHTML = '<option value="">Error loading bays</option>';
                        });
                }

                if (depAirportSelect && depBaySelect) {
                    depAirportSelect.addEventListener('change', function() {
                        loadBays(this.value, depBaySelect);
                    });

                    // Load bays if airport is already selected
                    if (depAirportSelect.value) {
                        loadBays(depAirportSelect.value, depBaySelect);
                    }
                }

                if (arrAirportSelect && arrBaySelect) {
                    arrAirportSelect.addEventListener('change', function() {
                        loadBays(this.value, arrBaySelect);
                    });

                    // Load bays if airport is already selected
                    if (arrAirportSelect.value) {
                        loadBays(arrAirportSelect.value, arrBaySelect);
                    }
                }
            });
        </script>
        @endpush
    @endif
@endsection
