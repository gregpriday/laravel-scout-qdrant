<?php

namespace GregPriday\LaravelScoutQdrant\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class QdrantStatusCommand extends Command
{
    protected $signature = 'qdrant:status';

    protected $description = 'Check the status of the Qdrant Docker container';

    public function handle()
    {
        // Fetch data from Docker
        exec('docker ps --filter "ancestor=qdrant/qdrant" --format "{{.ID}}\t{{.Image}}\t{{.Command}}\t{{.CreatedAt}}\t{{.Status}}\t{{.Ports}}\t{{.Names}}"', $output, $return_var);

        if (empty($output)) {
            $this->info('Qdrant is not running.');
        } else {
            $this->info('Qdrant is running. Here are the details:');

            $headers = ['CONTAINER ID', 'IMAGE', 'COMMAND', 'CREATED', 'STATUS', 'PORTS', 'NAMES'];

            // Split each line of the output into an array of columns
            $rows = array_map(function($line) {
                // Using trim to remove trailing newline
                return Str::of($line)->explode("\t")->map(function ($item) {
                    return trim($item, '"');
                })->toArray();
            }, $output);

            // Display the output as a table
            $this->table($headers, $rows);
        }
    }
}
