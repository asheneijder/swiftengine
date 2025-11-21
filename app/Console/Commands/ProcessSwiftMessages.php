<?php

namespace App\Console\Commands;

use App\Services\SwiftProcessingService;
use Illuminate\Console\Command;

class ProcessSwiftMessages extends Command
{
    protected $signature = 'swift:process-inbound';
    protected $description = 'Process inbound SWIFT .fin files, save to MongoDB, and generate grouped CSVs.';
    protected $processingService;

    public function __construct(SwiftProcessingService $processingService)
    {
        parent::__construct();
        $this->processingService = $processingService;
    }

    public function handle()
    {
        $this->info('Starting SWIFT inbound file processing...');

        // Delegate all logic to the service
        // We pass a closure to handle console output (info, warn, error)
        $this->processingService->processInboundFiles(
            'swift_inbound',
            'swift_outbound',
            function (string $type, string $message) {
                match ($type) {
                    'info' => $this->info($message),
                    'warn' => $this->warn($message),
                    'error' => $this->error($message),
                    'line' => $this->line($message),
                    default => $this->line($message),
                };
            }
        );

        return 0;
    }
}