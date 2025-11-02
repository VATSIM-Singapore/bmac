@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <h3><i class="fa fa-building mr-2"></i>Bay Assignment Management - {{ $event->name }}</h3>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#bayBlockingModal">
                    <i class="fa fa-ban mr-2"></i>Bay Blocking
                </button>
            </div>
        </div>
    </div>

    <!-- Warning Banner -->
    <div id="warningBanner" class="alert alert-warning alert-dismissible fade show" role="alert" style="display: none;">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <div class="d-flex align-items-start">
            <div class="flex-grow-1">
                <div id="warningContent">
                    <!-- Warning content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3 gate-filter-card">
        <div class="card-body">
            <div class="row align-items-start">
                <div class="col-md-3">
                    <label for="gateFilter" class="form-label mb-0">
                        <i class="fa fa-filter mr-1"></i>Filter by Gate:
                    </label>
                    <small class="text-muted d-block mt-1">
                        <i class="fa fa-info-circle mr-1"></i>
                        Type gate name and press Enter to add filter. Use partial names (e.g., "A" matches A1, A2, etc.)
                    </small>
                </div>
                <div class="col-md-3">
                    <div class="gate-filter-wrapper">
                        <input type="text" id="gateFilter" class="form-control" placeholder="Enter gate name (e.g., A, C1, etc.)" autocomplete="off">
                        <div class="gate-filter-chips-container">
                            <div class="gate-filter-chips" id="gateFilterChips"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary clear-all-chips" id="clearAllChips" style="display: none;">
                                <i class="fa fa-times mr-1"></i>Clear All
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="aircraftFilter" class="form-label mb-0">
                        <i class="fa fa-plane mr-1"></i>Highlight Aircraft Type:
                    </label>
                    <small class="text-muted d-block mt-1">
                        <i class="fa fa-info-circle mr-1"></i>
                        Dims flights that don't match the specified aircraft type
                    </small>
                </div>
                <div class="col-md-3">
                    <input type="text" id="aircraftFilter" class="form-control" placeholder="Enter aircraft type (e.g., B738, A320, etc.)">
                </div>
            </div>
        </div>
    </div>

    <!-- Bay Assignment Table -->
    <div class="card mb-3">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Gate Schedule - {{ $event->airportDep->name ?? 'Unknown Airport' }}</h5>
                    <small class="text-muted">
                        Displaying: {{ $timeRange['start']->format('H:i') }} - {{ $timeRange['end']->format('H:i') }}
                    </small>
                </div>
                <div class="text-right">
                    <small class="text-muted d-block mb-1"><i class="fa fa-info-circle mr-1"></i>Legend:</small>
                    <div>
                        <span class="badge badge-warning mr-1">Arrival</span>
                        <span class="badge badge-info mr-1">Departure</span>
                        <span class="badge badge-warning adhoc-striped mr-1">Ad Hoc Arrival</span>
                        <span class="badge badge-info adhoc-striped">Ad Hoc Departure</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-container">
                <table class="table table-bordered table-sm gate-table mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th class="bg-dark text-white sticky-gate-header">Gate</th>
                            @foreach($timeSlots as $timeSlot)
                                <th class="sticky-time-header">{{ $timeSlot->format('H:i') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bays as $bay)
                            @php
                                // Use pre-computed data from controller
                                $bayData = $bayUsage[$bay->id] ?? [];

                                // Calculate max rows needed for this bay
                                $maxOverlaps = 1;
                                foreach($bayData as $timeKey => $assignments) {
                                    if (is_array($assignments)) {
                                        $maxOverlaps = max($maxOverlaps, count($assignments));
                                    }
                                }
                            @endphp

                            {{-- Create sub-rows for this bay --}}
                            @for($subRow = 0; $subRow < $maxOverlaps; $subRow++)
                                <tr class="{{ $subRow > 0 ? 'bay-sub-row' : 'bay-main-row' }} {{ in_array($bay->id, $blockedBayIds) ? 'bay-blocked-row' : '' }}" data-gate="{{ $bay->name }}" data-bay-id="{{ $bay->id }}">
                                    @if($subRow === 0)
                                        <td class="bg-dark text-white font-weight-bold sticky-gate-cell {{ in_array($bay->id, $blockedBayIds) ? 'bay-blocked' : '' }}"
                                            rowspan="{{ $maxOverlaps }}">
                                            <div class="d-flex align-items-center">
                                                <span>{{ $bay->name }}</span>
                                                @if(in_array($bay->id, $blockedBayIds))
                                                    <i class="fa fa-ban text-white ml-2 bay-blocked-icon"
                                                       data-toggle="tooltip"
                                                       data-placement="right"
                                                       title="This bay is blocked."></i>
                                                @endif
                                                @if($maxOverlaps > 1)
                                                    <i class="fa fa-exclamation-triangle text-warning ml-2 gate-overlap-warning"
                                                       data-toggle="tooltip"
                                                       data-placement="right"
                                                       title="Gate has {{ $maxOverlaps }} overlapping assignments. Multiple flights are scheduled at the same time."></i>
                                                @endif
                                            </div>
                                        </td>
                                    @endif

                                    @php $skipCells = []; @endphp

                                    @foreach($timeSlots as $slotIndex => $timeSlot)
                                        @if(in_array($slotIndex, $skipCells))
                                            {{-- Skip this cell due to colspan --}}
                                            @continue
                                        @endif

                                        @php
                                            $timeKey = $timeSlot->format('Y-m-d H:i');
                                            $assignments = $bayData[$timeKey] ?? [];

                                            // Ensure assignments is an array and get the assignment for this sub-row
                                            $assignment = null;
                                            if (is_array($assignments) && isset($assignments[$subRow]) && $assignments[$subRow] !== null) {
                                                $assignment = $assignments[$subRow];
                                            }
                                        @endphp

                                        @if($assignment)
                                            @php
                                                $cssClass = 'bg-warning text-white'; // Default for arrival
                                                if ($assignment['type'] === 'departure') {
                                                    $cssClass = 'bg-info text-white';
                                                } elseif ($assignment['type'] === 'adhoc') {
                                                    // Use striped pattern for ad hoc flights
                                                    if ($assignment['flight_type'] === 'departure') {
                                                        $cssClass = 'bg-info text-white adhoc-striped';
                                                    } else {
                                                        $cssClass = 'bg-warning text-white adhoc-striped';
                                                    }
                                                }
                                                $colspan = $assignment['colspan'] ?? 1;

                                                // Only add cells to skip if colspan > 1
                                                if ($colspan > 1) {
                                                    for($i = 1; $i < $colspan; $i++) {
                                                        $skipCells[] = $slotIndex + $i;
                                                    }
                                                }
                                            @endphp

                                            <td class="time-slot {{ $cssClass }} flight-assignment-cell"
                                                @if($colspan > 1)
                                                    colspan="{{ $colspan }}"
                                                @endif
                                                data-flight-id="{{ $assignment['flight']->id }}"
                                                data-assignment-type="{{ $assignment['type'] }}"
                                                data-aircraft-type="{{ $assignment['aircraft_type'] ?? 'Unknown' }}"
                                                style="cursor: pointer;"
                                                title="Click to view/edit flight details"
                                                tabindex="0"
                                                role="button"
                                                aria-label="View flight details for {{ $assignment['callsign'] }}">

                                                <div class="text-center flight-details">
                                                    <div class="flight-callsign">{{ $assignment['callsign'] }}</div>
                                                    <div class="flight-airport-aircraft">
                                                        {{ $assignment['relevant_airport_icao'] ?? $assignment['relevant_airport'] }}
                                                        @if($assignment['aircraft_type'] && $assignment['aircraft_type'] !== 'Unknown')
                                                            ({{ $assignment['aircraft_type'] }})
                                                        @endif
                                                    </div>
                                                    <div class="flight-time">{{ $assignment['time_from'] }}-{{ $assignment['time_to'] }}</div>
                                                </div>
                                            </td>
                                        @else
                                            <td class="time-slot table-light"></td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endfor
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Flight Details Modal -->
<div class="modal fade" id="flightDetailsModal" tabindex="-1" role="dialog" aria-labelledby="flightDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="flightDetailsModalLabel">Flight Details</h5>
                <button type="button" class="close" id="modalCloseX" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="flightDetailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="closeModalBtn">Close</button>
                <button type="button" class="btn btn-primary" id="saveFlightDetails" style="display: none;">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Bay Blocking Modal -->
<div class="modal fade" id="bayBlockingModal" tabindex="-1" role="dialog" aria-labelledby="bayBlockingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bayBlockingModalLabel">
                    <i class="fa fa-ban mr-2"></i>Bay Blocking Management
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fa fa-plus mr-2"></i>Add Bay Blocking</h6>
                        <form id="addBayBlockingForm">
                            @csrf
                            <div class="form-group">
                                <label for="baySelect">Select Bay to Block:</label>
                                <select class="form-control" id="baySelect" name="bay_id" required>
                                    <option value="">Choose a bay...</option>
                                    @foreach($bays as $bay)
                                        @if(!in_array($bay->id, $blockedBayIds))
                                            <option value="{{ $bay->id }}">{{ $bay->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="fa fa-ban mr-1"></i>Block Bay
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fa fa-list mr-2"></i>Currently Blocked Bays</h6>
                        <div id="blockedBaysList">
                            <div class="text-center text-muted">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <div class="mt-2">Loading blocked bays...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Ad Hoc Flight Modal -->
<div class="modal fade" id="adhocFlightModal" tabindex="-1" role="dialog" aria-labelledby="adhocFlightModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adhocFlightModalLabel">
                    <i class="fa fa-plus mr-2"></i>Add Ad Hoc Flight
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="adhocFlightContent">
                <!-- Choice Selection -->
                <div id="adhocChoiceSection" class="text-center">
                    <h6 class="mb-3">Select Flight Type:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-info btn-lg btn-block" id="selectDeparture">
                                <i class="fa fa-plane-departure mr-2"></i>Departure
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-warning btn-lg btn-block" id="selectArrival">
                                <i class="fa fa-plane-arrival mr-2"></i>Arrival
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Flight Form -->
                <div id="adhocFlightForm" style="display: none;">
                    <form id="adhocFlightFormData">
                        @csrf
                        <input type="hidden" id="selectedTimeSlot" name="time_slot">
                        <input type="hidden" id="flightType" name="flight_type">
                        <input type="hidden" id="eventId" name="event_id" value="{{ $event->id }}">

                        <!-- Flight Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3"><i class="fa fa-plane mr-2"></i>Flight Information</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="callsign" class="font-weight-bold">Callsign <span class="text-danger">*</span>:</label>
                                    <input type="text" class="form-control" id="callsign" name="callsign" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="acType" class="font-weight-bold">Aircraft Type <span class="text-danger">*</span>:</label>
                                    <input type="text" class="form-control" id="acType" name="acType" required>
                                </div>
                            </div>
                        </div>

                        <!-- Airport Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-success mb-3"><i class="fa fa-map-marker-alt mr-2"></i>Airport Information</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="dep" class="font-weight-bold">Departure Airport:</label>
                                    <select class="form-control" id="dep" name="dep">
                                        <option value="">-- Select Airport --</option>
                                        @foreach(\App\Models\Airport::orderBy('name')->get() as $airport)
                                            <option value="{{ $airport->id }}">{{ $airport->name }} ({{ $airport->icao }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="arr" class="font-weight-bold">Arrival Airport:</label>
                                    <select class="form-control" id="arr" name="arr">
                                        <option value="">-- Select Airport --</option>
                                        @foreach(\App\Models\Airport::orderBy('name')->get() as $airport)
                                            <option value="{{ $airport->id }}">{{ $airport->name }} ({{ $airport->icao }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Time Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-info mb-3"><i class="fa fa-clock mr-2"></i>Time Information</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="std" class="font-weight-bold">STD (Scheduled Time of Departure):</label>
                                    <input type="datetime-local" class="form-control" id="std" name="std">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sta" class="font-weight-bold">STA (Scheduled Time of Arrival):</label>
                                    <input type="datetime-local" class="form-control" id="sta" name="sta">
                                </div>
                            </div>
                        </div>

                        <!-- Bay Assignment Information -->
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-warning mb-3">
                                    <i class="fa fa-building mr-2"></i>
                                    <span id="assignmentTypeLabel">Bay Assignment</span>
                                </h6>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="font-weight-bold">Assigned Gate:</label>
                                    <select class="form-control" id="assignedGate" name="bay_id">
                                        <option value="">-- Select Gate --</option>
                                        @foreach($bays as $bay)
                                            <option value="{{ $bay->id }}">{{ $bay->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Booked From:</label>
                                    <input type="datetime-local" class="form-control" id="bayAssignedFrom" name="bay_assigned_from">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Booked To:</label>
                                    <input type="datetime-local" class="form-control" id="bayAssignedTo" name="bay_assigned_to">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAdhocFlight" style="display: none;">
                    <i class="fa fa-save mr-1"></i>Save Ad Hoc Flight
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .table-container {
        position: relative;
        max-height: 60vh;
        overflow: auto;
        border: 1px solid #dee2e6;
    }

    .gate-table th:first-child,
    .gate-table td:first-child {
        position: sticky;
        left: 0;
        z-index: 10;
        min-width: 80px;
    }

    /* Ensure all table rows have proper z-index for sticky positioning */
    .gate-table tbody tr {
        position: relative;
    }

    /* Sub-rows (without bay name cell) need lower z-index to stay behind sticky column */
    .bay-sub-row td {
        z-index: 1 !important;
        position: relative;
    }

    /* Main rows (with bay name cell) work normally */
    .bay-main-row td:not(:first-child) {
        z-index: 1;
        position: relative;
    }

    /* Ensure the sticky gate cell always stays on top */
    .sticky-gate-cell {
        z-index: 12 !important;
        position: sticky;
        left: 0;
    }

    .gate-table th {
        position: sticky;
        top: 0;
        z-index: 5;
        white-space: nowrap;
        text-align: center;
        font-size: 0.75rem;
        padding: 0.4rem 0.2rem;
    }

    .gate-table th:first-child {
        z-index: 13;
    }

    .gate-table td {
        text-align: center;
        vertical-align: middle;
        font-size: 0.7rem;
        padding: 0.2rem;
        min-width: 45px;
        height: 50px;
        position: relative;
        border: 1px solid #dee2e6;
    }

    .sticky-gate-header,
    .sticky-gate-cell {
        background-color: #343a40 !important;
        color: white !important;
    }

    .sticky-time-header {
        background-color: #343a40 !important;
        color: white !important;
        writing-mode: vertical-rl;
        text-orientation: mixed;
        min-width: 45px;
    }

    .time-slot {
        cursor: pointer;
        transition: opacity 0.2s;
    }

    .time-slot:hover {
        opacity: 0.8;
    }

    .flight-details {
        padding: 2px;
        line-height: 1.1;
    }

    .flight-callsign {
        font-weight: bold;
        font-size: 0.7rem;
        margin-bottom: 1px;
    }

    .flight-airport-aircraft {
        font-size: 0.6rem;
        margin-bottom: 1px;
    }

    .flight-time {
        font-size: 0.5rem;
        opacity: 0.9;
    }

    @media (max-width: 768px) {
        .gate-table {
            font-size: 0.65rem;
        }

        .gate-table th,
        .gate-table td {
            padding: 0.15rem;
            min-width: 35px;
        }

        .flight-callsign {
            font-size: 0.6rem;
        }

        .flight-airport-aircraft {
            font-size: 0.5rem;
        }

        .flight-time {
            font-size: 0.4rem;
        }
    }

    .flight-assignment-cell:hover {
        opacity: 0.8;
        transform: scale(1.02);
        transition: all 0.2s;
    }

    .flight-assignment-cell:focus {
        outline: 2px solid #007bff;
        outline-offset: 2px;
    }

    .flight-assignment-cell {
        tabindex: 0;
    }

    .modal-body .form-group {
        margin-bottom: 1rem;
    }

    /* Filter Styling */
    #gateFilter, #aircraftFilter {
        min-width: 150px;
    }

    .gate-filter-card {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
    }

    .gate-filter-card .card-body {
        padding: 1rem;
    }

    .form-label {
        font-weight: 600;
        color: #495057;
    }

    /* Gate overlap warning icon styling */
    .gate-overlap-warning {
        font-size: 0.9rem;
        cursor: help;
        transition: transform 0.2s ease;
    }

    .gate-overlap-warning:hover {
        transform: scale(1.1);
    }

    /* Ensure proper spacing in gate cell */
    .sticky-gate-cell .d-flex {
        min-height: 100%;
        align-items: center;
    }

    /* Gate filter chips styling */
    .gate-filter-wrapper {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .gate-filter-chips-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        min-height: 32px;
        padding: 4px 0;
    }

    .gate-filter-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    .gate-filter-chip {
        display: inline-flex;
        align-items: center;
        background-color: #007bff;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 500;
        margin: 1px;
    }

    .gate-filter-chip .remove-chip {
        margin-left: 6px;
        cursor: pointer;
        font-weight: bold;
        opacity: 0.8;
        transition: opacity 0.2s;
    }

    .gate-filter-chip .remove-chip:hover {
        opacity: 1;
    }

    .clear-all-chips {
        padding: 2px 8px;
        font-size: 0.7rem;
        border-radius: 3px;
        margin-left: auto;
    }

    .clear-all-chips:hover {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
    }

    /* Bay blocking styling */
    .bay-blocked {
        background-color: #dc3545 !important;
    }

    .bay-blocked-row {
        background-color: #f8d7da;
    }

    .bay-blocked-row td {
        background-color: #f8d7da;
    }

    .bay-blocked-row .flight-assignment-cell {
        position: relative;
        z-index: 10;
    }

    .bay-blocked-icon {
        font-size: 0.9rem;
        cursor: help;
        transition: transform 0.2s ease;
    }

    /* Visual feedback for scroll position restoration */
    .position-restored {
        box-shadow: 0 0 10px rgba(0, 123, 255, 0.3);
        transition: box-shadow 0.3s ease;
    }

    /* Diagonal striped pattern for ad hoc flights */
    .adhoc-striped {
        background-image: repeating-linear-gradient(
            45deg,
            transparent,
            transparent 8px,
            rgba(255, 255, 255, 0.15) 8px,
            rgba(255, 255, 255, 0.15) 16px
        ) !important;
    }

    /* Aircraft filter dimming effect */
    .aircraft-filter-dimmed {
        opacity: 0.3 !important;
        filter: grayscale(0.8) !important;
        transition: opacity 0.3s ease, filter 0.3s ease;
    }
</style>
@endpush

@push('scripts')
<script>
// Safe modal close function to prevent ARIA issues (global scope)
function closeModalSafely() {
    // Remove focus from any focused element
    if (document.activeElement && document.activeElement.blur) {
        document.activeElement.blur();
    }
    // Force focus to body
    document.body.focus();
    // Restore original modal footer for regular flights
    restoreOriginalModalFooter();
    // Close modal
    $('#flightDetailsModal').modal('hide');
}

// Restore original modal footer for regular flights
function restoreOriginalModalFooter() {
    const originalFooter = `
        <button type="button" class="btn btn-secondary" id="closeModalBtn">Close</button>
        <button type="button" class="btn btn-primary" id="saveFlightDetails" style="display: none;">Save Changes</button>
    `;
    $('#flightDetailsModal .modal-footer').html(originalFooter);
    // Re-attach event handlers after footer is restored
    reattachModalFooterHandlers();
}

// Close modal function (global scope)
function closeModal() {
    // Remove focus from any buttons and inputs before closing
    $('#flightDetailsModal').find('button, input, select, textarea').blur();

    // Remove any active focus from the modal
    $('#flightDetailsModal').find(':focus').blur();

    // Close the modal properly
    $('#flightDetailsModal').modal('hide');

    // Ensure aria-hidden is set properly during the closing process
    setTimeout(function() {
        $('#flightDetailsModal').attr('aria-hidden', 'true');
    }, 50);
}

// Save flight details function (global scope)
function saveFlightDetails(forceSave = false) {
    const form = $('#flightDetailsForm');
    let formData = form.serialize();

    // Add force_save parameter if this is a forced save
    if (forceSave) {
        formData += '&force_save=true';
    }

    // Show loading state
    $('#saveFlightDetails').prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');

    $.ajax({
        url: form.attr('action'),
        method: 'POST',
        data: formData,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.overlap_detected && !forceSave) {
                // Show browser confirmation dialog for overlap
                const userConfirmed = confirm(response.overlap_message);

                if (userConfirmed) {
                    // User wants to proceed, save with force flag
                    saveFlightDetails(true);
                    return;
                } else {
                    // User cancelled, reset button state
                    $('#saveFlightDetails').prop('disabled', false).html('Save Changes');
                    return;
                }
            }

            if (response.success) {
                // Close modal immediately
                closeModal();

                // Show success message below breadcrumbs
                showMessage('success', response.message || 'Flight details updated successfully!');

                // Reload only the table/matrix
                reloadBayMatrix();
            } else {
                // Show error message in the modal
                showModalMessage('danger', response.message || 'Error saving flight details.');
            }
        },
        error: function(xhr, status, error) {
            const errorMsg = xhr.responseJSON?.message || 'Error saving flight details. Please try again.';
            // Show error message in the modal
            showModalMessage('danger', errorMsg);
            console.error('Error saving flight details:', error);
        },
        complete: function() {
            $('#saveFlightDetails').prop('disabled', false).html('Save Changes');
        }
    });
}

// Re-attach modal footer event handlers (global scope)
function reattachModalFooterHandlers() {
    // Remove any existing handlers to prevent duplicates
    $('#closeModalBtn').off('click');
    $('#modalCloseX').off('click');
    $('#saveFlightDetails').off('click');
    
    // Re-attach handlers
    $('#closeModalBtn').on('click', function() {
        closeModal();
    });
    
    $('#modalCloseX').on('click', function() {
        closeModal();
    });
    
    $('#saveFlightDetails').on('click', function() {
        saveFlightDetails();
    });
}

// Delete ad hoc flight function (global scope)
function deleteAdhocFlight(flightId) {
    if (confirm('Are you sure you want to delete this ad hoc flight? This action cannot be undone.')) {
        $.ajax({
            url: '{{ route("admin.events.bay-management.delete-adhoc-flight", $event) }}',
            method: 'DELETE',
            data: {
                flight_id: flightId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message below breadcrumbs
                    showMessage('success', 'Ad hoc flight deleted successfully!');
                    // Use the proper modal close function
                    closeModalSafely();
                    // Reload only the table/matrix (preserves scroll position)
                    reloadBayMatrix();
                } else {
                    showModalMessage('danger', response.message || 'Error deleting ad hoc flight.');
                }
            },
            error: function(xhr) {
                console.error('Error deleting ad hoc flight:', xhr);
                showModalMessage('danger', 'Error deleting ad hoc flight. Please try again.');
            }
        });
    }
}

// Show modal message function (global scope)
function showModalMessage(type, message) {
    // Remove any existing modal messages
    $('.modal-message').remove();

    // Create message element
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const iconClass = type === 'success' ? 'fa-check' : 'fa-exclamation-triangle';

    const messageHtml = `
        <div class="alert ${alertClass} alert-dismissible modal-message" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <i class="fa ${iconClass} mr-2"></i>${message}
        </div>
    `;

    // Insert message at the top of the modal body
    // Check which modal is currently open and target the appropriate content area
    if ($('#adhocFlightModal').hasClass('show')) {
        $('#adhocFlightContent').prepend(messageHtml);
    } else {
        $('#flightDetailsContent').prepend(messageHtml);
    }

    // Auto-dismiss success messages after 5 seconds
    if (type === 'success') {
        setTimeout(function() {
            $('.modal-message').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
}

// Show main page message function (global scope)
function showMessage(type, message) {
    // Remove any existing messages
    $('.bay-management-message').remove();

    // Create message element
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const iconClass = type === 'success' ? 'fa-check' : 'fa-exclamation-triangle';

    const messageHtml = `
        <div class="alert ${alertClass} alert-dismissible bay-management-message" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <i class="fa ${iconClass} mr-2"></i>${message}
        </div>
    `;

    // Insert message below breadcrumbs
    $('.container-fluid .row .col-12 .mb-3').after(messageHtml);

    // Auto-dismiss success messages after 5 seconds
    if (type === 'success') {
        setTimeout(function() {
            $('.bay-management-message').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
}

// Initialize table event handlers function (global scope)
function initializeTableEventHandlers() {
    // Handle clicks on existing flight assignment cells
    $(document).off('click', '.flight-assignment-cell').on('click', '.flight-assignment-cell', function(e) {
        e.stopPropagation();

        // Focus tracking
        $('.flight-assignment-cell').removeClass('last-clicked');
        $(this).addClass('last-clicked');

        // Get flight data from the cell itself
        const flightId = $(this).data('flight-id');
        const assignmentType = $(this).data('assignment-type');

        if (flightId) {
            // Check if this is an ad hoc flight
            if (assignmentType === 'adhoc') {
                // For ad hoc flights, show a simple info modal instead of trying to load details
                showAdhocFlightInfo(flightId);
            } else {
                loadFlightDetails(flightId, assignmentType);
            }
        }
    });

    // Handle clicks on empty cells for ad hoc flights
    $(document).off('click', '.time-slot.table-light').on('click', '.time-slot.table-light', function(e) {
        e.stopPropagation();

        // Get the bay and time information from the cell
        const $row = $(this).closest('tr');
        const gateName = $row.data('gate');
        const $header = $('.gate-table thead th');
        const cellIndex = $(this).index();
        const timeSlot = $header.eq(cellIndex).text();

        // Get bay ID from the row data
        const bayId = $row.data('bay-id') || $row.find('td:first').data('bay-id');

        // Store the selected information
        window.selectedAdhocData = {
            gateName: gateName,
            timeSlot: timeSlot,
            bayId: bayId,
            timeSlotIndex: cellIndex
        };

        // Set the time slot in the hidden field
        $('#selectedTimeSlot').val(timeSlot);

        // Show the ad hoc flight modal
        $('#adhocFlightModal').modal('show');
    });
}

// Initialize tooltips function (global scope)
function initializeTooltips() {
    // Initialize Bootstrap tooltips for gate overlap warnings
    $('[data-toggle="tooltip"]').tooltip({
        trigger: 'hover',
        placement: 'right',
        container: 'body'
    });
}

// Apply filters function (global scope)
function applyFilters() {
    const gateFilters = window.getGateFilters ? window.getGateFilters() : [];
    const aircraftFilterText = $('#aircraftFilter').val().trim();

    const $tableRows = $('.gate-table tbody tr');
    $tableRows.each(function() {
        const $row = $(this);
        const gateName = $row.data('gate');
        let showRow = true;

        // Apply gate filters - this controls row visibility
        if (gateFilters.length > 0) {
            showRow = false;
            for (let filter of gateFilters) {
                if (gateName && typeof gateName === 'string' && gateName.toLowerCase().startsWith(filter.toLowerCase())) {
                    showRow = true;
                    break;
                }
            }
        }

        // Show or hide the entire row based on gate filter
        if (showRow) {
            $row.show();

            // Apply aircraft filter to flight cells within visible rows
            if (aircraftFilterText) {
                const $flightCells = $row.find('.flight-assignment-cell');
                
                $flightCells.each(function() {
                    const $cell = $(this);
                    const aircraftType = $cell.data('aircraft-type') || '';
                    const matchesFilter = aircraftType.toLowerCase().includes(aircraftFilterText.toLowerCase());
                    
                    // Apply visual styling based on match
                    if (matchesFilter) {
                        $cell.removeClass('aircraft-filter-dimmed');
                    } else {
                        $cell.addClass('aircraft-filter-dimmed');
                    }
                });
            } else {
                // Clear aircraft filter styling when no filter is applied
                $row.find('.flight-assignment-cell').removeClass('aircraft-filter-dimmed');
            }
        } else {
            $row.hide();
        }
    });
}

// Generate warning banner from matrix function (global scope)
function generateWarningBannerFromMatrix() {
    // This function should be implemented based on existing logic
    // For now, just a placeholder to prevent ReferenceError
}

// Show ad hoc flight info function (global scope)
function showAdhocFlightInfo(flightId) {
    // Show modal with loading state
    $('#flightDetailsModal').modal('show');
    $('#flightDetailsContent').html(`
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading ad hoc flight details...</p>
        </div>
    `);

    // Load ad hoc flight details for editing
    loadAdhocFlightForEdit(flightId);
}

// Load ad hoc flight for edit function (global scope)
function loadAdhocFlightForEdit(flightId) {
    $.ajax({
        url: '{{ route("admin.events.bay-management.get-adhoc-flight", $event) }}',
        method: 'GET',
        data: { flight_id: flightId },
        success: function(response) {
            if (response.success) {
                displayAdhocFlightEditForm(response.flight);
            } else {
                showModalMessage('danger', response.message || 'Error loading ad hoc flight details.');
            }
        },
        error: function(xhr) {
            console.error('Error loading ad hoc flight details:', xhr);
            showModalMessage('danger', 'Error loading ad hoc flight details. Please try again.');
        }
    });
}

// Load flight details function (global scope)
function loadFlightDetails(flightId, assignmentType) {
    // Show modal with loading state
    $('#flightDetailsModal').modal('show');
    $('#flightDetailsContent').html(`
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    `);
    $('#saveFlightDetails').hide();

    // Ensure original footer is restored and handlers are attached
    restoreOriginalModalFooter();

    // Load flight details via AJAX
    $.ajax({
        url: '{{ route("admin.events.bay-management.flight-details", [$event, "__FLIGHT_ID__"]) }}'.replace('__FLIGHT_ID__', flightId),
        method: 'GET',
        data: { assignment_type: assignmentType },
        success: function(response) {
            $('#flightDetailsContent').html(response.html);
            $('#saveFlightDetails').show();
            // Re-attach handlers after content loads (in case footer was modified)
            reattachModalFooterHandlers();
        },
        error: function(xhr, status, error) {
            $('#flightDetailsContent').html(`
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-triangle mr-2"></i>
                    Error loading flight details. Please try again.
                </div>
            `);
            console.error('Error loading flight details:', error);
            // Re-attach handlers even on error
            reattachModalFooterHandlers();
        }
    });
}

// Display ad hoc flight edit form function (global scope)
function displayAdhocFlightEditForm(flight) {
    const flightType = flight.dep == '{{ $event->dep }}' ? 'departure' : 'arrival';
    const stdValue = flight.std ? new Date(flight.std).toISOString().slice(0, 16) : '';
    const staValue = flight.sta ? new Date(flight.sta).toISOString().slice(0, 16) : '';
    const bayFromValue = flight.bay_assigned_from ? new Date(flight.bay_assigned_from).toISOString().slice(0, 16) : '';
    const bayToValue = flight.bay_assigned_to ? new Date(flight.bay_assigned_to).toISOString().slice(0, 16) : '';

    // Build form HTML using string concatenation to avoid template literal issues
    let formHtml = '<form id="editAdhocFlightForm">';
    formHtml += '<input type="hidden" id="editFlightId" value="' + flight.id + '">';
    formHtml += '<input type="hidden" id="editFlightType" value="' + flightType + '">';

    formHtml += '<div class="row mb-4">';
    formHtml += '<div class="col-12">';
    formHtml += '<h6 class="text-primary mb-3"><i class="fa fa-edit mr-2"></i>Edit Ad Hoc Flight</h6>';
    formHtml += '</div>';
    formHtml += '<div class="col-md-6">';
    formHtml += '<div class="form-group">';
    formHtml += '<label for="editCallsign" class="font-weight-bold">Callsign <span class="text-danger">*</span>:</label>';
    formHtml += '<input type="text" class="form-control" id="editCallsign" name="callsign" value="' + (flight.callsign || '') + '" required>';
    formHtml += '</div>';
    formHtml += '</div>';
    formHtml += '<div class="col-md-6">';
    formHtml += '<div class="form-group">';
    formHtml += '<label for="editAcType" class="font-weight-bold">Aircraft Type <span class="text-danger">*</span>:</label>';
    formHtml += '<input type="text" class="form-control" id="editAcType" name="acType" value="' + (flight.acType || '') + '" required>';
    formHtml += '</div>';
    formHtml += '</div>';
    formHtml += '</div>';

    formHtml += '<div class="row mb-4">';
    formHtml += '<div class="col-12">';
    formHtml += '<h6 class="text-success mb-3"><i class="fa fa-map-marker-alt mr-2"></i>Airport Information</h6>';
    formHtml += '</div>';
    formHtml += '<div class="col-md-6">';
    formHtml += '<div class="form-group">';
    formHtml += '<label for="editDep" class="font-weight-bold">Departure Airport:</label>';
    formHtml += '<select class="form-control" id="editDep" name="dep">';
    formHtml += '<option value="">-- Select Airport --</option>';
    // Airport options will be populated via AJAX
    formHtml += '</select>';
    formHtml += '</div>';
    formHtml += '</div>';
    formHtml += '<div class="col-md-6">';
    formHtml += '<div class="form-group">';
    formHtml += '<label for="editArr" class="font-weight-bold">Arrival Airport:</label>';
    formHtml += '<select class="form-control" id="editArr" name="arr">';
    formHtml += '<option value="">-- Select Airport --</option>';
    // Airport options will be populated via AJAX
    formHtml += '</select>';
    formHtml += '</div>';
    formHtml += '</div>';
    formHtml += '</div>';

    formHtml += '<div class="row mb-4">';
    formHtml += '<div class="col-12">';
    formHtml += '<h6 class="text-info mb-3"><i class="fa fa-clock mr-2"></i>Time Information</h6>';
    formHtml += '</div>';
    formHtml += '<div class="col-md-6">';
    formHtml += '<div class="form-group">';
    formHtml += '<label for="editStd" class="font-weight-bold">STD (Scheduled Time of Departure):</label>';
    formHtml += '<input type="datetime-local" class="form-control" id="editStd" name="std" value="' + stdValue + '">';
    formHtml += '</div>';
    formHtml += '</div>';
    formHtml += '<div class="col-md-6">';
    formHtml += '<div class="form-group">';
    formHtml += '<label for="editSta" class="font-weight-bold">STA (Scheduled Time of Arrival):</label>';
    formHtml += '<input type="datetime-local" class="form-control" id="editSta" name="sta" value="' + staValue + '">';
    formHtml += '</div>';
    formHtml += '</div>';
    formHtml += '</div>';

    formHtml += '<div class="row mb-4">';
    formHtml += '<div class="col-12">';
    formHtml += '<h6 class="text-warning mb-3"><i class="fa fa-building mr-2"></i>Bay Assignment</h6>';
    formHtml += '</div>';
    formHtml += '<div class="col-md-12">';
    formHtml += '<div class="form-group">';
    formHtml += '<label class="font-weight-bold">Assigned Gate:</label>';
    formHtml += '<select class="form-control" id="editBayId" name="bay_id">';
    formHtml += '<option value="">-- Select Gate --</option>';
    // Bay options will be populated via AJAX
    formHtml += '</select>';
    formHtml += '</div>';
    formHtml += '</div>';
    formHtml += '<div class="col-md-6">';
    formHtml += '<div class="form-group">';
    formHtml += '<label class="font-weight-bold">Booked From:</label>';
    formHtml += '<input type="datetime-local" class="form-control" id="editBayAssignedFrom" name="bay_assigned_from" value="' + bayFromValue + '">';
    formHtml += '</div>';
    formHtml += '</div>';
    formHtml += '<div class="col-md-6">';
    formHtml += '<div class="form-group">';
    formHtml += '<label class="font-weight-bold">Booked To:</label>';
    formHtml += '<input type="datetime-local" class="form-control" id="editBayAssignedTo" name="bay_assigned_to" value="' + bayToValue + '">';
    formHtml += '</div>';
    formHtml += '</div>';
    formHtml += '</div>';
    formHtml += '</form>';

    $('#flightDetailsContent').html(formHtml);

    // Update modal footer for edit mode
    let footerHtml = '<button type="button" class="btn btn-danger mr-2" onclick="deleteAdhocFlight(' + flight.id + ')">';
    footerHtml += '<i class="fa fa-trash mr-1"></i>Delete Flight';
    footerHtml += '</button>';
    footerHtml += '<button type="button" class="btn btn-secondary mr-2" onclick="closeModalSafely()">';
    footerHtml += '<i class="fa fa-times mr-1"></i>Cancel';
    footerHtml += '</button>';
    footerHtml += '<button type="button" class="btn btn-primary" id="saveAdhocFlightEditBtn">';
    footerHtml += '<i class="fa fa-save mr-1"></i>Save Changes';
    footerHtml += '</button>';

    $('#flightDetailsModal .modal-footer').html(footerHtml);

    // Add event listener for the save button
    $('#saveAdhocFlightEditBtn').off('click').on('click', function() {
        saveAdhocFlightEdit();
    });

    // Set the selected values after the form is created
    setTimeout(() => {
        // First populate the options
        populateAirportOptions();

        // Then set the selected values after a short delay
        setTimeout(() => {
            $('#editDep').val(flight.dep || '');
            $('#editArr').val(flight.arr || '');
            $('#editBayId').val(flight.bay_id || '');
        }, 50);
    }, 100);
}

// Populate airport and bay options function (global scope)
function populateAirportOptions() {
    // Get airports from the existing select elements in the page
    const $existingDepSelect = $('#dep');
    const $existingArrSelect = $('#arr');
    const $existingBaySelect = $('#assignedGate');

    if ($existingDepSelect.length > 0) {
        const depOptions = $existingDepSelect.html();
        $('#editDep').html(depOptions);
    }

    if ($existingArrSelect.length > 0) {
        const arrOptions = $existingArrSelect.html();
        $('#editArr').html(arrOptions);
    }

    if ($existingBaySelect.length > 0) {
        const bayOptions = $existingBaySelect.html();
        const $editBaySelect = $('#editBayId');
        if ($editBaySelect.length > 0) {
            $editBaySelect.html(bayOptions);
        }
    }
}

// Reload bay matrix function (global scope)
function reloadBayMatrix() {
    // Capture current scroll position before refresh
    const $tableContainer = $('.table-container');
    const scrollTop = $tableContainer.scrollTop();
    const scrollLeft = $tableContainer.scrollLeft();

    // Store the position for visual feedback
    window.lastScrollPosition = { top: scrollTop, left: scrollLeft };

    // Show loading indicator
    $tableContainer.prepend(`
        <div class="text-center p-3" id="tableLoadingIndicator">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <div class="mt-2">Updating bay assignments...</div>
        </div>
    `);

    // Reload the page content via AJAX
    $.ajax({
        url: window.location.href,
        method: 'GET',
        success: function(response) {
            // Extract the table content from the response
            const $newContent = $(response);
            const $newTable = $newContent.find('.table-container');

            if ($newTable.length > 0) {
                // Replace the table container
                $tableContainer.replaceWith($newTable);

                // Re-bind event handlers for the new table
                initializeTableEventHandlers();

                // Re-initialize tooltips for the new table
                initializeTooltips();

                // Re-apply current filters if any are selected
                applyFilters();

                // Refresh warning data after table update
                generateWarningBannerFromMatrix();

                // Restore scroll position after a brief delay to ensure DOM is ready
                setTimeout(function() {
                    const $newTableContainer = $('.table-container');

                    // Ensure the scroll position doesn't exceed the new content dimensions
                    const maxScrollTop = $newTableContainer[0].scrollHeight - $newTableContainer[0].clientHeight;
                    const maxScrollLeft = $newTableContainer[0].scrollWidth - $newTableContainer[0].clientWidth;

                    const adjustedScrollTop = Math.min(scrollTop, maxScrollTop);
                    const adjustedScrollLeft = Math.min(scrollLeft, maxScrollLeft);

                    // Only restore scroll position if it was greater than 0
                    if (scrollTop > 0 || scrollLeft > 0) {
                        $newTableContainer.scrollTop(adjustedScrollTop);
                        $newTableContainer.scrollLeft(adjustedScrollLeft);

                        // Add subtle visual feedback that position was restored
                        $newTableContainer.addClass('position-restored');
                        setTimeout(function() {
                            $newTableContainer.removeClass('position-restored');
                        }, 1000);
                    }
                }, 100); // Increased delay to ensure DOM is fully ready
            }
        },
        error: function(xhr, status, error) {
            console.error('Error reloading bay matrix:', error);
            showMessage('danger', 'Error updating bay assignments. Please refresh the page.');
        },
        complete: function() {
            // Remove loading indicator
            $('#tableLoadingIndicator').remove();
        }
    });
}

// Save ad hoc flight edit function (global scope)
function saveAdhocFlightEdit() {
    const flightId = $('#editFlightId').val();
    const formData = {
        flight_id: flightId,
        callsign: $('#editCallsign').val(),
        acType: $('#editAcType').val(),
        dep: $('#editDep').val(),
        arr: $('#editArr').val(),
        std: $('#editStd').val(),
        sta: $('#editSta').val(),
        bay_id: $('#editBayId').val(),
        bay_assigned_from: $('#editBayAssignedFrom').val(),
        bay_assigned_to: $('#editBayAssignedTo').val(),
        _token: '{{ csrf_token() }}'
    };

    // Validate required fields
    if (!formData.callsign || !formData.acType || !formData.bay_id) {
        showModalMessage('danger', 'Please fill in all required fields (Callsign, Aircraft Type, and Gate).');
        return;
    }

    // Show loading state
    const $saveButton = $('#saveAdhocFlightEditBtn');
    $saveButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');

    $.ajax({
        url: '{{ route("admin.events.bay-management.update-adhoc-flight", $event) }}',
        method: 'PUT',
        data: formData,
        success: function(response) {
            if (response.success) {
                // Show success message below breadcrumbs
                showMessage('success', 'Ad hoc flight updated successfully!');
                // Use the proper modal close function
                closeModalSafely();
                // Reload only the table/matrix (preserves scroll position)
                reloadBayMatrix();
            } else {
                showModalMessage('danger', response.message || 'Error updating ad hoc flight.');
            }
        },
        error: function(xhr) {
            console.error('Error updating ad hoc flight:', xhr);
            showModalMessage('danger', 'Error updating ad hoc flight. Please try again.');
        },
        complete: function() {
            $saveButton.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Changes');
        }
    });
}

$(document).ready(function() {
    // Initialize table event handlers
    initializeTableEventHandlers();

    // Initialize gate filter
    initializeGateFilter();

    // Initialize tooltips for gate overlap warnings
    initializeTooltips();

    // Initialize bay blocking functionality
    initializeBayBlocking();

    // Initialize warning banner
    initializeWarningBanner();

    // Initialize modal footer handlers on page load
    reattachModalFooterHandlers();

    // Initialize ad hoc flight modal handlers
    initializeAdhocFlightModal();

    // Handle modal close events to prevent ARIA focus issues
    $('#flightDetailsModal').on('hide.bs.modal', function() {
        // Remove focus from any focused element BEFORE modal is hidden
        if (document.activeElement && document.activeElement.blur) {
            document.activeElement.blur();
        }
        // Return focus to the body to prevent ARIA issues
        document.body.focus();
    });

    $('#flightDetailsModal').on('hidden.bs.modal', function() {
        // Ensure no element has focus after modal is completely hidden
        if (document.activeElement && document.activeElement.blur) {
            document.activeElement.blur();
        }
        // Restore original modal footer for regular flights
        restoreOriginalModalFooter();
    });

    // Handle modal show events to manage focus properly
    $('#flightDetailsModal').on('shown.bs.modal', function() {
        // Focus on the first input field when modal is shown
        const firstInput = $(this).find('input, select, textarea').first();
        if (firstInput.length) {
            firstInput.focus();
        }
    });

    function initializeGateFilter() {
        let gateFilters = [];

        $('#gateFilter').on('keypress', function(e) {
            if (e.which === 13 || e.keyCode === 13) { // Enter key
                e.preventDefault();
                addGateFilter();
            }
        });

        $('#gateFilter').on('blur', function() {
            if ($(this).val().trim()) {
                addGateFilter();
            }
        });

        $('#aircraftFilter').on('input', function() {
            applyFilters();
        });

        function addGateFilter() {
            const gateName = $('#gateFilter').val().trim();
            if (gateName && !gateFilters.includes(gateName)) {
                gateFilters.push(gateName);
                renderGateChips();
                $('#gateFilter').val('');
                applyFilters();
            }
        }

        function removeGateFilter(gateName) {
            gateFilters = gateFilters.filter(filter => filter !== gateName);
            renderGateChips();
            applyFilters();
        }

        function renderGateChips() {
            const $chipsContainer = $('#gateFilterChips');
            const $clearAllBtn = $('#clearAllChips');
            $chipsContainer.empty();

            gateFilters.forEach(gateName => {
                const chip = $(`
                    <span class="gate-filter-chip">
                        ${gateName}
                        <span class="remove-chip" data-gate="${gateName}">&times;</span>
                    </span>
                `);
                $chipsContainer.append(chip);
            });

            // Show/hide Clear All button based on whether there are chips
            if (gateFilters.length > 0) {
                $clearAllBtn.show();
            } else {
                $clearAllBtn.hide();
            }

            // Bind remove events
            $('.remove-chip').on('click', function() {
                const gateName = $(this).data('gate');
                removeGateFilter(gateName);
            });
        }

        // Clear All button handler
        $('#clearAllChips').on('click', function() {
            gateFilters = [];
            renderGateChips();
            applyFilters();
        });

        // Make functions available globally for applyFilters
        window.getGateFilters = function() {
            return gateFilters;
        };
    }

    // Handle ESC key press
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#flightDetailsModal').hasClass('show')) {
            closeModal();
        }
    });

    // Handle modal close events to properly manage focus
    $('#flightDetailsModal').on('hidden.bs.modal', function() {
        // Clear any focus and ensure proper cleanup
        $(this).removeAttr('aria-hidden');
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();

        // Return focus to the clicked flight cell if it exists
        const lastClickedCell = $('.flight-assignment-cell.last-clicked');
        if (lastClickedCell.length) {
            lastClickedCell.focus().removeClass('last-clicked');
        }
    });

    function initializeBayBlocking() {
        // Load blocked bays when modal is shown
        $('#bayBlockingModal').on('show.bs.modal', function() {
            loadBlockedBays();
        });

        // Refresh bay matrix when modal is closed
        $('#bayBlockingModal').on('hidden.bs.modal', function() {
            reloadBayMatrix();
        });

        // Handle modal close to prevent aria-hidden warning
        $('#bayBlockingModal').on('hide.bs.modal', function() {
            // Remove focus from any focused elements in the modal
            $(this).find(':focus').blur();
        });

        // Handle add bay blocking form submission
        $('#addBayBlockingForm').on('submit', function(e) {
            e.preventDefault();
            addBayBlocking();
        });
    }

    function loadBlockedBays() {
        $.ajax({
            url: '{{ route("admin.events.bay-management.blocked-bays", $event) }}',
            method: 'GET',
            success: function(response) {
                const $list = $('#blockedBaysList');
                $list.empty();

                if (response.blockedBays && response.blockedBays.length > 0) {
                    response.blockedBays.forEach(function(blocking) {
                        const bayItem = $(`
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                <div>
                                    <i class="fa fa-ban text-warning mr-2"></i>
                                    <strong>${blocking.bay.name}</strong>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-blocking"
                                        data-blocking-id="${blocking.id}" data-bay-name="${blocking.bay.name}">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        `);
                        $list.append(bayItem);
                    });
                } else {
                    $list.html('<div class="text-center text-muted"><i class="fa fa-check-circle mr-2"></i>No bays are currently blocked</div>');
                }

                // Bind remove events
                $('.remove-blocking').on('click', function() {
                    const blockingId = $(this).data('blocking-id');
                    const bayName = $(this).data('bay-name');
                    removeBayBlocking(blockingId, bayName);
                });
            },
            error: function(xhr, status, error) {
                $('#blockedBaysList').html('<div class="alert alert-danger"><i class="fa fa-exclamation-triangle mr-2"></i>Error loading blocked bays</div>');
                console.error('Error loading blocked bays:', error);
            }
        });
    }

    function addBayBlocking() {
        const bayId = $('#baySelect').val();
        if (!bayId) {
            showBayBlockingMessage('danger', 'Please select a bay to block.');
            return;
        }

        const $submitBtn = $('#addBayBlockingForm button[type="submit"]');
        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Blocking...');

        $.ajax({
            url: '{{ route("admin.events.bay-management.block-bay", $event) }}',
            method: 'POST',
            data: {
                bay_id: bayId,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showBayBlockingMessage('success', response.message);
                    $('#baySelect').val('');
                    loadBlockedBays();
                    updateBaySelectionDropdown();
                } else {
                    showBayBlockingMessage('danger', response.message || 'Error blocking bay.');
                }
            },
            error: function(xhr, status, error) {
                const errorMsg = xhr.responseJSON?.message || 'Error blocking bay. Please try again.';
                showBayBlockingMessage('danger', errorMsg);
                console.error('Error blocking bay:', error);
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html('<i class="fa fa-ban mr-1"></i>Block Bay');
            }
        });
    }

    function removeBayBlocking(blockingId, bayName) {
        if (!confirm(`Are you sure you want to unblock bay ${bayName}?`)) {
            return;
        }

        $.ajax({
            url: '{{ route("admin.events.bay-management.unblock-bay", $event) }}',
            method: 'DELETE',
            data: {
                blocking_id: blockingId,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showBayBlockingMessage('success', response.message);
                    loadBlockedBays();
                    updateBaySelectionDropdown();
                } else {
                    showBayBlockingMessage('danger', response.message || 'Error unblocking bay.');
                }
            },
            error: function(xhr, status, error) {
                const errorMsg = xhr.responseJSON?.message || 'Error unblocking bay. Please try again.';
                showBayBlockingMessage('danger', errorMsg);
                console.error('Error unblocking bay:', error);
            }
        });
    }

    function showBayBlockingMessage(type, message) {
        // Remove any existing messages
        $('.bay-blocking-message').remove();

        // Create message element
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const iconClass = type === 'success' ? 'fa-check' : 'fa-exclamation-triangle';

        const messageHtml = `
            <div class="alert ${alertClass} alert-dismissible bay-blocking-message" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <i class="fa ${iconClass} mr-2"></i>${message}
            </div>
        `;

        // Insert message at the top of the modal body
        $('#bayBlockingModal .modal-body').prepend(messageHtml);

        // Auto-dismiss success messages after 3 seconds
        if (type === 'success') {
            setTimeout(function() {
                $('.bay-blocking-message').fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    }

    function updateBaySelectionDropdown() {
        // Reload the blocked bays to get updated list
        $.ajax({
            url: '{{ route("admin.events.bay-management.blocked-bays", $event) }}',
            method: 'GET',
            success: function(response) {
                const $select = $('#baySelect');
                const currentValue = $select.val();

                // Get all available bays (from the original data)
                const allBays = @json($bays);
                const blockedBayIds = response.blockedBays.map(blocking => blocking.bay.id);

                // Clear current options except the first one
                $select.find('option:not(:first)').remove();

                // Add available bays (not blocked)
                allBays.forEach(function(bay) {
                    if (!blockedBayIds.includes(bay.id)) {
                        $select.append(`<option value="${bay.id}">${bay.name}</option>`);
                    }
                });

                // Restore previous selection if it's still valid
                if (currentValue && !blockedBayIds.includes(parseInt(currentValue))) {
                    $select.val(currentValue);
                }
            },
            error: function() {
                console.error('Error updating bay selection dropdown');
            }
        });
    }

    function initializeWarningBanner() {
        // Generate warning banner from existing matrix data on page load
        generateWarningBannerFromMatrix();

        // Set up periodic refresh of warning data (every 30 seconds)
        setInterval(generateWarningBannerFromMatrix, 30000);
    }

    function generateWarningBannerFromMatrix() {
        const $banner = $('#warningBanner');
        const $content = $('#warningContent');

        // Scan the matrix table for warning indicators
        const overlappingBays = [];
        const blockedBayAssignments = [];

        // Find all bay rows (main rows, not sub-rows)
        $('.bay-main-row').each(function() {
            const $row = $(this);
            const gateName = $row.data('gate');

            if (!gateName) return;

            // Check for overlap warning icon
            const $overlapIcon = $row.find('.gate-overlap-warning');
            if ($overlapIcon.length > 0) {
                // Extract overlap count from tooltip title
                const tooltipTitle = $overlapIcon.attr('title') || '';
                const overlapMatch = tooltipTitle.match(/(\d+) overlapping assignments/);
                const overlapCount = overlapMatch ? parseInt(overlapMatch[1]) : 2;

                overlappingBays.push({
                    bay_name: gateName,
                    overlap_count: overlapCount
                });
            }

            // Check for blocked bay icon
            const $blockedIcon = $row.find('.bay-blocked-icon');
            if ($blockedIcon.length > 0) {
                // Check if this bay has any flight assignments
                const hasAssignments = $row.find('.flight-assignment-cell').length > 0;
                if (hasAssignments) {
                    blockedBayAssignments.push({
                        bay_name: gateName
                    });
                }
            }
        });

        // Sort the arrays to match backend behavior
        overlappingBays.sort((a, b) => compareBayNames(a.bay_name, b.bay_name));
        blockedBayAssignments.sort((a, b) => compareBayNames(a.bay_name, b.bay_name));

        // Generate warning content
        let warningHtml = '';
        let hasWarnings = false;

        // Add overlapping bay warnings
        if (overlappingBays.length > 0) {
            const overlappingBayNames = overlappingBays.map(bay => bay.bay_name).join(', ');
            warningHtml += `
                <div class="mb-2">
                    <strong><i class="fa fa-exclamation-triangle mr-1"></i>Bay overlapping assignment for:</strong> ${overlappingBayNames}
                </div>
            `;
            hasWarnings = true;
        }

        // Add blocked bay assignment warnings
        if (blockedBayAssignments.length > 0) {
            const blockedBayNames = blockedBayAssignments.map(assignment => assignment.bay_name).join(', ');
            warningHtml += `
                <div class="mb-2">
                    <strong><i class="fa fa-ban mr-1"></i>Blocked bay assigned for:</strong> ${blockedBayNames}
                </div>
            `;
            hasWarnings = true;
        }

        // Show or hide banner
        if (hasWarnings) {
            $content.html(warningHtml);
            $banner.show();
        } else {
            $banner.hide();
        }
    }

    /**
     * Compare bay names using the same sorting logic as the backend
     * This ensures consistent ordering between the table matrix and warning banner
     */
    function compareBayNames(nameA, nameB) {
        const sortKeyA = getBaySortKey(nameA);
        const sortKeyB = getBaySortKey(nameB);
        return sortKeyA.localeCompare(sortKeyB);
    }

    /**
     * Generate sort key for bay name using the same logic as the backend
     */
    function getBaySortKey(name) {
        // Check if the name starts with a letter
        if (/^[A-Z]/.test(name)) {
            // Letter-prefixed gates: extract letter, number, and suffix for proper sorting
            const match = name.match(/^([A-Z]+)(\d+)([A-Z]*)$/);
            if (match) {
                const letter = match[1];      // "A", "C", etc.
                const number = parseInt(match[2]); // 1, 17, etc.
                const suffix = match[3] || ''; // "L", "R", etc.

                // Create sortable key: letter + padded number + suffix
                // This ensures A1 < A2 < A10 < C1 < C17L < C17R
                return letter + number.toString().padStart(5, '0') + suffix;
            }
            // If it doesn't match the pattern, put it with letter gates but sort by name
            return '0' + name;
        } else {
            // Numeric-only gates: pad with zeros and prefix with 'ZZ' to put them last
            if (/^\d+$/.test(name)) {
                return 'ZZ' + name.padStart(5, '0');
            }
            // Other formats go last
            return 'ZZ' + name;
        }
    }

    function initializeAdhocFlightModal() {
        // Handle departure/arrival selection
        $('#selectDeparture').on('click', function() {
            selectFlightType('departure');
        });

        $('#selectArrival').on('click', function() {
            selectFlightType('arrival');
        });

        // Handle save button
        $('#saveAdhocFlight').on('click', function() {
            saveAdhocFlight();
        });

        // Handle modal close - reset form
        $('#adhocFlightModal').on('hidden.bs.modal', function() {
            resetAdhocFlightModal();
        });

        // Handle STD/STA changes to recalculate booking times
        $('#std, #sta').on('change', function() {
            const flightType = $('#flightType').val();
            if (flightType) {
                calculateBookingTimes(flightType);
            }
        });
    }

    function selectFlightType(type) {
        // Hide choice section and show form
        $('#adhocChoiceSection').hide();
        $('#adhocFlightForm').show();
        $('#saveAdhocFlight').show();

        // Set flight type
        $('#flightType').val(type);
        $('#assignmentTypeLabel').text(type === 'departure' ? 'Departure Bay Assignment' : 'Arrival Bay Assignment');

        // Pre-select the assigned gate from the clicked cell
        const bayId = getBayIdFromName(window.selectedAdhocData.gateName);
        $('#assignedGate').val(bayId);

        // Auto-populate airports based on event
        autoPopulateAirports(type);

        // Auto-populate STD/STA based on selected time slot
        autoPopulateFlightTimes(type);

        // Calculate and set booking times based on STD/STA
        calculateBookingTimes(type);
    }

    function autoPopulateAirports(type) {
        // Get event airports from the page data
        const eventDepId = '{{ $event->dep }}';
        const eventArrId = '{{ $event->arr }}';

        if (type === 'departure') {
            // For departure, set departure airport to event departure airport
            $('#dep').val(eventDepId);
        } else {
            // For arrival, set arrival airport to event arrival airport
            $('#arr').val(eventArrId);
        }
    }

    function autoPopulateFlightTimes(type) {
        const timeSlot = window.selectedAdhocData.timeSlot;

        // Parse the time slot (format: "HH:MM")
        const [hours, minutes] = timeSlot.split(':').map(Number);

        // Use event date instead of today's date
        const eventDate = '{{ $event->startEvent->format("Y-m-d") }}';
        const baseTime = new Date(eventDate + 'T' + timeSlot + ':00');

        if (type === 'departure') {
            // For departure, set STD to the selected time slot
            $('#std').val(formatDateTimeLocal(baseTime));
        } else {
            // For arrival, set STA to the selected time slot
            $('#sta').val(formatDateTimeLocal(baseTime));
        }
    }

    function getBayIdFromName(gateName) {
        // Get bay ID from the stored data
        return window.selectedAdhocData ? window.selectedAdhocData.bayId : null;
    }

    function calculateBookingTimes(type) {
        let baseTime;

        if (type === 'departure') {
            // For departure, use STD (CTOT) as the base time
            const stdValue = $('#std').val();
            if (!stdValue) return; // No STD set yet
            baseTime = new Date(stdValue);
        } else {
            // For arrival, use STA (ETA) as the base time
            const staValue = $('#sta').val();
            if (!staValue) return; // No STA set yet
            baseTime = new Date(staValue);
        }

        // Validate that the time is within the event date range
        const eventStart = new Date('{{ $event->startEvent->format("Y-m-d") }}');
        const eventEnd = new Date('{{ $event->endEvent->format("Y-m-d") }}');
        // For same-day events, allow the full day
        if (eventStart.getTime() === eventEnd.getTime()) {
            eventEnd.setHours(23, 59, 59, 999); // End of day
        } else {
            eventEnd.setDate(eventEnd.getDate() + 1); // Next day
        }

        // Compare only the date parts, not the time
        const baseDateOnly = new Date(baseTime.getFullYear(), baseTime.getMonth(), baseTime.getDate());
        const eventStartDate = new Date(eventStart.getFullYear(), eventStart.getMonth(), eventStart.getDate());
        const eventEndDate = new Date(eventEnd.getFullYear(), eventEnd.getMonth(), eventEnd.getDate());

        if (baseDateOnly < eventStartDate || baseDateOnly > eventEndDate) {
            alert('Flight time must be within the event date range ({{ $event->startEvent->format("Y-m-d") }} to {{ $event->endEvent->format("Y-m-d") }})');
            return;
        }

        // Calculate booking times based on existing BayAssignmentService logic
        // For departure: 20 minutes before to actual time (CTOT)
        // For arrival: 20 minutes before to 15 minutes after (ETA)
        const bookingFrom = new Date(baseTime);
        const bookingTo = new Date(baseTime);

        if (type === 'departure') {
            // Departure: 20 min before to actual CTOT
            bookingFrom.setMinutes(bookingFrom.getMinutes() - 20);
            // bookingTo stays at the actual time (CTOT)
        } else {
            // Arrival: 20 min before to 15 min after ETA
            bookingFrom.setMinutes(bookingFrom.getMinutes() - 20);
            bookingTo.setMinutes(bookingTo.getMinutes() + 15);
        }

        // Set the form values
        $('#bayAssignedFrom').val(formatDateTimeLocal(bookingFrom));
        $('#bayAssignedTo').val(formatDateTimeLocal(bookingTo));
    }

    function formatDateTimeLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    function saveAdhocFlight() {
        // Validate required fields
        if (!$('#callsign').val() || !$('#acType').val() || !$('#assignedGate').val()) {
            showModalMessage('danger', 'Please fill in all required fields (Callsign, Aircraft Type, and Gate).');
            return;
        }

        // Validate dates are within event range
        const eventStart = new Date('{{ $event->startEvent->format("Y-m-d") }}');
        const eventEnd = new Date('{{ $event->endEvent->format("Y-m-d") }}');
        // For same-day events, allow the full day
        if (eventStart.getTime() === eventEnd.getTime()) {
            eventEnd.setHours(23, 59, 59, 999); // End of day
        } else {
            eventEnd.setDate(eventEnd.getDate() + 1); // Next day
        }

        const stdValue = $('#std').val();
        const staValue = $('#sta').val();
        const bayFromValue = $('#bayAssignedFrom').val();
        const bayToValue = $('#bayAssignedTo').val();

        if (stdValue) {
            const stdDate = new Date(stdValue);
            const stdDateOnly = new Date(stdDate.getFullYear(), stdDate.getMonth(), stdDate.getDate());
            const eventStartDate = new Date(eventStart.getFullYear(), eventStart.getMonth(), eventStart.getDate());
            const eventEndDate = new Date(eventEnd.getFullYear(), eventEnd.getMonth(), eventEnd.getDate());

            if (stdDateOnly < eventStartDate || stdDateOnly > eventEndDate) {
                showModalMessage('danger', 'STD must be within the event date range ({{ $event->startEvent->format("Y-m-d") }} to {{ $event->endEvent->format("Y-m-d") }}).');
                return;
            }
        }

        if (staValue) {
            const staDate = new Date(staValue);
            const staDateOnly = new Date(staDate.getFullYear(), staDate.getMonth(), staDate.getDate());
            const eventStartDate = new Date(eventStart.getFullYear(), eventStart.getMonth(), eventStart.getDate());
            const eventEndDate = new Date(eventEnd.getFullYear(), eventEnd.getMonth(), eventEnd.getDate());

            if (staDateOnly < eventStartDate || staDateOnly > eventEndDate) {
                showModalMessage('danger', 'STA must be within the event date range ({{ $event->startEvent->format("Y-m-d") }} to {{ $event->endEvent->format("Y-m-d") }}).');
                return;
            }
        }

        if (bayFromValue) {
            const bayFromDate = new Date(bayFromValue);
            const bayFromDateOnly = new Date(bayFromDate.getFullYear(), bayFromDate.getMonth(), bayFromDate.getDate());
            const eventStartDate = new Date(eventStart.getFullYear(), eventStart.getMonth(), eventStart.getDate());
            const eventEndDate = new Date(eventEnd.getFullYear(), eventEnd.getMonth(), eventEnd.getDate());

            if (bayFromDateOnly < eventStartDate || bayFromDateOnly > eventEndDate) {
                showModalMessage('danger', 'Bay assignment times must be within the event date range ({{ $event->startEvent->format("Y-m-d") }} to {{ $event->endEvent->format("Y-m-d") }}).');
                return;
            }
        }

        if (bayToValue) {
            const bayToDate = new Date(bayToValue);
            const bayToDateOnly = new Date(bayToDate.getFullYear(), bayToDate.getMonth(), bayToDate.getDate());
            const eventStartDate = new Date(eventStart.getFullYear(), eventStart.getMonth(), eventStart.getDate());
            const eventEndDate = new Date(eventEnd.getFullYear(), eventEnd.getMonth(), eventEnd.getDate());

            if (bayToDateOnly < eventStartDate || bayToDateOnly > eventEndDate) {
                showModalMessage('danger', 'Bay assignment times must be within the event date range ({{ $event->startEvent->format("Y-m-d") }} to {{ $event->endEvent->format("Y-m-d") }}).');
                return;
            }
        }

        const form = $('#adhocFlightFormData');
        const formData = form.serialize();

        // Add the selected bay ID to the form data
        const bayId = $('#assignedGate').val();
        const formDataWithBay = formData + '&bay_id=' + encodeURIComponent(bayId);

        // Show loading state
        $('#saveAdhocFlight').prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');

        $.ajax({
            url: '{{ route("admin.events.bay-management.store-adhoc-flight", $event) }}',
            method: 'POST',
            data: formDataWithBay,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.overlap_detected && !response.force_save) {
                    // Show browser confirmation dialog for overlap
                    const userConfirmed = confirm(response.overlap_message);

                    if (userConfirmed) {
                        // Retry with force save
                        const forceData = formDataWithBay + '&force_save=true';
                        $.ajax({
                            url: '{{ route("admin.events.bay-management.store-adhoc-flight", $event) }}',
                            method: 'POST',
                            data: forceData,
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                handleAdhocFlightSuccess(response);
                            },
                            error: function(xhr) {
                                handleAdhocFlightError(xhr);
                            }
                        });
                        return;
                    } else {
                        // User cancelled, reset button state
                        $('#saveAdhocFlight').prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Ad Hoc Flight');
                        return;
                    }
                }

                handleAdhocFlightSuccess(response);
            },
            error: function(xhr) {
                handleAdhocFlightError(xhr);
            }
        });
    }

    function handleAdhocFlightSuccess(response) {
        // Close modal
        $('#adhocFlightModal').modal('hide');

        // Show success message
        showMessage('success', response.message || 'Ad hoc flight created successfully!');

        // Reload the bay matrix
        reloadBayMatrix();

        // Reset button state
        $('#saveAdhocFlight').prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Ad Hoc Flight');
    }

    function handleAdhocFlightError(xhr) {
        const errorMsg = xhr.responseJSON?.message || 'Error creating ad hoc flight. Please try again.';
        showModalMessage('danger', errorMsg);

        // Reset button state
        $('#saveAdhocFlight').prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Ad Hoc Flight');
    }

    function resetAdhocFlightModal() {
        // Reset form
        $('#adhocFlightFormData')[0].reset();

        // Hide form and show choice section
        $('#adhocFlightForm').hide();
        $('#adhocChoiceSection').show();
        $('#saveAdhocFlight').hide();

        // Clear any existing modal messages
        $('.modal-message').remove();

        // Clear selected data
        window.selectedAdhocData = null;
    }
});
</script>
@endpush
