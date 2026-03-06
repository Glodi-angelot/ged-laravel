<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $q      = $request->string('q')->toString();
        $action = $request->string('action')->toString();
        $entity = $request->string('entity')->toString();

        $logs = AuditLog::query()
            ->with('user')
            ->when($action, fn($query) => $query->where('action', $action))
            ->when($entity, fn($query) => $query->where('entity_type', $entity))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('entity_type', 'like', "%{$q}%")
                        ->orWhere('route', 'like', "%{$q}%")
                        ->orWhere('ip', 'like', "%{$q}%")
                        ->orWhere('action', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $actions  = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $entities = AuditLog::query()->select('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type');

        return view('admin.audit.index', compact('logs', 'q', 'action', 'entity', 'actions', 'entities'));
    }

    /**
     * Supprimer un historique (une ligne)
     */
    public function destroy(AuditLog $log, Request $request)
    {
        $log->delete();

        return redirect()
            ->route('admin.audit.index', $request->query()) // garde les filtres
            ->with('success', 'Historique supprimé.');
    }

    /**
     * (Optionnel) Supprimer en masse selon les filtres
     * - q (route/ip/type/action)
     * - action
     * - entity
     */
    public function clear(Request $request)
    {
        $q      = $request->string('q')->toString();
        $action = $request->string('action')->toString();
        $entity = $request->string('entity')->toString();

        $query = AuditLog::query();

        if ($action) {
            $query->where('action', $action);
        }

        if ($entity) {
            $query->where('entity_type', $entity);
        }

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('entity_type', 'like', "%{$q}%")
                    ->orWhere('route', 'like', "%{$q}%")
                    ->orWhere('ip', 'like', "%{$q}%")
                    ->orWhere('action', 'like', "%{$q}%");
            });
        }

        $deleted = $query->delete();

        return redirect()
            ->route('admin.audit.index')
            ->with('success', "Historique supprimé : {$deleted} ligne(s).");
    }
}