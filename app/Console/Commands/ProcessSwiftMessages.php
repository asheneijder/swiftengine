<?php

namespace App\Console\Commands;

use App\Services\SwiftProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessSwiftMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swift:process-inbound';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process inbound SWIFT .fin files from the swift_inbound directory and generate CSV output.';

    protected $processingService;

    /**
     * Create a new command instance.
     *
     * @param SwiftProcessingService $processingService
     */
    public function __construct(SwiftProcessingService $processingService)
    {
        parent::__construct();
        $this->processingService = $processingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting SWIFT inbound file processing...');
        
        $inboundDisk = 'swift_inbound';
        $outboundDisk = 'swift_outbound';

        // Ensure directories exist
        Storage::disk($inboundDisk)->makeDirectory('/');
        Storage::disk($outboundDisk)->makeDirectory('/');

        $files = Storage::disk($inboundDisk)->files();

        if (empty($files)) {
            $this->warn('No files found in storage/app/swift_inbound to process.');
            return 0;
        }

        $this->info(sprintf('Found %d file(s) to process.', count($files)));

        $processedCount = 0;
        $errorCount = 0;

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $this->line("Processing file: {$fileName}");

            try {
                $fileContent = Storage::disk($inboundDisk)->get($filePath);
                
                // Process and save the file
                $outputPath = $this->processingService->processFile($fileContent, $fileName);

                if ($outputPath) {
                    $this->info("Successfully processed and saved to: {$outputPath}");
                    // Optionally, delete the original file after successful processing
                    // Storage::disk($inboundDisk)->delete($filePath);
                    $processedCount++;
                } else {
                    $this->error("Failed to identify MT type for file: {$fileName}");
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $this->error("Error processing file {$fileName}: " . $e->getMessage());
                Log::error("SWIFT Processing Error for {$fileName}:", ['exception' => $e]);
                $errorCount++;
            }
        }

        $this->info('Processing complete.');
        $this->info("Successfully processed: {$processedCount} file(s).");
        $this->warn("Failed to process: {$errorCount} file(s).");

        return 0;
    }
}