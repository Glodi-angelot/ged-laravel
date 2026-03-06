<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $active = $request->input('active'); // '' | '1' | '0'

        $services = Service::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->when($active !== null && $active !== '', function ($query) use ($active) {
                $query->where('is_active', (bool) ((int) $active));
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:services,code'],
            'is_active' => ['nullable'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        // ✅ créer le service et garder la variable
        $service = Service::create($data);

        // ✅ audit
        audit('created', 'Service', $service->id, null, $service->toArray());

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Service créé avec succès.');
    }

    public function edit(Service $service)
    {
        return view('admin.services.edit', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:services,code,' . $service->id],
            'is_active' => ['nullable'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        // ✅ old values avant modification
        $old = $service->toArray();

        $service->update($data);

        // ✅ audit
        audit('updated', 'Service', $service->id, $old, $service->toArray());

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Service mis à jour.');
    }

    public function destroy(Service $service)
    {
        // ✅ old values avant suppression
        $old = $service->toArray();

        $id = $service->id; // garde l'id avant delete
        $service->delete();

        // ✅ audit (entity_id toujours disponible)
        audit('deleted', 'Service', $id, $old, null);

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Service supprimé.');
    }
}