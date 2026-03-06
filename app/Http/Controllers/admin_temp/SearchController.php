<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->string('q')->toString());

        // Résultats vides si pas de query
        $services = collect();
        $folders  = collect();
        $documents = collect();
        $users = collect();
        $audits = collect();

        if ($q !== '') {
            $services = Service::query()
                ->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orderBy('name')
                ->limit(10)
                ->get();

            $folders = Folder::query()
                ->with(['service','parent'])
                ->where('name', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%")
                ->orderBy('name')
                ->limit(10)
                ->get();

            $documents = Document::query()
                ->with(['service','uploader','folder'])
                ->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', "%{$q}%")
                        ->orWhere('reference', 'like', "%{$q}%")
                        ->orWhere('original_name', 'like', "%{$q}%");
                })
                ->latest()
                ->limit(12)
                ->get();

            $users = User::query()
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->latest()
                ->limit(10)
                ->get();

            // Optionnel : audit 
            if (class_exists(AuditLog::class)) {
                $audits = AuditLog::query()
                    ->where('action', 'like', "%{$q}%")
                    ->orWhere('entity_type', 'like', "%{$q}%")
                    ->orWhere('entity_id', 'like', "%{$q}%")
                    ->latest()
                    ->limit(10)
                    ->get();
            }
        }

        return view('admin.search.index', compact('q', 'services', 'folders', 'documents', 'users', 'audits'));
    }
}