<div {{ $refreshInSeconds ? "wire:poll.{$refreshInSeconds}s" : '' }}>
    <h3>{{ $event->name }} |
        @if($filter == 'my-bookings')
            My Bookings
        @else
            {{ $filter ? ucfirst($filter) : 'Slot Table' }}
        @endif
    </h3>
    <hr>
    <p>
        @if($event->hasOrderButtons())
            <button wire:model="filter" wire:click="filter(null)"
                class="btn {{ !$filter ? 'btn-success' : 'btn-primary' }}">Show
                All</button>&nbsp;
            <button wire:model="filter" wire:click="filter('departures')"
                class="btn {{ $filter == 'departures' ? 'btn-success' : 'btn-primary' }}">Show
                Departures</button>&nbsp;
            <button wire:model="filter" wire:click="filter('arrivals')"
                class="btn {{ $filter == 'arrivals' ? 'btn-success' : 'btn-primary' }}">Show
                Arrivals</button>&nbsp;
        @endif
        @if(auth()->check())
            <button wire:model="filter" wire:click="filter('my-bookings')"
                class="btn {{ $filter == 'my-bookings' ? 'btn-success' : 'btn-primary' }}">My
                Bookings</button>&nbsp;
        @endif
        @if(auth()->check() && auth()->user()->isAdmin && $event->endBooking >= now())
            @push('scripts')
                <script>
                    $('.delete-booking').on('click', function (e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Are you sure',
                            text: 'Are you sure you want to remove this booking?',
                            icon: 'warning',
                            showCancelButton: true,
                        }).then((result) => {
                            if (result.value) {
                                Swal.fire('Deleting booking...');
                                Swal.showLoading();
                                $(this).closest('form').submit();
                            }
                        });
                    });
                </script>
            @endpush
            <a href="{{ route('admin.bookings.create',$event) }}" class="btn btn-primary"><i class="fa fa-plus"></i>
                Add
                Booking</a>&nbsp;
            <a href="{{ route('admin.bookings.create',$event) }}/bulk" class="btn btn-primary"><i
                    class="fa fa-plus"></i>
                Add
                Timeslots</a>&nbsp;
        @endif

        @push('scripts')
            <script>
                $(document).ready(function() {
                    // Function to extract hour from time string (e.g. "12:34" -> "12")
                    function extractHour(timeString) {
                        if (!timeString) return '';
                        const time = timeString.toString().trim();
                        const match = time.match(/(\d{1,2}):?\d{0,2}/);
                        return match ? match[1].padStart(2, '0') : '';
                    }

                    // Function to filter table rows
                    function filterTable() {
                        const filters = {
                            std: $('#filter-std').val().trim(),
                            sta: $('#filter-sta').val().trim(),
                            flight: $('#filter-flight').val().toLowerCase().trim(),
                            from: $('#filter-from').val().toLowerCase().trim(),
                            to: $('#filter-to').val().toLowerCase().trim(),
                            aircraft: $('#filter-aircraft').val().toLowerCase().trim()
                        };

                        let visibleRows = 0;
                        const totalRows = $('#bookings-table tbody tr').length;

                        $('#bookings-table tbody tr').each(function() {
                            const row = $(this);
                            let showRow = true;

                            // Check STD filter (hour range)
                            if (filters.std && showRow) {
                                const stdData = row.data('std') || '';
                                const stdHour = extractHour(stdData);
                                showRow = stdHour === filters.std;
                            }

                            // Check STA filter (hour range)
                            if (filters.sta && showRow) {
                                const staData = row.data('sta') || '';
                                const staHour = extractHour(staData);
                                showRow = staHour === filters.sta;
                            }

                            // Check Flight filter
                            if (filters.flight && showRow) {
                                const flightData = row.data('flight') || '';
                                showRow = flightData.toString().toLowerCase().includes(filters.flight);
                            }

                            // Check From filter
                            if (filters.from && showRow) {
                                const fromData = row.data('from') || '';
                                const fromNameData = row.data('from-name') || '';
                                showRow = fromData.toString().toLowerCase().includes(filters.from) ||
                                         fromNameData.toString().toLowerCase().includes(filters.from);
                            }

                            // Check To filter
                            if (filters.to && showRow) {
                                const toData = row.data('to') || '';
                                const toNameData = row.data('to-name') || '';
                                showRow = toData.toString().toLowerCase().includes(filters.to) ||
                                         toNameData.toString().toLowerCase().includes(filters.to);
                            }

                            // Check Aircraft filter
                            if (filters.aircraft && showRow) {
                                const aircraftData = row.data('aircraft') || '';
                                showRow = aircraftData.toString().toLowerCase().includes(filters.aircraft);
                            }

                            // Show/hide row
                            if (showRow) {
                                row.show();
                                visibleRows++;
                            } else {
                                row.hide();
                            }
                        });

                        // Update results counter
                        updateFilterCounter(visibleRows, totalRows);
                    }

                    // Function to update the filter results counter
                    function updateFilterCounter(visible, total) {
                        const hasFilters = $('#filter-std').val() || $('#filter-sta').val() ||
                                         $('#filter-flight').val() || $('#filter-from').val() ||
                                         $('#filter-to').val() || $('#filter-aircraft').val();

                        if (hasFilters) {
                            $('#filter-results-count').text(`Showing ${visible} of ${total} flights`);
                        } else {
                            $('#filter-results-count').text('');
                        }
                    }

                    // Function to clear all filters
                    function clearFilters() {
                        $('#filter-std, #filter-sta, #filter-flight, #filter-from, #filter-to, #filter-aircraft').val('');
                        $('#bookings-table tbody tr').show();
                        $('#filter-results-count').text('');
                        // Don't collapse the filter section - keep it open
                    }

                    // Bind filter events
                    $('#filter-std, #filter-sta, #filter-flight, #filter-from, #filter-to, #filter-aircraft').on('input keyup change', function() {
                        filterTable();
                    });

                    // Bind clear filters event
                    $('#clear-filters').on('click', function(e) {
                        e.preventDefault(); // Prevent default button behavior
                        e.stopPropagation(); // Prevent event bubbling to parent elements
                        clearFilters();
                    });

                    // Handle collapsible filter section
                    $('#filter-collapse').on('show.bs.collapse', function () {
                        $('#filter-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');
                    });

                    $('#filter-collapse').on('hide.bs.collapse', function () {
                        $('#filter-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                    });

                    // Make entire header clickable for collapse
                    $('.card-header[data-toggle="collapse"]').on('click', function(e) {
                        $($(this).data('target')).collapse('toggle');
                    });

                    // Initial counter update
                    updateFilterCounter($('#bookings-table tbody tr').length, $('#bookings-table tbody tr').length);
                });
            </script>
        @endpush
    </p>
    @include('layouts.alert')

    @if($event->event_type_id != \App\Enums\EventType::MULTIFLIGHTS->value)
        <div class="mb-3 mt-3" id="booking-filters">
            <div class="card">
                <div class="card-header" style="cursor: pointer;" data-toggle="collapse" data-target="#filter-collapse" aria-expanded="true" aria-controls="filter-collapse">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-filter"></i> Filter Schedules
                        <small class="text-muted ml-2">
                            <i class="fas fa-chevron-down" id="filter-chevron"></i>
                        </small>
                    </h5>
                </div>
                <div class="collapse show" id="filter-collapse">
                    <div class="card-body">
                        <div class="row">
                            @if($event->uses_times)
                                <div class="col-lg-2 col-md-3 col-sm-6 mb-3">
                                    <label for="filter-std" class="form-label">STD Hour</label>
                                    <select class="form-control form-control-sm" id="filter-std">
                                        <option value="">All Hours</option>
                                        <option value="00">00:00 - 01:00</option>
                                        <option value="01">01:00 - 02:00</option>
                                        <option value="02">02:00 - 03:00</option>
                                        <option value="03">03:00 - 04:00</option>
                                        <option value="04">04:00 - 05:00</option>
                                        <option value="05">05:00 - 06:00</option>
                                        <option value="06">06:00 - 07:00</option>
                                        <option value="07">07:00 - 08:00</option>
                                        <option value="08">08:00 - 09:00</option>
                                        <option value="09">09:00 - 10:00</option>
                                        <option value="10">10:00 - 11:00</option>
                                        <option value="11">11:00 - 12:00</option>
                                        <option value="12">12:00 - 13:00</option>
                                        <option value="13">13:00 - 14:00</option>
                                        <option value="14">14:00 - 15:00</option>
                                        <option value="15">15:00 - 16:00</option>
                                        <option value="16">16:00 - 17:00</option>
                                        <option value="17">17:00 - 18:00</option>
                                        <option value="18">18:00 - 19:00</option>
                                        <option value="19">19:00 - 20:00</option>
                                        <option value="20">20:00 - 21:00</option>
                                        <option value="21">21:00 - 22:00</option>
                                        <option value="22">22:00 - 23:00</option>
                                        <option value="23">23:00 - 00:00</option>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-3 col-sm-6 mb-3 pr-1">
                                    <label for="filter-sta" class="form-label">STA Hour</label>
                                    <select class="form-control form-control-sm" id="filter-sta">
                                        <option value="">All Hours</option>
                                        <option value="00">00:00 - 01:00</option>
                                        <option value="01">01:00 - 02:00</option>
                                        <option value="02">02:00 - 03:00</option>
                                        <option value="03">03:00 - 04:00</option>
                                        <option value="04">04:00 - 05:00</option>
                                        <option value="05">05:00 - 06:00</option>
                                        <option value="06">06:00 - 07:00</option>
                                        <option value="07">07:00 - 08:00</option>
                                        <option value="08">08:00 - 09:00</option>
                                        <option value="09">09:00 - 10:00</option>
                                        <option value="10">10:00 - 11:00</option>
                                        <option value="11">11:00 - 12:00</option>
                                        <option value="12">12:00 - 13:00</option>
                                        <option value="13">13:00 - 14:00</option>
                                        <option value="14">14:00 - 15:00</option>
                                        <option value="15">15:00 - 16:00</option>
                                        <option value="16">16:00 - 17:00</option>
                                        <option value="17">17:00 - 18:00</option>
                                        <option value="18">18:00 - 19:00</option>
                                        <option value="19">19:00 - 20:00</option>
                                        <option value="20">20:00 - 21:00</option>
                                        <option value="21">21:00 - 22:00</option>
                                        <option value="22">22:00 - 23:00</option>
                                        <option value="23">23:00 - 00:00</option>
                                    </select>
                                </div>
                            @endif
                            <div class="col-lg-2 col-md-3 col-sm-6 mb-3 pr-1">
                                <label for="filter-flight" class="form-label">Flight</label>
                                <input type="text" class="form-control form-control-sm" id="filter-flight" placeholder="e.g. SIA956">
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6 mb-3 pr-1">
                                <label for="filter-from" class="form-label">From</label>
                                <input type="text" class="form-control form-control-sm" id="filter-from" placeholder="e.g. WSSS">
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6 mb-3 pr-1">
                                <label for="filter-to" class="form-label">To</label>
                                <input type="text" class="form-control form-control-sm" id="filter-to" placeholder="e.g. WIII">
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6 mb-3 pr-1">
                                <label for="filter-aircraft" class="form-label">Aircraft</label>
                                <input type="text" class="form-control form-control-sm" id="filter-aircraft" placeholder="e.g. A359">
                            </div>
                            <div class="col-lg-2 col-md-3 col-sm-6 mb-3 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-filters">
                                    <i class="fas fa-times"></i> Clear Filters
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted">
                                    <span id="filter-results-count"></span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($event->startBooking <= now() || auth()->check() && auth()->user()->isAdmin)
        Flights available: {{ strval($total - $booked) }} / {{ $total }}
        <table class="table table-hover table-responsive" id="bookings-table">
            @if($event->event_type_id == \App\Enums\EventType::MULTIFLIGHTS->value)
                @include('booking.overview.multiflights')
            @else
                @include('booking.overview.default')
            @endif

        </table>
    @else
        <h3>Bookings will be available at <strong>{{ $event->startBooking->format('d-m-Y H:i') }}z</strong></h3><br>
    @endif
</div>
