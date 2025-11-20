<?php

namespace App\Services;

class SwiftParserUtil
{
    public static function getBlock(string $finContent, string $blockNumber): ?string
    {
        $pattern = "/\{" . $blockNumber . ":(.*?)\}(?=\s*\{|$)/s";
        if (preg_match($pattern, $finContent, $matches)) {
            if ($blockNumber == '4') {
                return rtrim($matches[1], "\r\n- ");
            }
            return $matches[1];
        }
        return null;
    }

    public static function getMessageType(string $finContent): ?string
    {
        $block2 = self::getBlock($finContent, '2');
        if ($block2 && preg_match('/^[IO]([0-9]{3})/', $block2, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public static function getSenderBic(string $finContent): ?string
    {
        $block1 = self::getBlock($finContent, '1');
        if ($block1 && preg_match('/^[F0-9]{3}([A-Z0-9]{8,11})/', $block1, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public static function getReceiverBic(string $finContent): ?string
    {
        $block2 = self::getBlock($finContent, '2');
        if ($block2 && preg_match('/^[IO][0-9]{3}[0-9]{4}[0-9]{6}([A-Z0-9]{8,11})/', $block2, $matches)) {
            return $matches[1];
        }
        if ($block2 && preg_match('/^[IO][0-9]{3}.*?([A-Z0-9]{8,11})/', $block2, $matches)) {
             return $matches[1];
        }
        return null;
    }

    public static function getMessageDate(string $finContent): ?string
    {
        $block2 = self::getBlock($finContent, '2');
        if ($block2 && preg_match('/^[IO][0-9]{3}[0-9]{4}([0-9]{6})/', $block2, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get all occurrences of a specific tag (useful for repetitive tags like 61).
     */
    public static function getAllTagValues(string $block4Content, string $tag): array
    {
        $tagPattern = preg_quote($tag, '/');
        // Matches :61:......
        $pattern = "/:{$tagPattern}:(.*?)(?=^:|\Z)/ms";
        
        if (preg_match_all($pattern, $block4Content, $matches)) {
            return array_map('trim', $matches[1]);
        }
        return [];
    }

    public static function getTagValue(string $block4Content, string $tag, string $qualifier = null): ?string
    {
        $tagPattern = preg_quote($tag, '/');
        $qualifierPattern = $qualifier ? '::' . preg_quote($qualifier, '/') . '//' : '(?:::(.*?)\/\/)?';
        
        if ($qualifier) {
            $pattern = "/:{$tagPattern}::" . preg_quote($qualifier, '/') . "\/\/(.*?)(?=^:|\Z)/ms";
        } else {
            $pattern = "/:{$tagPattern}:(.*?)(?=^:|\Z)/ms";
            if (preg_match("/:{$tagPattern}:(::.*?)\/\/(.*?)(?=^:|\Z)/ms", $block4Content)) {
                 $pattern = "/:{$tagPattern}:(.*?)(?=^:|\Z)/ms";
            }
        }

        if (preg_match($pattern, $block4Content, $matches)) {
            $value = trim($matches[1]);
            if (!$qualifier && preg_match('/^::(.*?)\/\/(.*)/s', $value, $qualifierMatches)) {
                 return trim($qualifierMatches[2]);
            }
            return $value;
        }
        return null;
    }

    public static function getMultiLineTagValue(string $block4Content, string $tag): array
    {
        $value = self::getTagValue($block4Content, $tag);
        if ($value == null) {
            return [];
        }
        return explode("\n", str_replace("\r", '', $value));
    }

    public static function parseCurrencyAmount(string $value): array
    {
        $date = null;
        $currency = null;
        $amount = null;

        if (preg_match('/^([0-9]{6})([A-Z]{3})([0-9,.]+)$/', $value, $matches)) {
            $date = $matches[1];
            $currency = $matches[2];
            $amount = $matches[3];
        } elseif (preg_match('/^([A-Z]{3})([0-9,.]+)$/', $value, $matches)) {
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
     * Parses Balance tags (60F, 62F, 64, 65)
     * Format: 1!a6!n3!a15d (D/C mark, Date, Currency, Amount)
     * Example: C101025USD1000,50
     */
    public static function parseBalance(string $value): array
    {
        // C or D + Date(6) + Currency(3) + Amount
        if (preg_match('/^([CD])([0-9]{6})([A-Z]{3})([0-9,.]+)$/', $value, $matches)) {
            return [
                'mark' => $matches[1] == 'C' ? 'Credit' : 'Debit',
                'date' => self::formatSwiftDate($matches[2]),
                'currency' => $matches[3],
                'amount' => str_replace(',', '.', str_replace('.', '', $matches[4])), // Handle 1.000,00 vs 1000.00
            ];
        }
        return [];
    }

    public static function parsePrice(string $value): array
    {
        if (preg_match('/^([A-Z]{4})\/([A-Z]{3})([0-9,.]+)$/', $value, $matches)) {
            return [
                'type' => $matches[1],
                'currency' => $matches[2],
                'price' => str_replace(',', '', $matches[3]),
            ];
        }
        return [];
    }

    public static function parseQuantity(string $value): array
    {
        if (preg_match('/^([A-Z]{4})\/([0-9,.]+)$/', $value, $matches)) {
            return [
                'type' => $matches[1],
                'quantity' => str_replace(',', '', $matches[2]),
            ];
        }
        return [];
    }

    public static function formatSwiftDate(string $date): string
    {
        if (strlen($date) == 6) {
            return '20' . substr($date, 0, 2) . '-' . substr($date, 2, 2) . '-' . substr($date, 4, 2);
        }
        return $date;
    }
    
    public static function buildCsv(array $data): string
    {
        $csvData = [];
        $csvData[] = '"' . implode('","', array_keys($data)) . '"';
        
        $values = [];
        foreach ($data as $value) {
            $value = str_replace('"', '""', $value ?? '');
            $values[] = $value;
        }
        $csvData[] = '"' . implode('","', $values) . '"';
        
        return implode("\n", $csvData);
    }
}