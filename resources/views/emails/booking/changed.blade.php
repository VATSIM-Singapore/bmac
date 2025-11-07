@component('mail::message')
# Booking change

Dear **{{ $booking->user->full_name }}**,

Your booking for **{{ $booking->event->name }}** has been amended.

Questions? Ask us on the SINvACC Discord Server, or email us <a href="mailto:realops@sinvacc.org">realops@sinvacc.org</a>

Please review the changes below:

@component('mail::table')
|  |  |  |
|-----|-----|-----|
@foreach($changes as $change)
@switch($change['name'])
@case('callsign')
| Callsign: | **{{ $change['new'] }}** | (was {{ $change['old'] }}) |
@break
@case('acType')
| Aircraft code: | **{{ $change['new'] }}** | (was {{ $change['old'] }}) |
@break
@case('dep')
| ADEP: | **{{ \App\Models\Airport::find($change['new'])->icao }}** | (was {{ \App\Models\Airport::find($change['old'])->first()->icao }}) |
@break
@case('arr')
| ADES: | **{{ \App\Models\Airport::find($change['new'])->icao }}** | (was {{ \App\Models\Airport::find($change['old'])->first()->icao }}) |
@break
@case('ctot')
| STD: | **{{ \Carbon\Carbon::parse($change['new'])->format('Hi').'z' }}** | (was {{ \Carbon\Carbon::parse($change['old'])->format('Hi').'z' }}) |
@break
@case('eta')
| STA: | **{{ \Carbon\Carbon::parse($change['new'])->format('Hi').'z' }}** | (was {{ \Carbon\Carbon::parse($change['old'])->format('Hi').'z' }}) |
@break
@case('route')
| Route: | **{{ $change['new'] }}** | (was {{ $change['old'] }}) |
@break
@case('oceanicTrack')
| Track: | **{{ $change['new'] }}** | (was {{ $change['old'] }}) |
@break
@case('oceanicFL')
| {{ $booking->event->is_oceanic_event ? __('Oceanic Entry FL') : __('Cruise FL') }}: | **FL{{ $change['new'] }}** | (was FL{{ $change['old'] }}) |
@break
@case('notes')
| Notes: | **{{ $change['new'] }}** | (was {{ $change['old'] }}) |
@break
@case('message')
| A message has been left: | {{ $change['new'] }} |
@break
@endswitch
@endforeach
@endcomponent

@lang('Regards'),

**{{ config('mail.from.name', config('app.name')) }}**
@endcomponent
