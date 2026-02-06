<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminManagementController extends Controller
{
    public function index()
    {
        return view('admin.admins.index', [
            'admins' => Admin::all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:admins',
            'password' => 'required|min:8',
        ]);

        Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
        ]);

        return redirect()->back()->with('success', 'Admin created');
    }

    public function updatePassword(Request $request, Admin $admin)
    {
        $request->validate([
            'password' => 'required|min:8',
        ]);

        $admin->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'Password updated');
    }
    public function destroy($id)
    {
        $admin = Admin::findOrFail($id);

        // Prevent deleting super admin
        if ($admin->role === 'super_admin') {
            return redirect()
                ->back()
                ->with('success', 'Super admin cannot be deleted.');
        }

        $admin->delete();

        return redirect()
            ->back()
            ->with('success', 'Admin deleted successfully.');
    }
}
