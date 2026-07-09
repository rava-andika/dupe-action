<?php

namespace App\Jobs;

use App\Models\AdminLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogAdminActivity implements ShouldQueue
{
     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected ?int $userId,
        protected string $action,
        protected string $resourceName,
        protected ?string $details = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        AdminLog::create([
            'admin_id' => $this->userId,
            'action' => $this->action,
            'resource_name' => $this->resourceName,
            'details' => $this->details,
        ]);
    }
}
