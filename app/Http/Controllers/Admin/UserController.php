<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $role = $request->string('role')->toString(); // admin|user|null

        $users = User::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when(in_array($role, ['admin','user'], true), function ($query) use ($role) {
                // Spatie roles
                $query->whereHas('roles', fn($r) => $r->where('name', $role));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Mini-stats (sur tout le système, pas juste la page)
        $total = User::count();
        $adminsCount = User::whereHas('roles', fn($r) => $r->where('name', 'admin'))->count();
        $usersCount = max($total - $adminsCount, 0);

        return view('admin.users.index', compact('users', 'q', 'role', 'total', 'adminsCount', 'usersCount'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:6','confirmed'],
            'role' => ['required','in:admin,user'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles([$data['role']]);

        $new = $user->toArray();
        unset($new['password'], $new['remember_token']);
        $new['role'] = $data['role'];
        audit('created', 'User', $user->id, null, $new);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé avec succès.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email,'.$user->id],
            'role' => ['required','in:admin,user'],
            'password' => ['nullable','string','min:6','confirmed'],
        ]);

        $old = $user->toArray();
        unset($old['password'], $old['remember_token']);
        $old['role'] = $user->getRoleNames()->first() ?? null;

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => !empty($data['password']) ? Hash::make($data['password']) : $user->password,
        ]);

        $user->syncRoles([$data['role']]);

        $new = $user->fresh()->toArray();
        unset($new['password'], $new['remember_token']);
        $new['role'] = $data['role'];

        audit('updated', 'User', $user->id, $old, $new);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->with('success', "Impossible de supprimer l'utilisateur connecté.");
        }

        $old = $user->toArray();
        unset($old['password'], $old['remember_token']);
        $old['role'] = $user->getRoleNames()->first() ?? null;

        $id = $user->id;
        $user->delete();

        audit('deleted', 'User', $id, $old, null);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur supprimé.');
    }
}