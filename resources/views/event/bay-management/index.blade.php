@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="mb-3">
                <h3><i class="fa fa-building mr-2"></i>Bay Assignment Management - {{ $event->name }}</h3>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3 gate-filter-card">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <label for="gateFilter" class="form-label mb-0">
                        <i class="fa fa-filter mr-1"></i>Filter by Gate:
                    </label>
                </div>
                <div class="col-md-3">
                    <input type="text" id="gateFilter" class="form-control" placeholder="Enter gate name (e.g., A, C1, etc.)">
                </div>
                <div class="col-md-3">
                    <label for="aircraftFilter" class="form-label mb-0">
                        <i class="fa fa-plane mr-1"></i>Filter by Aircraft Type:
                    </label>
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
                        <span class="badge badge-info">Departure</span>
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
                                <tr class="{{ $subRow > 0 ? 'bay-sub-row' : 'bay-main-row' }}" data-gate="{{ $bay->name }}">
                                    @if($subRow === 0)
                                        <td class="bg-dark text-white font-weight-bold sticky-gate-cell"
                                            rowspan="{{ $maxOverlaps }}">
                                            {{ $bay->name }}
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
                                                $cssClass = $assignment['type'] === 'departure' ? 'bg-info text-white' : 'bg-warning text-white';
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
        z-index: 1;
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

    /* Clean up since we're using real table rows now */
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize table event handlers
    initializeTableEventHandlers();

    // Initialize gate filter
    initializeGateFilter();

    // Handle save button click
    $('#saveFlightDetails').on('click', function() {
        saveFlightDetails();
    });

    function initializeTableEventHandlers() {
        $(document).off('click', '.flight-assignment-cell').on('click', '.flight-assignment-cell', function(e) {
            e.stopPropagation();

            // Focus tracking
            $('.flight-assignment-cell').removeClass('last-clicked');
            $(this).addClass('last-clicked');

            // Get flight data from the cell itself
            const flightId = $(this).data('flight-id');
            const assignmentType = $(this).data('assignment-type');

            if (flightId) {
                loadFlightDetails(flightId, assignmentType);
            }
        });
    }

    function initializeGateFilter() {
        $('#gateFilter').on('input', function() {
            applyFilters();
        });

        $('#aircraftFilter').on('input', function() {
            applyFilters();
        });
    }

    function applyFilters() {
        const gateFilterText = $('#gateFilter').val().trim();
        const aircraftFilterText = $('#aircraftFilter').val().trim();

        const $tableRows = $('.gate-table tbody tr');

        $tableRows.each(function() {
            const $row = $(this);
            const gateName = $row.data('gate');
            let showRow = true;

            // Apply gate filter - this controls row visibility
            if (gateFilterText) {
                if (!gateName || typeof gateName !== 'string' || !gateName.toLowerCase().startsWith(gateFilterText.toLowerCase())) {
                    showRow = false;
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
                        const aircraftType = $cell.data('aircraft-type');

                        if (aircraftType && aircraftType.toLowerCase().includes(aircraftFilterText.toLowerCase())) {
                            // Show cells that match the aircraft filter with full opacity
                            $cell.css('opacity', '1');
                        } else {
                            // Make non-matching cells very transparent
                            $cell.css('opacity', '0.1');
                        }
                    });
                } else {
                    // If no aircraft filter, show all flight cells with full opacity
                    $row.find('.flight-assignment-cell').css('opacity', '1');
                }
            } else {
                // Hide the entire row if gate filter doesn't match
                $row.hide();
            }
        });
    }

    // Handle close button click
    $('#closeModalBtn').on('click', function() {
        closeModal();
    });

    // Handle X button click
    $('#modalCloseX').on('click', function() {
        closeModal();
    });

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

        // Load flight details via AJAX
        $.ajax({
            url: '{{ route("admin.events.bay-management.flight-details", [$event, "__FLIGHT_ID__"]) }}'.replace('__FLIGHT_ID__', flightId),
            method: 'GET',
            data: { assignment_type: assignmentType },
            success: function(response) {
                $('#flightDetailsContent').html(response.html);
                $('#saveFlightDetails').show();
            },
            error: function(xhr, status, error) {
                $('#flightDetailsContent').html(`
                    <div class="alert alert-danger">
                        <i class="fa fa-exclamation-triangle mr-2"></i>
                        Error loading flight details. Please try again.
                    </div>
                `);
                console.error('Error loading flight details:', error);
            }
        });
    }

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
        $('#flightDetailsContent').prepend(messageHtml);

        // Auto-dismiss success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $('.modal-message').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    function reloadBayMatrix() {
        // Show loading indicator
        $('.table-container').prepend(`
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
                    $('.table-container').replaceWith($newTable);

                    // Re-bind event handlers for the new table
                    initializeTableEventHandlers();

                    // Re-apply current filters if any are selected
                    applyFilters();
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

});
</script>
@endpush
