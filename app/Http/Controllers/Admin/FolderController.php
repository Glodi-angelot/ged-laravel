<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FolderController extends Controller
{
    public function index(Request $request)
    {
        $serviceId = $request->integer('service_id');
        $parentId  = $request->integer('parent_id');
        $q         = $request->string('q')->toString();

        $folders = Folder::query()
            ->with(['service', 'parent'])
            ->when($serviceId, fn($query) => $query->where('service_id', $serviceId))
            ->when($parentId,
                fn($query) => $query->where('parent_id', $parentId),
                fn($query) => $query->whereNull('parent_id')
            )
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $services = Service::query()->orderBy('name')->get();
        $currentParent = $parentId ? Folder::find($parentId) : null;

        return view('admin.folders.index', compact(
            'folders', 'services', 'serviceId', 'parentId', 'q', 'currentParent'
        ));
    }

    public function create(Request $request)
    {
        $services = Service::query()->orderBy('name')->get();
        $parents  = Folder::query()->orderBy('name')->get();

        $serviceId = $request->integer('service_id');
        $parentId  = $request->integer('parent_id');

        return view('admin.folders.create', compact('services', 'parents', 'serviceId', 'parentId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'service_id' => ['nullable','exists:services,id'],
            'parent_id' => ['nullable','exists:folders,id'],
            'is_active' => ['nullable'],
        ]);

        // Génération slug unique
        $slug = Str::slug($data['name']);
        $base = $slug;
        $i = 2;
        while (Folder::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        $folder = Folder::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        // ✅ audit création
        audit('created', 'Folder', $folder->id, null, $folder->toArray());

        return redirect()->route('admin.folders.index', [
            'service_id' => $data['service_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
        ])->with('success', 'Dossier créé avec succès.');
    }

    public function edit(Folder $folder)
    {
        $services = Service::query()->orderBy('name')->get();

        $parents = Folder::query()
            ->where('id', '!=', $folder->id)
            ->orderBy('name')
            ->get();

        return view('admin.folders.edit', compact('folder','services','parents'));
    }

    public function update(Request $request, Folder $folder)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'service_id' => ['nullable','exists:services,id'],
            'parent_id' => ['nullable','exists:folders,id'],
            'is_active' => ['nullable'],
        ]);

        // Empêcher parent = soi-même
        if (!empty($data['parent_id']) && (int)$data['parent_id'] === (int)$folder->id) {
            return back()->withErrors([
                'parent_id' => 'Un dossier ne peut pas être son propre parent.'
            ]);
        }

        // ✅ sauvegarde ancienne valeur
        $old = $folder->toArray();

        $folder->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        // ✅ audit modification
        audit('updated', 'Folder', $folder->id, $old, $folder->toArray());

        return redirect()->route('admin.folders.index')
            ->with('success', 'Dossier mis à jour.');
    }

    public function destroy(Folder $folder)
    {
        // Si des sous-dossiers existent
        if ($folder->children()->exists()) {
            return back()->withErrors([
                'folder' => 'Impossible : ce dossier contient des sous-dossiers.'
            ]);
        }

        // Si des documents existent
        if ($folder->documents()->exists()) {
            return back()->withErrors([
                'folder' => 'Impossible : ce dossier contient des documents.'
            ]);
        }

        // ✅ sauvegarde ancienne valeur
        $old = $folder->toArray();

        $folder->delete();

        // ✅ audit suppression
        audit('deleted', 'Folder', $folder->id, $old, null);

        return redirect()->route('admin.folders.index')
            ->with('success', 'Dossier supprimé.');
    }
}