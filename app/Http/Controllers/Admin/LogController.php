<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ActivityLog::query()->orderBy('timestamp', 'desc');
        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function ($q) use ($term) {
                $q->where('user', 'like', "%{$term}%")->orWhere('action', 'like', "%{$term}%");
            });
        }

        return view('admin.logs.index', ['logs' => $query->limit(2000)->get()]);
    }
}
