<?php

namespace App\Console\Commands;

use App\Models\SwiftMessage;
use App\Services\SwiftProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessSwiftMessages extends Command
{
    protected $signature = 'swift:process-inbound';
    protected $description = 'Process inbound SWIFT files and save to MongoDB';
    protected $processingService;

    public function __construct(SwiftProcessingService $processingService)
    {
        parent::__construct();
        $this->processingService = $processingService;
    }

    public function handle()
    {
        $this->info('Processing SWIFT files into MongoDB...');
        
        $files = Storage::disk('swift_inbound')->files();

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            if (str_starts_with($fileName, '.')) continue;

            try {
                $content = Storage::disk('swift_inbound')->get($filePath);
                $result = $this->processingService->parseFile($content, $fileName);

                if ($result) {
                    // Convert YYMMDD to Y-m-d
                    $rawDate = $result['meta']['date_yymmdd'];
                    $formattedDate = \DateTime::createFromFormat('ymd', $rawDate)->format('Y-m-d');

                    SwiftMessage::create([
                        'mt_type' => $result['meta']['mt_type'],
                        'sender' => $result['meta']['sender'],
                        'receiver' => $result['meta']['receiver'],
                        'message_date' => $formattedDate,
                        'parsed_data' => $result['data'],
                        'source_file' => $fileName
                    ]);

                    $this->info("Imported: $fileName");
                    
                    // Optional: Move processed file
                    // Storage::disk('swift_inbound')->move($filePath, 'processed/' . $fileName);
                }
            } catch (\Exception $e) {
                $this->error("Error $fileName: " . $e->getMessage());
            }
        }

        $this->info('Done.');
    }
}