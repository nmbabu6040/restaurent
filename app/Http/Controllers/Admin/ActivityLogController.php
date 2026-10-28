<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index()
    {
        return view('admin.activity.index', [
            'logs' => ActivityLog::with('user')->latest('id')->paginate(30),
        ]);
    }
}
