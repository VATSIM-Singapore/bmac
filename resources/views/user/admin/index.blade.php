@extends('layouts.app')

@section('content')
    <h3>Users</h3>
    <hr>
    @include('layouts.alert')
    @push('scripts')
        <script>
            $('.toggle-admin').on('click', function(e) {
                e.preventDefault();
                var form = $(this).closest('form');
                var action = $(this).data('action');
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Are you sure you want to ' + action + ' admin access for this user?',
                    icon: 'warning',
                    showCancelButton: true,
                }).then((result) => {
                    if (result.value) {
                        form.submit();
                    }
                });
            });
        </script>
    @endpush
    <table class="table table-hover">
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">First Name</th>
                <th scope="col">Last Name</th>
                <th scope="col">Email</th>
                <th scope="col">Admin</th>
                @if ($isSuperAdmin)
                    <th scope="col">Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name_first }}</td>
                    <td>{{ $user->name_last }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @if ($user->isSuperAdmin)
                            <span class="badge badge-dark">Super Admin</span>
                        @elseif ($user->isAdmin)
                            <span class="badge badge-success">Admin</span>
                        @else
                            <span class="badge badge-secondary">-</span>
                        @endif
                    </td>
                    @if ($isSuperAdmin)
                        <td>
                            @if (!$user->isSuperAdmin && $user->id !== auth()->id())
                                <form action="{{ route('admin.users.toggleAdmin', $user) }}" method="post">
                                    @csrf
                                    @if ($user->isAdmin)
                                        <button type="button" class="btn btn-warning btn-sm toggle-admin" data-action="revoke">
                                            <i class="fa fa-times"></i> Revoke Admin
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-success btn-sm toggle-admin" data-action="grant">
                                            <i class="fa fa-check"></i> Grant Admin
                                        </button>
                                    @endif
                                </form>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $users->links() }}
@endsection
