{{--
    Bay Matrix Table Partial
    Variables: $bays, $timeSlots, $bayUsage, $timeRange, $blockedBayIds, $event
--}}
<div class="card mb-3" id="bayMatrixCard">
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
                            $bayData = $bayUsage[$bay->id] ?? [];

                            // Calculate max rows needed for this bay
                            $maxOverlaps = 1;
                            foreach ($bayData as $assignments) {
                                if (is_array($assignments)) {
                                    $maxOverlaps = max($maxOverlaps, count($assignments));
                                }
                            }

                            $isBayBlocked = in_array($bay->id, $blockedBayIds);
                        @endphp

                        @for($subRow = 0; $subRow < $maxOverlaps; $subRow++)
                            <tr class="{{ $subRow > 0 ? 'bay-sub-row' : 'bay-main-row' }} {{ $isBayBlocked ? 'bay-blocked-row' : '' }}"
                                data-gate="{{ $bay->name }}"
                                data-bay-id="{{ $bay->id }}">

                                @if($subRow === 0)
                                    <td class="bg-dark text-white font-weight-bold sticky-gate-cell {{ $isBayBlocked ? 'bay-blocked' : '' }}"
                                        rowspan="{{ $maxOverlaps }}">
                                        <div class="d-flex align-items-center">
                                            <span>{{ $bay->name }}</span>
                                            @if($isBayBlocked)
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
                                        @continue
                                    @endif

                                    @php
                                        $timeKey = $timeSlot->format('Y-m-d H:i');
                                        $assignments = $bayData[$timeKey] ?? [];

                                        $assignment = null;
                                        if (is_array($assignments) && isset($assignments[$subRow]) && $assignments[$subRow] !== null) {
                                            $assignment = $assignments[$subRow];
                                        }
                                    @endphp

                                    @if($assignment)
                                        @php
                                            $cssClass = 'bg-warning text-white';
                                            if ($assignment['type'] === 'departure') {
                                                $cssClass = 'bg-info text-white';
                                            } elseif ($assignment['type'] === 'adhoc') {
                                                $cssClass = $assignment['flight_type'] === 'departure'
                                                    ? 'bg-info text-white adhoc-striped'
                                                    : 'bg-warning text-white adhoc-striped';
                                            }
                                            $colspan = $assignment['colspan'] ?? 1;

                                            if ($colspan > 1) {
                                                for ($i = 1; $i < $colspan; $i++) {
                                                    $skipCells[] = $slotIndex + $i;
                                                }
                                            }
                                        @endphp

                                        <td class="time-slot {{ $cssClass }} flight-assignment-cell"
                                            @if($colspan > 1) colspan="{{ $colspan }}" @endif
                                            data-flight-id="{{ $assignment['flight']->id }}"
                                            data-assignment-type="{{ $assignment['type'] }}"
                                            data-aircraft-type="{{ $assignment['aircraft_type'] ?? 'Unknown' }}"
                                            data-bay-id="{{ $bay->id }}"
                                            data-time-slot="{{ $timeSlot->format('H:i') }}"
                                            data-original-bay="{{ $bay->name }}"
                                            @if($assignment['type'] === 'adhoc')
                                                data-callsign="{{ $assignment['flight']->callsign }}"
                                                data-ac-type="{{ $assignment['flight']->acType }}"
                                                data-dep="{{ $assignment['flight']->dep }}"
                                                data-arr="{{ $assignment['flight']->arr }}"
                                                data-std="{{ $assignment['flight']->std ? $assignment['flight']->std->format('Y-m-d H:i:s') : '' }}"
                                                data-sta="{{ $assignment['flight']->sta ? $assignment['flight']->sta->format('Y-m-d H:i:s') : '' }}"
                                            @endif
                                            style="cursor: grab;"
                                            title="Drag to move flight or click to view/edit details"
                                            tabindex="0"
                                            role="button"
                                            aria-label="Drag to move or view flight details for {{ $assignment['callsign'] }}"
                                            draggable="true">

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
