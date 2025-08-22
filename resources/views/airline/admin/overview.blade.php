@extends('layouts.app')

@section('content')
    <h3>Airlines Overview</h3>
    <hr>
    @include('layouts.alert')
    @push('scripts')
        <script>
            $('.delete-airline').on('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure',
                    text: 'Are you sure you want to remove this airline?',
                    icon: 'warning',
                    showCancelButton: true,
                }).then((result) => {
                    if (result.value) {
                        Swal.fire('Deleting airline...');
                        Swal.showLoading();
                        $(this).closest('form').submit();
                    }
                });
            });
        </script>
    @endpush
    <div class="d-flex flex-row flex-wrap">
        <a href="{{ route('admin.airlines.create') }}" class="btn btn-primary m-1"><i class="fa fa-plus"></i> Add new
            Airline</a>
    </div>
    <table class="table table-hover">
        <thead>
            <tr>
                <th scope="row">ICAO</th>
                <th scope="row">Name</th>
                <th scope="row">Logo</th>
                <th scope="row" colspan="2">Actions</th>
            </tr>
        </thead>
        @foreach ($airlines as $airline)
            <tr>
                <td><a href="{{ route('admin.airlines.show', $airline) }}">{{ $airline->icao }}</a></td>
                <td><a href="{{ route('admin.airlines.show', $airline) }}">{{ $airline->name }}</a></td>
                <td>
                    @if($airline->logo_url)
                        <img src="{{ $airline->logo_url }}" alt="{{ $airline->name }} logo" style="max-height: 30px; max-width: 100px;">
                    @else
                        <span class="text-muted">No logo</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.airlines.edit', $airline) }}">
                        <button class="btn btn-primary">
                            <i class="fa fa-edit"></i> Edit Airline
                        </button>
                    </a>
                </td>
                <td>
                    <form action="{{ route('admin.airlines.destroy', $airline) }}" method="post">
                        @method('DELETE')
                        <button class="btn btn-danger delete-airline"><i class="fa fa-trash"></i> Remove Airline
                        </button>
                        @csrf
                    </form>
                </td>
            </tr>
        @endforeach
        {{ $airlines->links() }}
    </table>
    {{ $airlines->links() }}
@endsection
