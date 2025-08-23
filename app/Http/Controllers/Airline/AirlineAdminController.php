<?php

namespace App\Http\Controllers\Airline;

use App\Models\Airline;
use Illuminate\View\View;
use App\Policies\AirlinePolicy;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\AdminController;
use App\Http\Requests\Airline\Admin\StoreAirline;
use App\Http\Requests\Airline\Admin\UpdateAirline;
use App\Services\CachedDataService;
use Illuminate\Support\Facades\Storage;

class AirlineAdminController extends AdminController
{
    public function __construct()
    {
        $this->authorizeResource(AirlinePolicy::class, 'airline');
    }

    public function index(): View
    {
        $airlines = Airline::paginate(100);
        return view('airline.admin.overview', compact('airlines'));
    }

    public function create(): View
    {
        return view('airline.admin.form', ['airline' => new Airline()]);
    }

    public function store(StoreAirline $request): RedirectResponse
    {
        $data = $request->validated();
        
        // Handle logo upload
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $extension = $file->getClientOriginalExtension();
            $filename = strtoupper($data['icao']) . '.' . $extension;
            $logoPath = $file->storeAs('airlines', $filename, 'public');
            $data['logo_path'] = $logoPath;
        }
        
        // Remove logo field from data as it's not a database column
        unset($data['logo']);
        
        $airline = Airline::create($data);
        
        // Clear airlines cache when new airline is added
        $cachedDataService = new CachedDataService();
        $cachedDataService->clearAirlinesCache();
        
        flashMessage('success', __('Done'), __(':airline has been added!', ['airline' => "$airline->name [$airline->icao]"]));
        return to_route('admin.airlines.index');
    }

    public function show(Airline $airline): View
    {
        return view('airline.admin.show', compact('airline'));
    }

    public function edit(Airline $airline): View
    {
        return view('airline.admin.form', compact('airline'));
    }

    public function update(UpdateAirline $request, Airline $airline): RedirectResponse
    {
        $data = $request->validated();
        
        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($airline->logo_path) {
                Storage::disk('public')->delete($airline->logo_path);
            }
            $file = $request->file('logo');
            $extension = $file->getClientOriginalExtension();
            $filename = strtoupper($data['icao']) . '.' . $extension;
            $logoPath = $file->storeAs('airlines', $filename, 'public');
            $data['logo_path'] = $logoPath;
        }
        
        // Remove logo field from data as it's not a database column
        unset($data['logo']);
        
        $airline->update($data);
        
        // Clear airlines cache when airline is updated
        $cachedDataService = new CachedDataService();
        $cachedDataService->clearAirlinesCache();
        
        flashMessage('success', __('Done'), __(':airline has been updated!', ['airline' => "$airline->name [$airline->icao]"]));

        return to_route('admin.airlines.index');
    }

    public function destroy(Airline $airline): RedirectResponse
    {
        // Delete logo file if exists
        if ($airline->logo_path) {
            Storage::disk('public')->delete($airline->logo_path);
        }
        
        $airline->delete();
        
        // Clear airlines cache when airline is deleted
        $cachedDataService = new CachedDataService();
        $cachedDataService->clearAirlinesCache();
        
        flashMessage('success', __('Done'), __(':airline has been deleted!', ['airline' => "$airline->name [$airline->icao]"]));

        return redirect()->back();
    }
}
