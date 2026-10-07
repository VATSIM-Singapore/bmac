@component('mail::message')
# {{ $subject }}

Dear **{{ $full_name }}**,

{!! $content !!}

@lang('Regards'),

**The SINvACC Team**
@endcomponent
