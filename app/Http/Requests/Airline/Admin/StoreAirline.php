<?php

namespace App\Http\Requests\Airline\Admin;

use App\Http\Requests\Request;

class StoreAirline extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'icao' => 'required|string|unique:airlines|size:3',
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
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
            'icao' => __('ICAO'),
            'name' => __('Name'),
            'logo' => __('Logo'),
        ];
    }
}
