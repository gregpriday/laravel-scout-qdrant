<?php

namespace GregPriday\LaravelScoutQdrant\Commands;

use Illuminate\Console\Command;

class QdrantStartCommand extends Command
{
    protected $signature = 'qdrant:start {--storage= : Storage directory for Qdrant} {--port=6333 : Port to expose Qdrant service}';

    protected $description = 'Starts the Qdrant Docker container';

    public function handle()
    {
        // Check if Qdrant is already running
        exec('docker ps | grep qdrant/qdrant', $output, $return_var);
        if (!empty($output)) {
            $this->comment('Qdrant is already running.');
            return;
        }

        $storageDir = $this->option('storage') ?? config('scout-qdrant.qdrant.storage', 'database/qdrant');
        // Make sure the directory is absolute
        if ($storageDir[0] !== '/') {
            $storageDir = getcwd() . '/' . $storageDir;
        }

        $port = $this->option('port');

        exec("docker run -d -p $port:6333 -v $storageDir:/qdrant/storage qdrant/qdrant", $output, $return_var);

        if ($return_var !== 0) {
            $this->error('Failed to start Qdrant Docker container');
        } else {
            $this->info('Successfully started Qdrant Docker container');
        }
    }
}
