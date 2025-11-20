<?php

namespace App\Console\Commands;

use App\Services\SwiftProcessingService;
use App\Services\SwiftCodeTranslator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessSwiftMessages extends Command
{
    protected $signature = 'swift:process-inbound';
    protected $description = 'Process inbound SWIFT .fin files, grouping them by Type/Sender/Receiver/Date into single CSVs.';
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

        // Array to hold grouped data
        // Key will be a composite of Type|Sender|Receiver|Date
        $groupedMessages = [];

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            if (str_starts_with($fileName, '.')) continue;

            $this->line("Parsing: {$fileName}");

            try {
                $fileContent = Storage::disk($inboundDisk)->get($filePath);
                $result = $this->processingService->parseFile($fileContent, $fileName);

                if ($result) {
                    $data = $result['data'];
                    $meta = $result['meta'];

                    // Create a unique key for grouping
                    // Only messages with EXACTLY the same Type, Sender, Receiver, and Date will be merged.
                    $groupKey = sprintf(
                        '%s|%s|%s|%s',
                        $meta['mt_type'],
                        $meta['sender'],
                        $meta['receiver'],
                        $meta['date_yymmdd']
                    );

                    if (!isset($groupedMessages[$groupKey])) {
                        $groupedMessages[$groupKey] = [
                            'meta' => $meta,
                            'rows' => []
                        ];
                    }

                    // Add this message's data as a new row in the group
                    $groupedMessages[$groupKey]['rows'][] = $data;

                } else {
                    $this->warn("Skipped {$fileName}: Unable to determine MT type.");
                }
            } catch (\Exception $e) {
                $this->error("Error processing {$fileName}: " . $e->getMessage());
                Log::error("SWIFT Processing Error for {$fileName}", ['exception' => $e]);
            }
        }

        $this->info("Grouping complete. Generating CSV files...");

        // Generate CSVs from groups
        foreach ($groupedMessages as $key => $group) {
            $meta = $group['meta'];
            $rows = $group['rows'];

            // 1. Prepare Filename Variables
            $mtType = $meta['mt_type'];
            $sender = $meta['sender'];
            $receiver = $meta['receiver'];
            $rawDate = $meta['date_yymmdd']; // YYMMDD

            // Convert YYMMDD to DDMMYY
            $dateDdmmyy = $rawDate; 
            if (strlen($rawDate) == 6) {
                $year = substr($rawDate, 0, 2);
                $month = substr($rawDate, 2, 2);
                $day = substr($rawDate, 4, 2);
                $dateDdmmyy = $day . $month . $year;
            }

            $meaning = strtolower(SwiftCodeTranslator::translateMessageType($mtType));
            // Replace spaces with underscores for safer filenames if preferred, or keep spaces as requested
            // $meaning = str_replace(' ', '_', $meaning); 

            // 2. Construct Filename
            // Format: messagetype_meaning_from:bic-to:bic_date(ddmmyy).csv
            // Example: 541_receive against payment_ARTBMYKLXXX-PMBKLCSUXXX_201125.csv
            $filename = sprintf(
                "%s_%s_%s-%s_%s.csv",
                $mtType,
                $meaning,
                $sender,
                $receiver,
                $dateDdmmyy
            );

            // 3. Create Directory (Group by Message Type)
            $directory = $mtType;
            Storage::disk($outboundDisk)->makeDirectory($directory);

            // 4. Generate Content
            $csvContent = $this->processingService->generateCsvContent($rows);

            // 5. Save
            $fullPath = $directory . '/' . $filename;
            Storage::disk($outboundDisk)->put($fullPath, $csvContent);

            $this->info("Created: {$fullPath} (" . count($rows) . " messages combined)");
        }

        $this->info("All processing complete.");
        return 0;
    }
}