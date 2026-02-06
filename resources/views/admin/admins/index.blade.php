@extends('admin.layouts.app')

@section('title', 'Manage Admins')

@section('content')

<h1 style="margin-bottom:24px;">Manage Admins</h1>

@if(session('success'))
    <div class="card" style="background:#ecfdf5;color:#065f46;margin-bottom:24px;">
        {{ session('success') }}
    </div>
@endif

<div class="card">
    <h3 style="margin-bottom:16px;">Create Admin</h3>

    <form method="POST" action="{{ route('admin.admins') }}">
        @csrf

        <input type="text" name="name" placeholder="Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>

        <button>Create Admin</button>
    </form>
</div>

<h2 style="margin:40px 0 16px;">Existing Admins</h2>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th width="220">Actions</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($admins as $admin)
                <tr>
                    <td>{{ $admin->name }}</td>
                    <td>{{ $admin->email }}</td>
                    <td>{{ ucfirst($admin->role) }}</td>
                    <td>
                        {{ $admin->is_active ? 'Active' : 'Disabled' }}
                    </td>
                    <td>
                        @if ($admin->role !== 'super_admin')
                            <form method="POST" action="/admin/admins/{{ $admin->id }}/toggle" style="display:inline;">
                                @csrf
                                <button class="outline">
                                    {{ $admin->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </form>

                            <form method="POST" action="/admin/admins/{{ $admin->id }}" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button class="danger">
                                    Delete
                                </button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
