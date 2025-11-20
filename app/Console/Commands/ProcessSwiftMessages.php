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
    protected $description = 'Process inbound SWIFT .fin files and generate aggregated CSVs per MT type.';

    protected $processingService;

    public function __construct(SwiftProcessingService $processingService)
    {
        parent::__construct();
        $this->processingService = $processingService;
    }

    public function handle()
    {
        $this->info('Starting SWIFT inbound file processing...');
        
        $inboundDisk = 'swift_inbound';
        $outboundDisk = 'swift_outbound';

        Storage::disk($inboundDisk)->makeDirectory('/');
        Storage::disk($outboundDisk)->makeDirectory('/');

        $files = Storage::disk($inboundDisk)->files();

        if (empty($files)) {
            $this->warn('No files found to process.');
            return 0;
        }

        $this->info(sprintf('Found %d file(s) to process.', count($files)));

        // This array will hold all data grouped by MT type
        // e.g., ['543' => [ [row1], [row2] ], '103' => [ [row1] ] ]
        $groupedData = [];
        
        $processedCount = 0;
        $errorCount = 0;

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            
            // Ignore hidden files (like .gitignore)
            if (str_starts_with($fileName, '.')) {
                continue;
            }

            $this->line("Parsing file: {$fileName}");

            try {
                $fileContent = Storage::disk($inboundDisk)->get($filePath);
                
                // Parse the file content
                $result = $this->processingService->parseFile($fileContent, $fileName);

                if ($result) {
                    $mtType = $result['type'];
                    $data = $result['data'];
                    
                    if (!isset($groupedData[$mtType])) {
                        $groupedData[$mtType] = [];
                    }
                    
                    $groupedData[$mtType][] = $data;
                    $processedCount++;
                } else {
                    $this->warn("Skipped {$fileName}: Unknown or unsupported MT type.");
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $this->error("Error processing {$fileName}: " . $e->getMessage());
                Log::error("SWIFT Processing Error for {$fileName}:", ['exception' => $e]);
                $errorCount++;
            }
        }

        // Generate one CSV file for each MT type detected
        foreach ($groupedData as $mtType => $rows) {
            $csvContent = $this->processingService->generateCsvContent($rows);
            $outputFilename = "MT{$mtType}.csv";
            
            Storage::disk($outboundDisk)->put($outputFilename, $csvContent);
            $this->info("Generated aggregated CSV: {$outputFilename} with " . count($rows) . " records.");
        }

        $this->info('Processing complete.');
        $this->info("Successfully processed: {$processedCount} file(s).");
        
        return 0;
    }
}