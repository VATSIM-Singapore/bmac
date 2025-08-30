<?php

namespace App\Http\Requests\Booking\Admin;

use App\Http\Requests\Request;

class UpdateBooking extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'is_editable' => 'required|boolean',
            'callsign' => 'nullable|alpha_num|max:7',
            'acType' => 'nullable|alpha_num|between:3,4',
            'airline_id' => 'nullable|exists:airlines,id',
            'ctot' => 'present|nullable|date_format:H:i',
            'eta' => 'present|nullable|date_format:H:i',
            'dep' => 'nullable|exists:airports,id',
            'arr' => 'nullable|exists:airports,id',
            'route' => 'nullable',
            'oceanicFL' => 'nullable|int:3',
            'oceanicTrack' => 'nullable|alpha|min:1|max:2',
            'notes' => 'nullable',
            'message' => 'nullable',
            'notify_user' => 'nullable',
            'dep_bay' => [
                'present',
                'nullable',
                function ($attribute, $value, $fail) {
                    if (!empty($value) && !\App\Models\Bay::where('id', $value)->exists()) {
                        $fail('The selected ' . $attribute . ' is invalid.');
                    }
                },
            ],
            'arr_bay' => [
                'present',
                'nullable',
                function ($attribute, $value, $fail) {
                    if (!empty($value) && !\App\Models\Bay::where('id', $value)->exists()) {
                        $fail('The selected ' . $attribute . ' is invalid.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'is_editable' => __('Editable?'),
            'callsign' => __('Callsign'),
            'acType' => __('Aircraft code'),
            'airline_id' => __('Airline'),
            'ctot' => __('CTOT'),
            'eta' => __('ETA'),
            'dep' => __('Departure airport'),
            'arr' => __('Arrival airport'),
            'route' => __('Route'),
            'oceanicFL' => __('Oceanic Entry Level') . ' / ' . __('Cruise FL'),
            'notes' => __('Notes'),
            'message' => __('Message'),
            'notify_user' => __('Notify user'),
            'dep_bay' => __('Departure Bay'),
            'arr_bay' => __('Arrival Bay'),
        ];
    }

}
