<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use Illuminate\Http\Request;
use Inertia\Response;

class AdminLogsController extends Controller
{
    public static string $name = 'admin-logs';
    public static string $icon = 'ClipboardList';
    public static int $position = 999;

    private function getPaginatedData(Request $request): array
    {
        return getPaginatedData(
            $request,
            AdminLog::class,
            ['id', 'admin.name', 'action', 'resource_name', 'details', 'created_at'],
            'admin.admin-logs.index',
        );
    }

    public function index(Request $request): Response
    {
        return inertia('Admin/AdminLogs', $this->getPaginatedData($request));
    }

    public function show(Request $request,  AdminLog $adminLog): Response
    {
        $adminLog->load('admin');
        return inertia('Admin/AdminLogs', array_merge($this->getPaginatedData($request), [
            'showData' => [
                'id' => $adminLog->id,
                'admin' => empty($adminLog->admin) ? null : [$adminLog->admin_id => $adminLog->admin->name],
                'action' => $adminLog->action,
                'resource_name' => $adminLog->resource_name,
                'details' => $adminLog->details,
                'created_at' => $adminLog->created_at
            ]
        ]));
    }
}
