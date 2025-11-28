<?php

namespace App\Services;

use App\Models\SwiftMessage;
use App\Services\SwiftParsers\Mt103Parser;
use App\Services\SwiftParsers\Mt210Parser;
use App\Services\SwiftParsers\Mt541Parser;
use App\Services\SwiftParsers\Mt543Parser;
use App\Services\SwiftParsers\Mt545Parser;
use App\Services\SwiftParsers\Mt547Parser;
use App\Services\SwiftParsers\Mt940Parser;
use App\Services\SwiftParsers\Mt544Parser;
use App\Services\SwiftParsers\Mt546Parser;
use App\Services\SwiftParserUtil;
use App\Services\SwiftCodeTranslator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SwiftProcessingService
{
    protected $parsers = [
        '103' => Mt103Parser::class,
        '210' => Mt210Parser::class,
        '541' => Mt541Parser::class,
        '543' => Mt543Parser::class,
        '545' => Mt545Parser::class,
        '547' => Mt547Parser::class,
        '940' => Mt940Parser::class,
        '544' => Mt544Parser::class,
        '546' => Mt546Parser::class,
    ];

    /**
     * Main control method to process files, save to DB, and generate CSVs.
     * * @param string $inboundDisk Name of the filesystem disk for inbound files.
     * @param string $outboundDisk Name of the filesystem disk for outbound CSVs.
     * @param callable|null $onProgress Optional callback for logging output (type, message).
     */
    /**
     * Main control method to process files, save to DB, and generate CSVs.
     * @param string $inboundDisk Name of the filesystem disk for inbound files.
     * @param string $outboundDisk Name of the filesystem disk for outbound CSVs.
     * @param callable|null $onProgress Optional callback for logging output (type, message).
     */
    public function processInboundFiles(string $inboundDisk, string $outboundDisk, ?callable $onProgress = null): void
    {
        // Ensure directories exist
        Storage::disk($inboundDisk)->makeDirectory('/');
        Storage::disk($outboundDisk)->makeDirectory('/');

        $files = Storage::disk($inboundDisk)->files();

        if (empty($files)) {
            if ($onProgress) $onProgress('warn', 'No files found to process.');
            return;
        }

        $groupedMessages = [];

        foreach ($files as $filePath) {
            $fileName = basename($filePath);

            // 1. Skip system files (starting with dot)
            if (str_starts_with($fileName, '.')) continue;

            // 2. Validate Extension: Allow only .fin and .txt
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($extension, ['fin', 'txt'])) {
                if ($onProgress) $onProgress('line', "Skipping {$fileName}: Unsupported extension.");
                continue;
            }

            if ($onProgress) $onProgress('line', "Parsing: {$fileName}");

            try {
                // Get content (works for both .fin and .txt as they are plain text)
                $fileContent = Storage::disk($inboundDisk)->get($filePath);
                $result = $this->parseFile($fileContent, $fileName);

                if ($result) {
                    // Duplicate Check & Save to MongoDB
                    if ($this->saveToDatabase($result['data'])) {
                        if ($onProgress) $onProgress('info', "  [ACCEPTED] Saved to database.");

                        // Add to Group for CSV generation
                        $this->groupMessage($groupedMessages, $result);
                    } else {
                        if ($onProgress) $onProgress('warn', "  [REJECTED] Duplicate data found for {$fileName}. Skipping.");
                    }
                } else {
                    if ($onProgress) $onProgress('warn', "Skipped {$fileName}: Unable to determine MT type.");
                }
            } catch (\Exception $e) {
                $errorMsg = "Error processing {$fileName}: " . $e->getMessage();
                if ($onProgress) $onProgress('error', $errorMsg);
                Log::error("SWIFT Processing Error for {$fileName}", ['exception' => $e]);
            }
        }

        if (!empty($groupedMessages)) {
            if ($onProgress) $onProgress('info', "Grouping complete. Generating CSV files...");
            $this->generateAndSaveCsvs($groupedMessages, $outboundDisk, $onProgress);
        }

        if ($onProgress) $onProgress('info', "All processing complete.");
    }

    /**
     * Parses a single file content.
     */
    public function parseFile(string $fileContent, string $fileName): ?array
    {
        $mtType = SwiftParserUtil::getMessageType($fileContent);

        if (!$mtType || !isset($this->parsers[$mtType])) {
            return null;
        }

        $parser = new $this->parsers[$mtType]();
        $parsedData = $parser->parse($fileContent);

        // Extract Metadata
        $sender = SwiftParserUtil::getSenderBic($fileContent) ?? 'UNKNOWN';
        $receiver = SwiftParserUtil::getReceiverBic($fileContent) ?? 'UNKNOWN';
        $messageDate = SwiftParserUtil::getMessageDate($fileContent) ?? '000000'; // YYMMDD

        return [
            'type' => $mtType,
            'data' => $parsedData,
            'meta' => [
                'mt_type' => $mtType,
                'sender' => $sender,
                'receiver' => $receiver,
                'date_yymmdd' => $messageDate,
                'source_file' => $fileName
            ]
        ];
    }

    /**
     * Checks for duplicates and saves to MongoDB.
     */
    protected function saveToDatabase(array $data): bool
    {
        // Check if exact data already exists
        $exists = SwiftMessage::where(function ($query) use ($data) {
            foreach ($data as $key => $value) {
                $query->where($key, $value);
            }
        })->exists();

        if ($exists) {
            return false;
        }

        SwiftMessage::create($data);
        return true;
    }

    /**
     * Groups the parsed message into the array by Type/Sender/Receiver/Date.
     */
    protected function groupMessage(array &$groupedMessages, array $result): void
    {
        $data = $result['data'];
        $meta = $result['meta'];

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
    }

    /**
     * Generates CSV files from grouped messages and saves them to disk.
     */
    protected function generateAndSaveCsvs(array $groupedMessages, string $outboundDisk, ?callable $onProgress = null): void
    {
        foreach ($groupedMessages as $group) {
            $meta = $group['meta'];
            $rows = $group['rows'];

            $mtType = $meta['mt_type'];
            $sender = $meta['sender'];
            $receiver = $meta['receiver'];
            $rawDate = $meta['date_yymmdd'];

            // Convert YYMMDD to DDMMYY
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

            $csvContent = $this->generateCsvContent($rows);
            $fullPath = $directory . '/' . $filename;
            Storage::disk($outboundDisk)->put($fullPath, $csvContent);

            if ($onProgress) $onProgress('info', "Created CSV: {$fullPath} (" . count($rows) . " msgs)");
        }
    }

    /**
     * Converts array rows to CSV string.
     */
    public function generateCsvContent(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        $headers = array_keys(reset($rows));

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($rows as $row) {
            $sanitizedRow = array_map(function ($item) {
                if (is_array($item) || is_object($item)) {
                    return json_encode($item);
                }
                return $item;
            }, $row);

            fputcsv($output, $sanitizedRow);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }
}
