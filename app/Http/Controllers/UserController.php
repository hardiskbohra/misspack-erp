<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** List all users with optional search */
    public function index(Request $request)
    {
        $search  = $request->get('search', '');
        $perPage = $request->get('per_page', 10);

        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name',        'like', "%{$search}%")
                  ->orWhere('email',       'like', "%{$search}%")
                  ->orWhere('mobile',      'like', "%{$search}%")
                  ->orWhere('department',  'like', "%{$search}%")
                  ->orWhere('designation', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('id')->paginate($perPage)->withQueryString();

        return view('users.index', compact('users', 'search', 'perPage'));
    }

    /** Return user data as JSON for the edit modal */
    public function getData(User $user)
    {
        return response()->json([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'mobile'      => $user->mobile,
            'department'  => $user->department,
            'designation' => $user->designation,
            'avatar'      => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'verified'    => !is_null($user->email_verified_at),
        ]);
    }

    /** Store new user */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|string|min:6|confirmed',
            'mobile'      => 'nullable|string|max:20',
            'department'  => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'avatar'      => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $data             = $request->only(['name', 'email', 'mobile', 'department', 'designation']);
        $data['password'] = Hash::make($request->password);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        User::create($data);

        return back()->with('success', 'User added successfully!');
    }

    /** Update existing user */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password'    => 'nullable|string|min:6|confirmed',
            'mobile'      => 'nullable|string|max:20',
            'department'  => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'avatar'      => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $data = $request->only(['name', 'email', 'mobile', 'department', 'designation']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->boolean('remove_avatar') && $user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $data['avatar'] = null;
        }

        $user->update($data);

        return back()->with('success', 'User updated successfully!');
    }

    /** Delete user */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully!');
    }
}
