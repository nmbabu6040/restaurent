<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\BackupService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index()
    {
        $list = $this->backups->list();

        return view('admin.backups.index', [
            'backups' => $list,
            'last' => collect($list)->first()['time'] ?? null,
            'directory' => $this->backups->directory(),
            'zipAvailable' => class_exists(\ZipArchive::class),
            'scheduled' => (bool) config('restaurant.backup.enabled'),
            'keep' => (int) config('restaurant.backup.keep'),
        ]);
    }

    public function run()
    {
        set_time_limit(300);

        try {
            $result = $this->backups->create();
        } catch (RuntimeException $e) {
            Log::error('Backup failed: ' . $e->getMessage());

            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Backup failed: ' . $e->getMessage());

            return back()->with('error', 'Backup failed. See the log file for details.');
        }

        ActivityLog::record('backup.created', 'Created a backup (' . basename($result['db']) . ')');

        return back()->with('status', 'Backup created. Download it and keep a copy somewhere else too.');
    }

    public function download(string $name)
    {
        $path = $this->backups->path($name);
        abort_if($path === null, 404);

        ActivityLog::record('backup.downloaded', "Downloaded backup {$name}");

        return response()->download($path, $name, ['Cache-Control' => 'no-store, private']);
    }

    public function destroy(string $name)
    {
        abort_unless($this->backups->delete($name), 404);

        ActivityLog::record('backup.deleted', "Deleted backup {$name}");

        return back()->with('status', 'Backup deleted.');
    }
}
