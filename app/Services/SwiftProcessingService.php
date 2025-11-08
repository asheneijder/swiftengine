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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Process a single .fin file content.
     *
     * @param string $fileContent
     * @param string $fileName The original filename (e.g., "msg1.fin")
     * @return string|null The path to the saved CSV file, or null on failure.
     */
    public function processFile(string $fileContent, string $fileName): ?string
    {
        $mtType = SwiftParserUtil::getMessageType($fileContent);

        if (!$mtType || !isset($this->parsers[$mtType])) {
            return null; // Unknown or unsupported MT type
        }

        /** @var SwiftMessageParser $parser */
        $parser = new $this->parsers[$mtType]();

        $parsedData = $parser->parse($fileContent);
        $csvContent = $parser->toCsv($parsedData);

        // --- FILENAME LOGIC ADJUSTED AS REQUESTED ---
        // Create a .csv extension from the original file name.
        // e.g., "msg1.fin" becomes "msg1.csv"
        if (Str::endsWith(strtolower($fileName), '.fin')) {
            $csvFileName = Str::beforeLast($fileName, '.fin') . '.csv';
        } else {
            // Fallback in case the file has a different or no extension
            $csvFileName = $fileName . '.csv';
        }
        // --- END OF ADJUSTMENT ---
       
        $outputPath = "mt{$mtType}/{$csvFileName}";

        Storage::disk('swift_outbound')->put($outputPath, $csvContent);

        return $outputPath;
    }
}