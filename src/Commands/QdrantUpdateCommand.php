<?php

namespace GregPriday\LaravelScoutQdrant\Commands;

use Illuminate\Console\Command;

class QdrantUpdateCommand extends Command
{
    protected $signature = 'qdrant:update {version? : The version of Qdrant to update to}';

    protected $description = 'Updates the Qdrant Docker image';

    public function handle()
    {
        // Check if Docker is installed
        exec('docker -v', $output, $return_var);
        if ($return_var !== 0) {
            $this->error('Docker is not installed. Please visit https://docs.docker.com/engine/install/ for instructions on how to install Docker.');
            return;
        }

        // Set the Docker image tag
        $version = $this->argument('version');
        $tag = $version ? "qdrant/qdrant:$version" : 'qdrant/qdrant';

        exec("docker pull $tag", $output, $return_var);

        if ($return_var !== 0) {
            $this->error('Failed to update Qdrant Docker image');
        } else {
            // Stop the running Qdrant container
            exec('docker stop $(docker ps -q --filter ancestor=qdrant/qdrant)', $output, $return_var);
            if ($return_var !== 0) {
                $this->error('Failed to stop running Qdrant Docker container');
                return;
            }

            // Remove the old Qdrant container
            exec('docker rm $(docker ps -a -q --filter ancestor=qdrant/qdrant)', $output, $return_var);
            if ($return_var !== 0) {
                $this->error('Failed to remove old Qdrant Docker container');
                return;
            }

            $this->info('Successfully updated Qdrant Docker image. Please run `php artisan qdrant:start` to start the new container.');
        }
    }
}
