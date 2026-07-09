<?php

namespace App\Jobs;

use App\Models\TemporaryFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanTemporaryUploads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $filesToDelete = TemporaryFile::where('created_at', '<', now()->subHours(1))->get(); // Delete files older than 1 hour
        $filesDeleted = 0;

        foreach ($filesToDelete as $file) {
            Storage::disk('temp')->delete($file->path);
            $file->delete();
            $filesDeleted++;
        }
        
        Log::info("Temporary file cleanup complete. Deleted {$filesDeleted} expired files.");
    }
}
