<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    // Liste des documents à valider
    public function validation(Request $request)
    {
        $q = $request->string('q')->toString();

        $documents = Document::query()
            ->with(['service','folder','uploader'])
            ->where('status', 'draft')
            ->when($q, function ($query) use ($q) {
                $query->where(function($sub) use ($q) {
                    $sub->where('title', 'like', "%{$q}%")
                        ->orWhere('reference', 'like', "%{$q}%")
                        ->orWhere('original_name', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.workflow.validation', compact('documents', 'q'));
    }

    // Changer statut (draft/validated/archived)
    public function updateStatus(Request $request, Document $document)
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,validated,archived'],
        ]);

        $old = $document->toArray();

        $document->update([
            'status' => $data['status'],
        ]);

        audit('status_changed', 'Document', $document->id, [
            'status' => $old['status'] ?? null,
        ], [
            'status' => $document->status,
        ]);

        return back()->with('success', 'Statut du document mis à jour.');
    }
}