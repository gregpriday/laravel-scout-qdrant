<?php

namespace GregPriday\LaravelScoutQdrant\Commands;

use Illuminate\Console\Command;

class QdrantStopCommand extends Command
{
    protected $signature = 'qdrant:stop {--kill : Kill the container instead of stopping it}';

    protected $aliases = ['qdrant:terminate'];

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

        if ($this->option('kill')) {
            exec("docker kill $containerId", $output, $return_var);
            $action = 'killed';
        } else {
            exec("docker stop $containerId", $output, $return_var);
            $action = 'stopped';
        }

        if ($return_var !== 0) {
            $this->error("Failed to $action Qdrant Docker container");
        } else {
            $this->info("Successfully $action Qdrant Docker container");
        }
    }
}
