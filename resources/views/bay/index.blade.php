@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h3>{{ $airport->name }} [{{ $airport->icao }} | {{ $airport->iata }}] - Bay Management</h3>
    <hr>
    @include('layouts.alert')
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('admin.airports.index') }}" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Back to Airports
        </a>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createBayModal">
            <i class="fa fa-plus"></i> Add New Bay
        </button>
    </div>

    @if($bays->isEmpty())
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> No bays found for this airport. Click "Add New Bay" to create one.
        </div>
    @else
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Bay Name</th>
                    <th colspan="2">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bays as $bay)
                    <tr>
                        <td>{{ $bay->name }}</td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm edit-bay" 
                                    data-bay-id="{{ $bay->id }}" 
                                    data-bay-name="{{ $bay->name }}"
                                    data-toggle="modal" 
                                    data-target="#editBayModal">
                                <i class="fa fa-edit"></i> Edit
                            </button>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm delete-bay" 
                                    data-bay-id="{{ $bay->id }}" 
                                    data-bay-name="{{ $bay->name }}">
                                <i class="fa fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<!-- Create Bay Modal -->
<div class="modal fade" id="createBayModal" tabindex="-1" role="dialog" aria-labelledby="createBayModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createBayModalLabel">Add New Bay</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createBayForm">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="create_bay_name">Bay Name</label>
                        <input type="text" class="form-control" id="create_bay_name" name="name" required maxlength="255">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-plus"></i> Create Bay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bay Modal -->
<div class="modal fade" id="editBayModal" tabindex="-1" role="dialog" aria-labelledby="editBayModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBayModalLabel">Edit Bay</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editBayForm">
                @csrf
                @method('PUT')
                <input type="hidden" id="edit_bay_id" name="bay_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_bay_name">Bay Name</label>
                        <input type="text" class="form-control" id="edit_bay_name" name="name" required maxlength="255">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-check"></i> Update Bay
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // CSRF token setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Create Bay Form
    $('#createBayForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            name: $('#create_bay_name').val(),
            _token: $('input[name="_token"]').val()
        };

        $.ajax({
            url: '{{ route("admin.airports.bays.store", $airport) }}',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    if (errors.name) {
                        $('#create_bay_name').addClass('is-invalid');
                        $('#create_bay_name').siblings('.invalid-feedback').text(errors.name[0]);
                    }
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while creating the bay.',
                        icon: 'error'
                    });
                }
            }
        });
    });

    // Clear form validation on modal hide
    $('#createBayModal').on('hidden.bs.modal', function() {
        $('#createBayForm')[0].reset();
        $('.form-control').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    });

    // Edit Bay
    $('.edit-bay').on('click', function() {
        const bayId = $(this).data('bay-id');
        const bayName = $(this).data('bay-name');
        
        $('#edit_bay_id').val(bayId);
        $('#edit_bay_name').val(bayName);
    });

    // Edit Bay Form
    $('#editBayForm').on('submit', function(e) {
        e.preventDefault();
        
        const bayId = $('#edit_bay_id').val();
        const formData = {
            name: $('#edit_bay_name').val(),
            _token: $('input[name="_token"]').val(),
            _method: 'PUT'
        };

        $.ajax({
            url: `{{ route("admin.airports.bays.index", $airport) }}/${bayId}`,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    if (errors.name) {
                        $('#edit_bay_name').addClass('is-invalid');
                        $('#edit_bay_name').siblings('.invalid-feedback').text(errors.name[0]);
                    }
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while updating the bay.',
                        icon: 'error'
                    });
                }
            }
        });
    });

    // Clear edit form validation on modal hide
    $('#editBayModal').on('hidden.bs.modal', function() {
        $('.form-control').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    });

    // Delete Bay
    $('.delete-bay').on('click', function(e) {
        e.preventDefault();
        
        const bayId = $(this).data('bay-id');
        const bayName = $(this).data('bay-name');
        
        Swal.fire({
            title: 'Are you sure?',
            text: `Are you sure you want to delete bay "${bayName}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ route("admin.airports.bays.index", $airport) }}/${bayId}`,
                    method: 'DELETE',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Deleted!',
                                text: response.message,
                                icon: 'success'
                            }).then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred while deleting the bay.',
                            icon: 'error'
                        });
                    }
                });
            }
        });
    });
});
</script>
@endpush
@endsection
