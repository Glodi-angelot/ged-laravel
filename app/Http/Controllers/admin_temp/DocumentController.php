<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Service;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    // Extensions autorisées (OPTION 1)
    private array $allowedMimes = [
        'application/pdf',

        // Word
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',

        // Excel
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

        // PowerPoint
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $serviceId = $request->integer('service_id');

        $documents = Document::query()
            ->with(['service','uploader'])
            ->when($q, function ($query) use ($q) {
                $query->where(function($sub) use ($q) {
                    $sub->where('title', 'like', "%{$q}%")
                        ->orWhere('reference', 'like', "%{$q}%")
                        ->orWhere('original_name', 'like', "%{$q}%");
                });
            })
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($serviceId, fn($query) => $query->where('service_id', $serviceId))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $services = Service::query()->orderBy('name')->get();

        return view('admin.documents.index', compact('documents', 'services', 'q', 'status', 'serviceId'));
    }

    public function create()
    {
        $services = Service::query()->orderBy('name')->get();
        $folders  = Folder::query()->orderBy('name')->get();

        return view('admin.documents.create', compact('services', 'folders'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'reference' => ['nullable','string','max:80'],
            'description' => ['nullable','string'],
            'service_id' => ['nullable','exists:services,id'],
            'status' => ['required','in:draft,validated,archived'],
            'folder_id' => ['nullable','exists:folders,id'],

            // ✅ PDF/Word/Excel/PPT (20MB)
            'file' => ['required','file','max:20480'],
        ]);

        $file = $request->file('file');

        // Sécurité: vérifier MIME (serveur)
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, $this->allowedMimes, true)) {
            return back()->withErrors([
                'file' => "Format non supporté. Autorisés : PDF, Word, Excel, PowerPoint."
            ])->withInput();
        }

        $path = $file->store('documents', 'public');

        $doc = Document::create([
            'title' => $data['title'],
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'folder_id' => $data['folder_id'] ?? null,

            'uploaded_by' => auth()->id(),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $mime,
            'status' => $data['status'],
        ]);

        // (optionnel) audit si tu as déjà la helper audit()
        // audit('created', 'Document', $doc->id, null, $doc->toArray());

        return redirect()->route('admin.documents.index')->with('success', 'Document ajouté avec succès.');
    }

    public function edit(Document $document)
    {
        $services = Service::query()->orderBy('name')->get();
        $folders  = Folder::query()->orderBy('name')->get();

        return view('admin.documents.edit', compact('document','services','folders'));
    }

    public function update(Request $request, Document $document)
    {
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'reference' => ['nullable','string','max:80'],
            'description' => ['nullable','string'],
            'service_id' => ['nullable','exists:services,id'],
            'status' => ['required','in:draft,validated,archived'],
            'folder_id' => ['nullable','exists:folders,id'],

            // ✅ peut remplacer par PDF/Word/Excel/PPT
            'file' => ['nullable','file','max:20480'],
        ]);

        // $old = $document->toArray();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = (string) $file->getMimeType();

            if (!in_array($mime, $this->allowedMimes, true)) {
                return back()->withErrors([
                    'file' => "Format non supporté. Autorisés : PDF, Word, Excel, PowerPoint."
                ])->withInput();
            }

            Storage::disk('public')->delete($document->file_path);
            $path = $file->store('documents', 'public');

            $document->file_path = $path;
            $document->original_name = $file->getClientOriginalName();
            $document->file_size = $file->getSize();
            $document->mime_type = $mime;
        }

        $document->title = $data['title'];
        $document->reference = $data['reference'] ?? null;
        $document->description = $data['description'] ?? null;
        $document->service_id = $data['service_id'] ?? null;
        $document->folder_id = $data['folder_id'] ?? null;
        $document->status = $data['status'];

        $document->save();

        // audit('updated', 'Document', $document->id, $old, $document->toArray());

        return redirect()->route('admin.documents.index')->with('success', 'Document mis à jour.');
    }

    /**
     * ✅ OPTION 1 : Preview PDF uniquement (inline).
     * Autres formats => on redirige vers download.
     */
    public function preview(Document $document)
    {
        // si fichier manquant
        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'Fichier introuvable.');
        }

        $mime = strtolower((string) ($document->mime_type ?? ''));

        // PDF => inline dans navigateur
        if (str_contains($mime, 'pdf')) {
            $fullPath = Storage::disk('public')->path($document->file_path);
            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$document->original_name.'"',
            ]);
        }

        // autres => téléchargement
        return redirect()
            ->route('admin.documents.download', $document)
            ->with('success', "Prévisualisation disponible uniquement pour les PDF. Téléchargement lancé.");
    }

    public function download(Document $document)
    {
        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('public')->download($document->file_path, $document->original_name);
    }

    public function destroy(Document $document)
    {
        // $old = $document->toArray();

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        // audit('deleted', 'Document', $document->id, $old, null);

        return redirect()->route('admin.documents.index')->with('success', 'Document supprimé.');
    }
}