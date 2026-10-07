<?php

namespace App\Http\Requests\Booking\Admin;

use App\Http\Requests\Request;

class StoreBooking extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'id' => 'exists:events,id|required',
            'bulk' => 'required|boolean',
            'is_editable' => 'required|boolean',
            'callsign' => 'nullable|alpha_num|between:4,7',
            'acType' => 'nullable|alpha_num|between:3,4',
            'airline_id' => 'nullable|exists:airlines,id',
            'ctot' => 'sometimes|nullable',
            'eta' => 'sometimes|nullable',
            'route' => 'sometimes|nullable',
            'dep' => 'nullable|exists:airports,id',
            'arr' => 'nullable|exists:airports,id',
            'start' => 'sometimes|date_format:H:i',
            'end' => 'sometimes|date_format:H:i',
            'separation' => 'sometimes|numeric|min:1',
            'oceanicFL' => 'sometimes|nullable|integer:3',
            'notes' => 'nullable',
            'dep_bay' => [
                'sometimes',
                'nullable',
                function ($attribute, $value, $fail) {
                    if (!empty($value) && !\App\Models\Bay::where('id', $value)->exists()) {
                        $fail('The selected ' . $attribute . ' is invalid.');
                    }
                },
            ],
            'arr_bay' => [
                'sometimes',
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
            'id' => __('Event id'),
            'bulk' => __('Bulk'),
            'is_editable' => __('Editable?'),
            'callsign' => __('Callsign'),
            'acType' => __('Aircraft code'),
            'airline_id' => __('Airline'),
            'ctot' => __('CTOT'),
            'eta' => __('ETA'),
            'route' => __('Route'),
            'dep' => __('Departure airport'),
            'arr' => __('Arrival airport'),
            'start' => __('Start'),
            'end' => __('End'),
            'separation' => __('Separation (in minutes)'),
            'oceanicFL' => __('Oceanic Entry Level') . ' / ' . __('Cruise FL'),
            'notes' => __('Notes'),
            'dep_bay' => __('Departure Bay'),
            'arr_bay' => __('Arrival Bay'),
        ];
    }

}
