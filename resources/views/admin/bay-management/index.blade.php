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
                        <span class="badge badge-success mr-1">Arrival</span>
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
                            <tr>
                                <td class="bg-dark text-white font-weight-bold sticky-gate-cell">{{ $bay->name }}</td>
                                @foreach($timeSlots as $timeSlot)
                                    @php
                                        $timeKey = $timeSlot->format('Y-m-d H:i');
                                        $assignments = $bayUsage[$bay->id][$timeKey] ?? [];
                                        $hasAssignment = !empty($assignments) && !isset($assignments[0]['skip']);
                                        $shouldSkip = !empty($assignments) && isset($assignments[0]['skip']);
                                    @endphp

                                    @if($shouldSkip)
                                        {{-- Skip this cell as it's part of a colspan --}}
                                    @elseif($hasAssignment)
                                        @php
                                            $assignment = $assignments[0]; // Take first assignment
                                            $cssClass = $assignment['type'] === 'departure' ? 'bg-info text-white' : 'bg-success text-white';
                                        @endphp
                                        <td class="time-slot {{ $cssClass }}"
                                            @if($assignment['colspan'] > 1)
                                                colspan="{{ $assignment['colspan'] }}"
                                            @endif>
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
                        @endforeach
                    </tbody>
                </table>
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
        z-index: 11;
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
</style>
@endpush
