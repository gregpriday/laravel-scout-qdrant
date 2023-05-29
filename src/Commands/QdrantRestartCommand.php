<?php

namespace GregPriday\LaravelScoutQdrant\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;

class QdrantRestartCommand extends Command
{
    protected $signature = 'qdrant:restart {--storage= : Storage directory for Qdrant} {--port=6333 : Port to expose Qdrant service} {--restart=unless-stopped : Restart policy for the Qdrant Docker container}';

    protected $description = 'Restarts the Qdrant Docker container';

    public function handle()
    {
        // First, stop the running container, if any
        $stopExitCode = $this->call('qdrant:terminate');

        if ($stopExitCode !== 0) {
            $this->error('Failed to stop Qdrant Docker container');
            return;
        }

        // Then, start a new container
        $startExitCode = $this->call('qdrant:start', [
            '--storage' => $this->option('storage'),
            '--port' => $this->option('port'),
            '--restart' => $this->option('restart'),
        ]);

        if ($startExitCode !== 0) {
            $this->error('Failed to start Qdrant Docker container');
        } else {
            $this->info('Successfully restarted Qdrant Docker container');
        }
    }
}
