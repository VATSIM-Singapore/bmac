@component('mail::message')
# Booking confirmed

Dear **{{ $booking->user->full_name }}**,

Thanks for booking a slot for {{ $booking->event->name }}. Here you can find your slot information:

@component('mail::table')
|  |  |
|-----------|---------------------------|
| Callsign: | **{{ $booking->formatted_callsign }}@if($booking->airline && $booking->airline->callsign && $booking->callsign)
@php
    $flightNumber = preg_replace('/^' . preg_quote($booking->airline->icao, '/') . '/', '', $booking->callsign);
@endphp ({{ $booking->airline->callsign }} {{ $flightNumber }})@endif** |
| Aircraft: | **{{ $booking->formatted_actype }}** |
@if($booking->getRawOriginal('selcal') != null)
| SELCAL: | **{{ $booking->formatted_selcal }}** |
@endif
@if($flight->dep)
| From: | **{{ $flight->airportDep->icao  }}** |
@endif
@if($flight->depBay)
| Departure Bay: | **{{ $flight->depBay->name }}** |
@endif
@if($flight->arr)
| To: | **{{ $flight->airportArr->icao }}** |
@endif
@if($flight->arrBay)
| Arrival Bay: | **{{ $flight->arrBay->name }}** |
@endif
@isset($flight->ctot)
| STD: | **{{ $flight->formattedCtot }}** |
@endisset
@isset($flight->eta)
| STA: | **{{ $flight->formattedEta }}** |
@endisset
@isset($flight->route)
| Full Route: | **{{ $flight->route }}** |
@endisset
@if($booking->event->is_oceanic_event)
@if($flight->getRawOriginal('oceanicFL') != null)
| Oceanic Entry Level: | **{{ $flight->formatted_oceanicfl }}** |
@endif
@if($flight->getRawOriginal('oceanicTrack') != null)
| NAT Track: | **{{ $flight->oceanicTrack }}** |
@endif
| NAT TMI: | **{{ $booking->event->startEvent->dayOfYear }}** |
@else
@if($flight->getRawOriginal('oceanicFL') != null)
| Cruise FL: | **{{ $flight->formatted_oceanicfl }}** |
@endif
@endif
@endcomponent

Visit the [Website]({{ url('/') }}) for further information.

We look forward to seeing you in the virtual skies.

@lang('Regards'),

**The SINvACC Team**
@endcomponent
