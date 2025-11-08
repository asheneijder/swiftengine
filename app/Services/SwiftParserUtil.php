<?php

namespace App\Services;

class SwiftParserUtil
{
    /**
     * Extracts the content of a specific block (e.g., {1:..}, {2:..}, {4:...-})
     * @param string $finContent
     * @param string $blockNumber (e.g., '1', '2', '3', '4')
     * @return string|null
     */
    public static function getBlock(string $finContent, string $blockNumber): ?string
    {
        $pattern = "/\{" . $blockNumber . ":(.*?)\}(?=\s*\{|$)/s";
        if (preg_match($pattern, $finContent, $matches)) {
            // For block 4, trim the trailing newlines and the final '-'
            if ($blockNumber === '4') {
                return rtrim($matches[1], "\r\n- ");
            }
            return $matches[1];
        }
        return null;
    }

    /**
     * Extracts the Message Type (e.g., 543) from Block 2.
     * @param string $finContent
     * @return string|null
     */
    public static function getMessageType(string $finContent): ?string
    {
        $block2 = self::getBlock($finContent, '2');
        if ($block2 && preg_match('/^[IO]([0-9]{3})/', $block2, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extracts the Sender BIC from Block 1.
     * e.g., {1:F01PNBMMYKLXXX...} -> PNBMMYKLXXX
     * @param string $finContent
     * @return string|null
     */
    public static function getSenderBic(string $finContent): ?string
    {
        $block1 = self::getBlock($finContent, '1');
        // {1:F01<BIC_11>...}
        if ($block1 && preg_match('/^[F0-9]{3}([A-Z0-9]{8,11})/', $block1, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extracts the Receiver BIC from Block 2.
     * e.g., {2:O543...AMTBMYKLXXX...} -> AMTBMYKLXXX
     * @param string $finContent
     * @return string|null
     */
    public static function getReceiverBic(string $finContent): ?string
    {
        $block2 = self::getBlock($finContent, '2');
        // {2:O<MT><Time><Date><ReceiverBIC>...
        // {2:O 543 1405 250710 AMTBMYKLXXX ...
        if ($block2 && preg_match('/^[IO][0-9]{3}[0-9]{10}([A-Z0-9]{8,11})/', $block2, $matches)) {
            return $matches[1]; // This should get AMTBMYKLXXX
        }
        return null;
    }


    /**
     * Gets the value of a specific tag from Block 4.
     * Handles single-line, multi-line, and qualified/unqualified tags.
     *
     * Examples:
     * getTagValue($block4, '20C', 'SEME') => 'PNB/20250710/A538'
     * getTagValue($block4, '35B') => "ISIN MY0005220002\nMAYBANK"
     * getTagValue($block4, '50K') => "/123456789012\nPERMODALAN NASIONAL BERHAD\nMENARA PNB, JALAN TUN RAZAK\nKUALA LUMPUR"
     *
     * @param string $block4Content
     * @param string $tag (e.g., '20C', '35B', '50K')
     * @param string|null $qualifier (e.g., 'SEME', 'TRAD')
     * @return string|null
     */
    public static function getTagValue(string $block4Content, string $tag, string $qualifier = null): ?string
    {
        $tagPattern = preg_quote($tag, '/');
        $qualifierPattern = $qualifier ? '::' . preg_quote($qualifier, '/') . '//' : '(?:::(.*?)\/\/)?';
        
        // This regex looks for the tag, captures its value (non-greedily),
        // and stops at the *next* tag (which starts with :) or the end of the block.
        $pattern = "/:{$tagPattern}:{$qualifierPattern}(.*?)(?=^:|\Z)/ms";

        if ($qualifier) {
            // Specific qualifier
            $pattern = "/:{$tagPattern}::" . preg_quote($qualifier, '/') . "\/\/(.*?)(?=^:|\Z)/ms";
        } else {
            // Any qualifier or no qualifier
            $pattern = "/:{$tagPattern}:(.*?)(?=^:|\Z)/ms";
            
            // If we are looking for a tag that *has* no qualifier (like 35B or 50K)
            // we need to adjust the pattern to not capture the qualifier part.
            if (preg_match("/:{$tagPattern}:(::.*?)\/\/(.*?)(?=^:|\Z)/ms", $block4Content)) {
                 // Tag has a qualifier, but we weren't given one.
                 // This is ambiguous, so for simplicity, let's grab the first one.
                 // This logic can be refined if needed.
                 $pattern = "/:{$tagPattern}:(.*?)(?=^:|\Z)/ms";
            } else {
                 // Tag has no qualifier (e.g., :35B:ISIN...)
                 $pattern = "/:{$tagPattern}:(.*?)(?=^:|\Z)/ms";
            }
        }

        if (preg_match($pattern, $block4Content, $matches)) {
            $value = trim($matches[1]);
            
            // Special handling for tags *without* qualifiers (like 35B, 50K)
            // where the first part (e.g. ::SEME//) is actually part of the value
            if (!$qualifier && preg_match('/^::(.*?)\/\/(.*)/s', $value, $qualifierMatches)) {
                 // This is a qualified tag, but we were called without a qualifier.
                 // Let's return the content after the qualifier.
                 return trim($qualifierMatches[2]);
            }
            
            return $value;
        }

        return null;
    }

    /**
     * Specifically for multi-line tags like 50K or 59 where the first line
     * starts with a '/' or is just the BIC.
     * * @param string $block4Content
     * @param string $tag
     * @return array
     */
    public static function getMultiLineTagValue(string $block4Content, string $tag): array
    {
        $value = self::getTagValue($block4Content, $tag);
        if ($value === null) {
            return [];
        }
        return explode("\n", str_replace("\r", '', $value));
    }

    /**
     * Parses a :32A: or :32B: tag (Date, Currency, Amount)
     * @param string $value (e.g., '250714USD500000.00' or 'USD1250000.00')
     * @return array
     */
    public static function parseCurrencyAmount(string $value): array
    {
        $date = null;
        $currency = null;
        $amount = null;

        // Matches 32A (Date, Currency, Amount)
        if (preg_match('/^([0-9]{6})([A-Z]{3})([0-9,.]+)$/', $value, $matches)) {
            $date = $matches[1];
            $currency = $matches[2];
            $amount = $matches[3];
        } 
        // Matches 32B (Currency, Amount)
        elseif (preg_match('/^([A-Z]{3})([0-9,.]+)$/', $value, $matches)) {
            $currency = $matches[1];
            $amount = $matches[2];
        }

        return [
            'date' => $date ? self::formatSwiftDate($date) : null,
            'currency' => $currency,
            'amount' => str_replace(',', '', $amount),
        ];
    }
    
    /**
     * Parses a :90B: tag (Qualifier, AmountType, Currency, Price)
     * @param string $value (e.g., 'ACTU/MYR125.50')
     * @return array
     */
    public static function parsePrice(string $value): array
    {
        if (preg_match('/^([A-Z]{4})\/([A-Z]{3})([0-9,.]+)$/', $value, $matches)) {
            return [
                'type' => $matches[1], // e.g., ACTU
                'currency' => $matches[2], // e.g., MYR
                'price' => str_replace(',', '', $matches[3]), // e.g., 125.50
            ];
        }
        return [];
    }

    /**
     * Parses a :36B: tag (Qualifier, QuantityType, Quantity)
     * @param string $value (e.g., 'UNIT/100000')
     * @return array
     */
    public static function parseQuantity(string $value): array
    {
        if (preg_match('/^([A-Z]{4})\/([0-9,.]+)$/', $value, $matches)) {
            return [
                'type' => $matches[1], // e.g., UNIT
                'quantity' => str_replace(',', '', $matches[2]), // e.g., 100000
            ];
        }
        return [];
    }

    /**
     * Formats a 6-digit SWIFT date (YYMMDD) to YYYY-MM-DD
     * Assumes 20xx
     * @param string $date (e.g., 250710)
     * @return string (e.g., 2025-07-10)
     */
    public static function formatSwiftDate(string $date): string
    {
        if (strlen($date) === 6) {
            return '20' . substr($date, 0, 2) . '-' . substr($date, 2, 2) . '-' . substr($date, 4, 2);
        }
        return $date;
    }
    
    /**
     * Builds a CSV string from headers and data in a horizontal format.
     * @param array $data (associative array, e.g., ['Header 1' => 'Value 1', ...])
     * @return string
     */
    public static function buildCsv(array $data): string
    {
        $csvData = [];
        // Add Header
        $csvData[] = '"' . implode('","', array_keys($data)) . '"';
        
        // Add Row
        $values = [];
        foreach ($data as $value) {
            // Escape double quotes within the value
            $value = str_replace('"', '""', $value ?? '');
            $values[] = $value;
        }
        $csvData[] = '"' . implode('","', $values) . '"';
        
        return implode("\n", $csvData);
    }

    /**
     * Builds a CSV string for multi-row data (like MT940).
     * @param array $headers (e.g., ['Date', 'Type', ...])
     * @param array $rows (array of associative arrays)
     * @return string
     */
    public static function buildMultiRowCsv(array $headers, array $rows): string
    {
        $csvLines = [];
        $csvLines[] = '"' . implode('","', $headers) . '"';

        foreach ($rows as $row) {
            $values = [];
            foreach ($headers as $header) {
                // Ensure value exists, is not null, and escape double quotes
                $value = $row[$header] ?? '';
                $value = str_replace('"', '""', $value);
                $values[] = $value;
            }
            $csvLines[] = '"' . implode('","', $values) . '"';
        }
        
        return implode("\n", $csvLines);
    }
}