<?php

namespace App\Services;

use App\Services\SwiftParsers\SwiftMessageParser;
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
    /**
     * @var array
     */
    protected $parsers = [
        '103' => Mt103Parser::class,
        '210' => Mt210Parser::class,
        '541' => Mt541Parser::class,
        '543' => Mt543Parser::class,
        '545' => Mt545Parser::class,
        '547' => Mt547Parser::class,
        '940' => Mt940Parser::class,
    ];

    /**
     * Parse a single .fin file content and return structured data.
     *
     * @param string $fileContent
     * @param string $fileName
     * @return array|null Returns ['type' => '543', 'data' => [...]] or null on failure.
     */
    public function parseFile(string $fileContent, string $fileName): ?array
    {
        $mtType = SwiftParserUtil::getMessageType($fileContent);

        if (!$mtType || !isset($this->parsers[$mtType])) {
            return null; // Unknown or unsupported MT type
        }

        /** @var SwiftMessageParser $parser */
        $parser = new $this->parsers[$mtType]();

        $parsedData = $parser->parse($fileContent);

        // Include filename in data for reference
        $parsedData['_source_file'] = $fileName;

        return [
            'type' => $mtType,
            'data' => $parsedData,
        ];
    }
    
    /**
     * Helper to convert array of rows to CSV string
     */
    public function generateCsvContent(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        // Get headers from the first row
        $headers = array_keys(reset($rows));
        
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write rows
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }
}