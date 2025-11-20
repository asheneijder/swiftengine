<?php

namespace App\Services;

use App\Services\SwiftParsers\Mt103Parser;
use App\Services\SwiftParsers\Mt210Parser;
use App\Services\SwiftParsers\Mt541Parser;
use App\Services\SwiftParsers\Mt543Parser;
use App\Services\SwiftParsers\Mt545Parser;
use App\Services\SwiftParsers\Mt547Parser;
use App\Services\SwiftParsers\Mt940Parser;
use App\Services\SwiftParserUtil;

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
    ];

    public function parseFile(string $fileContent, string $fileName): ?array
    {
        $mtType = SwiftParserUtil::getMessageType($fileContent);

        if (!$mtType || !isset($this->parsers[$mtType])) {
            return null;
        }

        $parser = new $this->parsers[$mtType]();
        $parsedData = $parser->parse($fileContent);

        // Extract Metadata for filename generation and grouping
        $sender = SwiftParserUtil::getSenderBic($fileContent) ?? 'UNKNOWN';
        $receiver = SwiftParserUtil::getReceiverBic($fileContent) ?? 'UNKNOWN';
        $messageDate = SwiftParserUtil::getMessageDate($fileContent) ?? '000000'; // YYMMDD

        // Return standard structure with separated metadata
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
    
    public function generateCsvContent(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        $headers = array_keys(reset($rows));
        
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        
        foreach ($rows as $row) {
            // Sanitize row values to avoid Array to String conversion errors
            $sanitizedRow = array_map(function($item) {
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