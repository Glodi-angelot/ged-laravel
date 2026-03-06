<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Document;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalDocs = Document::count();
        $draftDocs = Document::where('status', 'draft')->count();
        $validatedDocs = Document::where('status', 'validated')->count();
        $archivedDocs = Document::where('status', 'archived')->count();

        $latestDocuments = Document::query()
            ->with(['service','folder','uploader'])
            ->latest()
            ->take(8)
            ->get();

        $latestLogs = AuditLog::query()
            ->with('user')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.dashboard', compact(
            'totalDocs','draftDocs','validatedDocs','archivedDocs',
            'latestDocuments','latestLogs'
        ));
    }
}