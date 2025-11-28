<?php

namespace App\Services;

use App\Models\SwiftMessage;
// MT Parsers
use App\Services\SwiftParsers\Mt103Parser;
use App\Services\SwiftParsers\Mt210Parser;
use App\Services\SwiftParsers\Mt541Parser;
use App\Services\SwiftParsers\Mt543Parser;
use App\Services\SwiftParsers\Mt544Parser; // Added
use App\Services\SwiftParsers\Mt545Parser;
use App\Services\SwiftParsers\Mt546Parser; // Added
use App\Services\SwiftParsers\Mt547Parser;
use App\Services\SwiftParsers\Mt940Parser;
// MX Parsers
use App\Services\SwiftParsers\MxPacs008Parser; // Added

use App\Services\SwiftParserUtil;
use App\Services\SwiftMxParserUtil; // Added
use App\Services\SwiftCodeTranslator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SwiftProcessingService
{
    protected $parsers = [
        // MT (FIN)
        '103' => Mt103Parser::class,
        '210' => Mt210Parser::class,
        '541' => Mt541Parser::class,
        '543' => Mt543Parser::class,
        '544' => Mt544Parser::class,
        '545' => Mt545Parser::class,
        '546' => Mt546Parser::class,
        '547' => Mt547Parser::class,
        '940' => Mt940Parser::class,

        // MX (XML) - Keys match the 'MsgDefIdr' (e.g. pacs.008)
        'pacs.008' => MxPacs008Parser::class,
    ];

    /**
     * Main control method to process files, save to DB, and generate CSVs.
     */
    public function processInboundFiles(string $inboundDisk, string $outboundDisk, ?callable $onProgress = null): void
    {
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

            // Filter: Hidden files
            if (str_starts_with($fileName, '.')) continue;

            // Filter: Extensions (Allow .fin, .txt, .xml)
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['fin', 'txt', 'xml'])) {
                if ($onProgress) $onProgress('line', "Skipping {$fileName}: Unsupported extension.");
                continue;
            }

            if ($onProgress) $onProgress('line', "Parsing: {$fileName}");

            try {
                $fileContent = Storage::disk($inboundDisk)->get($filePath);
                $result = $this->parseFile($fileContent, $fileName);

                if ($result) {
                    // 1. Duplicate Check & Save to MongoDB
                    if ($this->saveToDatabase($result['data'])) {
                        if ($onProgress) $onProgress('info', "  [ACCEPTED] Saved to database.");

                        // 2. Add to Group for CSV generation
                        $this->groupMessage($groupedMessages, $result);
                    } else {
                        if ($onProgress) $onProgress('warn', "  [REJECTED] Duplicate data found for {$fileName}. Skipping.");
                    }
                } else {
                    if ($onProgress) $onProgress('warn', "Skipped {$fileName}: Unable to determine Message Type.");
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
     * Parses a single file content (Detects MT or MX automatically).
     */
    public function parseFile(string $fileContent, string $fileName): ?array
    {
        // 1. Check if XML (MX Message)
        if (SwiftMxParserUtil::isXml($fileContent)) {
            return $this->parseMxFile($fileContent, $fileName);
        }

        // 2. Default to MT (FIN Message)
        return $this->parseMtFile($fileContent, $fileName);
    }

    /**
     * Logic for processing MT (FIN) files
     */
    protected function parseMtFile(string $fileContent, string $fileName): ?array
    {
        $mtType = SwiftParserUtil::getMessageType($fileContent);

        if (!$mtType || !isset($this->parsers[$mtType])) {
            return null;
        }

        $parser = new $this->parsers[$mtType]();
        $parsedData = $parser->parse($fileContent);

        // Extract Metadata for grouping
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
     * Logic for processing MX (XML) files
     */
    protected function parseMxFile(string $fileContent, string $fileName): ?array
    {
        $xml = SwiftMxParserUtil::parseXml($fileContent);
        if (!$xml) return null;

        // Extract Full Type (e.g., 'pacs.008.001.08')
        $fullType = SwiftMxParserUtil::getMxMessageType($xml);

        // Match against registered parsers (e.g. check if 'pacs.008' exists)
        // We take the first 8 chars (pacs.008) to match our array keys
        $mxType = substr($fullType, 0, 8);

        if (!$mxType || !isset($this->parsers[$mxType])) {
            return null;
        }

        $parser = new $this->parsers[$mxType]();
        $parsedData = $parser->parse($fileContent);

        // Extract Metadata for grouping
        // XML dates are usually YYYY-MM-DD. We convert to YYMMDD to match MT grouping logic.
        $creationDate = $parsedData['Creation Date'] ?? date('Y-m-d');
        $dateYymmdd = date('ymd', strtotime($creationDate));

        return [
            'type' => $mxType,
            'data' => $parsedData,
            'meta' => [
                'mt_type' => $mxType,
                'sender' => $parsedData['Sender'] ?? 'UNKNOWN',
                'receiver' => $parsedData['Receiver'] ?? 'UNKNOWN',
                'date_yymmdd' => $dateYymmdd,
                'source_file' => $fileName
            ]
        ];
    }

    /**
     * Checks for duplicates and saves to MongoDB.
     */
    protected function saveToDatabase(array $data): bool
    {
        // Check if exact data already exists (flexible for both MT and MX fields)
        $exists = SwiftMessage::where(function ($query) use ($data) {
            foreach ($data as $key => $value) {
                // MongoDB keys can't have dots; ensuring safety if needed, 
                // but usually values are just strings.
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
            // Sanitize meaning for filename (remove spaces/special chars)
            $meaning = preg_replace('/[^a-z0-9]+/', '_', $meaning);

            $filename = sprintf(
                "%s_%s_%s-%s_%s.csv",
                $mtType,
                $meaning,
                $sender,
                $receiver,
                $dateDdmmyy
            );

            // Group by Type (Folder)
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
