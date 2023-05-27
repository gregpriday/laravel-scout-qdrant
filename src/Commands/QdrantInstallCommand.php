<?php

namespace GregPriday\LaravelScoutQdrant\Commands;

use Illuminate\Console\Command;

class QdrantInstallCommand extends Command
{
    protected $signature = 'qdrant:install {version? : The version of Qdrant to install}';

    protected $description = 'Pulls the Qdrant Docker image';

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
            $this->error('Failed to pull Qdrant Docker image');
        } else {
            $this->info('Successfully pulled Qdrant Docker image');
        }
    }
}
