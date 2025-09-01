<form id="flightDetailsForm" action="{{ route('admin.events.bay-management.update-flight-details', [$event, $flight]) }}" method="POST">
    @csrf
    <input type="hidden" name="assignment_type" value="{{ $assignmentType }}">
    
    <!-- Flight Information (Read-only) -->
    <div class="row mb-4">
        <div class="col-12">
            <h6 class="text-primary mb-3"><i class="fa fa-plane mr-2"></i>Flight Information</h6>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label class="font-weight-bold">Flight No:</label>
                <input type="text" class="form-control" value="{{ $flight->booking->callsign ?? 'N/A' }}" readonly>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label class="font-weight-bold">Aircraft Type:</label>
                <input type="text" class="form-control" value="{{ $flight->booking->acType ?? 'N/A' }}" readonly>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label class="font-weight-bold">Departure Airport:</label>
                <input type="text" class="form-control" value="{{ $flight->airportDep->name ?? 'N/A' }}@if($flight->airportDep->icao) ({{ $flight->airportDep->icao }})@endif" readonly>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label class="font-weight-bold">Arrival Airport:</label>
                <input type="text" class="form-control" value="{{ $flight->airportArr->name ?? 'N/A' }}@if($flight->airportArr->icao) ({{ $flight->airportArr->icao }})@endif" readonly>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label class="font-weight-bold">STD (Scheduled Time of Departure):</label>
                <input type="text" class="form-control" value="{{ $flight->ctot ? $flight->ctot->format('Y-m-d H:i') . ' UTC' : 'N/A' }}" readonly>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label class="font-weight-bold">STA (Scheduled Time of Arrival):</label>
                <input type="text" class="form-control" value="{{ $flight->eta ? $flight->eta->format('Y-m-d H:i') . ' UTC' : 'N/A' }}" readonly>
            </div>
        </div>
    </div>

    <hr>

    <!-- Bay Assignment Information (Editable) -->
    <div class="row">
        <div class="col-12">
            <h6 class="text-success mb-3">
                <i class="fa fa-building mr-2"></i>
                {{ ucfirst($assignmentType) }} Bay Assignment
            </h6>
        </div>
        
        @if($assignmentType === 'departure')
            <div class="col-md-12">
                <div class="form-group">
                    <label for="dep_bay" class="font-weight-bold">Assigned Gate:</label>
                    <select name="dep_bay" id="dep_bay" class="form-control">
                        <option value="">-- Select Gate --</option>
                        @foreach($bays as $bay)
                            <option value="{{ $bay->id }}" {{ $flight->dep_bay == $bay->id ? 'selected' : '' }}>
                                {{ $bay->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="dep_bay_assigned_from" class="font-weight-bold">Booked From:</label>
                    <input type="datetime-local" 
                           name="dep_bay_assigned_from" 
                           id="dep_bay_assigned_from" 
                           class="form-control"
                           value="{{ $flight->dep_bay_assigned_from ? $flight->dep_bay_assigned_from->format('Y-m-d\TH:i') : '' }}">
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="dep_bay_assigned_to" class="font-weight-bold">Booked To:</label>
                    <input type="datetime-local" 
                           name="dep_bay_assigned_to" 
                           id="dep_bay_assigned_to" 
                           class="form-control"
                           value="{{ $flight->dep_bay_assigned_to ? $flight->dep_bay_assigned_to->format('Y-m-d\TH:i') : '' }}">
                </div>
            </div>
        @else
            <div class="col-md-12">
                <div class="form-group">
                    <label for="arr_bay" class="font-weight-bold">Assigned Gate:</label>
                    <select name="arr_bay" id="arr_bay" class="form-control">
                        <option value="">-- Select Gate --</option>
                        @foreach($bays as $bay)
                            <option value="{{ $bay->id }}" {{ $flight->arr_bay == $bay->id ? 'selected' : '' }}>
                                {{ $bay->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="arr_bay_assigned_from" class="font-weight-bold">Booked From:</label>
                    <input type="datetime-local" 
                           name="arr_bay_assigned_from" 
                           id="arr_bay_assigned_from" 
                           class="form-control"
                           value="{{ $flight->arr_bay_assigned_from ? $flight->arr_bay_assigned_from->format('Y-m-d\TH:i') : '' }}">
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="arr_bay_assigned_to" class="font-weight-bold">Booked To:</label>
                    <input type="datetime-local" 
                           name="arr_bay_assigned_to" 
                           id="arr_bay_assigned_to" 
                           class="form-control"
                           value="{{ $flight->arr_bay_assigned_to ? $flight->arr_bay_assigned_to->format('Y-m-d\TH:i') : '' }}">
                </div>
            </div>
        @endif
    </div>

    <!-- Additional Information -->
    @if($flight->booking->user)
        <hr>
        <div class="row">
            <div class="col-12">
                <h6 class="text-info mb-3"><i class="fa fa-user mr-2"></i>Booking Information</h6>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="font-weight-bold">Pilot:</label>
                    <input type="text" class="form-control" value="{{ $flight->booking->user_id ? ($flight->booking->user->full_name ?: 'N/A') : 'N/A' }}" readonly>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="font-weight-bold">User ID:</label>
                    <input type="text" class="form-control" value="{{ $flight->booking->user_id ?? 'N/A' }}" readonly>
                </div>
            </div>
        </div>
    @endif
</form>

<script>
// Add some basic validation and datetime handling
$(document).ready(function() {
    // Set minimum datetime to current time
    const now = new Date();
    const currentDateTime = now.toISOString().slice(0, 16);
    
    $('input[type="datetime-local"]').attr('min', currentDateTime);
    
    // Auto-populate datetime fields when gate is selected
    $('select[name$="_bay"]').on('change', function() {
        const baySelected = $(this).val();
        const prefix = $(this).attr('name').replace('_bay', '');
        
        if (baySelected && !$(`input[name="${prefix}_bay_assigned_from"]`).val()) {
            // Default to current time if no value is set
            $(`input[name="${prefix}_bay_assigned_from"]`).val(currentDateTime);
        }
        
        if (baySelected && !$(`input[name="${prefix}_bay_assigned_to"]`).val()) {
            // Default to 2 hours later if no value is set
            const twoHoursLater = new Date(now.getTime() + (2 * 60 * 60 * 1000));
            $(`input[name="${prefix}_bay_assigned_to"]`).val(twoHoursLater.toISOString().slice(0, 16));
        }
    });
    
    // Validate that 'to' time is after 'from' time
    $('input[name$="_assigned_to"]').on('change', function() {
        const prefix = $(this).attr('name').replace('_bay_assigned_to', '');
        const fromTime = $(`input[name="${prefix}_bay_assigned_from"]`).val();
        const toTime = $(this).val();
        
        if (fromTime && toTime && new Date(toTime) <= new Date(fromTime)) {
            alert('End time must be after start time');
            $(this).val('');
        }
    });
});
</script>
