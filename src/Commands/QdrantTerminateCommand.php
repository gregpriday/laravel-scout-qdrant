<?php

namespace GregPriday\LaravelScoutQdrant\Commands;

use Illuminate\Console\Command;

class QdrantTerminateCommand extends Command
{
    protected $signature = 'qdrant:terminate';

    protected $description = 'Stops the Qdrant Docker container';

    public function handle()
    {
        // Check if Qdrant is running
        exec('docker ps | grep qdrant/qdrant', $output, $return_var);
        if (empty($output)) {
            $this->comment('Qdrant is not running.');
            return;
        }

        // Extract container ID
        $containerId = preg_split('/\s+/', $output[0])[0];

        exec("docker stop $containerId", $output, $return_var);

        if ($return_var !== 0) {
            $this->error('Failed to stop Qdrant Docker container');
        } else {
            $this->info('Successfully stopped Qdrant Docker container');
        }
    }
}