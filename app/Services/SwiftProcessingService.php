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
use Illuminate\Support\Facades\Storage;

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
     * This separates parsing from file writing.
     *
     * @param string $fileContent
     * @param string $fileName
     * @return array|null Returns ['type' => '543', 'data' => [...]] or null.
     */
    public function parseFile(string $fileContent, string $fileName): ?array
    {
        $mtType = SwiftParserUtil::getMessageType($fileContent);

        if (!$mtType || !isset($this->parsers[$mtType])) {
            return null; 
        }

        $parser = new $this->parsers[$mtType]();
        $parsedData = $parser->parse($fileContent);

        // Add source filename for tracking
        $parsedData['_source_file'] = $fileName;

        return [
            'type' => $mtType,
            'data' => $parsedData,
        ];
    }
    
    /**
     * Generates CSV string content from an array of rows.
     * Includes sanitization to prevent "Array to string conversion" errors.
     *
     * @param array $rows
     * @return string
     */
    public function generateCsvContent(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        // Extract headers from the keys of the first row
        $headers = array_keys(reset($rows));
        
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, $headers);
        
        // Write rows
        foreach ($rows as $row) {
            // SANITIZATION STEP:
            // Check every item in the row. If it is an array or object, 
            // convert it to a JSON string. Otherwise keep it as is.
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