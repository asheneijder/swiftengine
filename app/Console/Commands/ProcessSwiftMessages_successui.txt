<?php

namespace App\Console\Commands;

use App\Services\SwiftProcessingService;
use App\Services\SwiftCodeTranslator;
use App\Models\SwiftMessage; // <--- IMPORT THIS MODEL
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessSwiftMessages extends Command
{
    protected $signature = 'swift:process-inbound';
    protected $description = 'Process inbound SWIFT .fin files, save to MongoDB and group into CSVs.';
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

                    // ============================================================
                    // [FIX START] SAVE TO MONGODB
                    // This is the part your current file is missing!
                    // ============================================================
                    $mongoDocument = array_merge($data, [
                        '_meta_filename' => $fileName,
                        '_meta_processed_at' => now(),
                        '_meta_type' => (string)$meta['mt_type'], // Fixes "Unknown Type"
                        '_meta_sender' => $meta['sender'],
                        '_meta_receiver' => $meta['receiver'],
                        '_meta_date' => $meta['date_yymmdd'],
                    ]);

                    SwiftMessage::create($mongoDocument);
                    // ============================================================
                    // [FIX END]
                    // ============================================================

                    // CSV Grouping Logic
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

                    $groupedMessages[$groupKey]['rows'][] = $data;

                } else {
                    $this->warn("Skipped {$fileName}: Unable to determine MT type.");
                }
            } catch (\Exception $e) {
                $this->error("Error processing {$fileName}: " . $e->getMessage());
                Log::error("SWIFT Processing Error for {$fileName}", ['exception' => $e]);
            }
        }

        $this->info("Mongo Import complete. Generating CSV files...");

        // Generate CSVs
        foreach ($groupedMessages as $key => $group) {
            $meta = $group['meta'];
            $rows = $group['rows'];

            $mtType = $meta['mt_type'];
            $sender = $meta['sender'];
            $receiver = $meta['receiver'];
            $rawDate = $meta['date_yymmdd'];

            $dateDdmmyy = $rawDate; 
            if (strlen($rawDate) == 6) {
                $year = substr($rawDate, 0, 2);
                $month = substr($rawDate, 2, 2);
                $day = substr($rawDate, 4, 2);
                $dateDdmmyy = $day . $month . $year;
            }

            $meaning = strtolower(SwiftCodeTranslator::translateMessageType($mtType));

            $filename = sprintf(
                "%s_%s_%s-%s_%s.csv",
                $mtType,
                $meaning,
                $sender,
                $receiver,
                $dateDdmmyy
            );

            $directory = $mtType;
            Storage::disk($outboundDisk)->makeDirectory($directory);
            $csvContent = $this->processingService->generateCsvContent($rows);
            $fullPath = $directory . '/' . $filename;
            Storage::disk($outboundDisk)->put($fullPath, $csvContent);

            $this->info("Created: {$fullPath} (" . count($rows) . " messages combined)");
        }

        $this->info("All processing complete.");
        return 0;
    }
}