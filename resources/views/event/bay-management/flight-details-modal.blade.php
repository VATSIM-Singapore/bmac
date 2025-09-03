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

<style>
.btn-loading {
    cursor: not-allowed;
    opacity: 0.7;
}

.btn-loading:hover {
    opacity: 0.7;
}

/* Disable form inputs during submission */
.form-disabled input,
.form-disabled select,
.form-disabled textarea {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

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

    // Handle form submission to prevent multiple submissions
    $('#flightDetailsForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get the save button
        const saveButton = $('#saveFlightDetails');
        
        // Disable the save button and show loading state
        saveButton.prop('disabled', true)
            .html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...')
            .addClass('btn-loading');
        
        // Disable all form inputs during submission
        $(this).find('input, select, textarea').prop('disabled', true);
        
        // Submit the form via AJAX
        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: $(this).serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.overlap_detected) {
                    // Show browser confirmation dialog for overlap
                    const userConfirmed = confirm(response.overlap_message);
                    
                    if (userConfirmed) {
                        // User wants to proceed, save with force flag
                        submitFormWithForceFlag();
                    } else {
                        // User cancelled, reset button state
                        resetFormState();
                    }
                    return;
                }
                
                if (response.success) {
                    // Show success message
                    $('#flightDetailsContent').prepend(`
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="fa fa-check mr-2"></i>${response.message || 'Flight details updated successfully!'}
                        </div>
                    `);
                    
                    // Reload the page after a short delay to show updated bay assignments
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    $('#flightDetailsContent').prepend(`
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="fa fa-exclamation-triangle mr-2"></i>${response.message || 'Error saving flight details.'}
                        </div>
                    `);
                    resetFormState();
                }
            },
            error: function(xhr, status, error) {
                const errorMsg = xhr.responseJSON?.message || 'Error saving flight details. Please try again.';
                $('#flightDetailsContent').prepend(`
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <i class="fa fa-exclamation-triangle mr-2"></i>${errorMsg}
                    </div>
                `);
                console.error('Error saving flight details:', error);
                resetFormState();
            }
        });
    });

    // Function to submit form with force flag
    function submitFormWithForceFlag() {
        const form = $('#flightDetailsForm');
        const formData = form.serialize() + '&force_save=true';
        
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#flightDetailsContent').prepend(`
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="fa fa-check mr-2"></i>${response.message || 'Flight details updated successfully!'}
                        </div>
                    `);
                    
                    // Reload the page after a short delay to show updated bay assignments
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    $('#flightDetailsContent').prepend(`
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="fa fa-exclamation-triangle mr-2"></i>${response.message || 'Error saving flight details.'}
                        </div>
                    `);
                    resetFormState();
                }
            },
            error: function(xhr, status, error) {
                const errorMsg = xhr.responseJSON?.message || 'Error saving flight details. Please try again.';
                $('#flightDetailsContent').prepend(`
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <i class="fa fa-exclamation-triangle mr-2"></i>${errorMsg}
                    </div>
                `);
                console.error('Error saving flight details:', error);
                resetFormState();
            }
        });
    }

    // Function to reset form state
    function resetFormState() {
        const saveButton = $('#saveFlightDetails');
        const form = $('#flightDetailsForm');
        
        // Re-enable the save button and restore original text
        saveButton.prop('disabled', false)
            .html('Save Changes')
            .removeClass('btn-loading');
        
        // Re-enable all form inputs
        form.find('input, select, textarea').prop('disabled', false);
    }
});
</script>
