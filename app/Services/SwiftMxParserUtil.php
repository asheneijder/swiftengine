<?php

namespace App\Services;

class SwiftMxParserUtil
{
    /**
     * Detects if the content is an XML/MX message.
     */
    public static function isXml(string $content): bool
    {
        return str_starts_with(trim($content), '<');
    }

    /**
     * Parses the XML string into a SimpleXMLElement.
     * Strategies:
     * 1. Attempt strict parsing.
     * 2. If fails (often due to prefixes), try cleaning namespaces for easier access.
     */
    public static function parseXml(string $content): ?\SimpleXMLElement
    {
        // 1. Clean namespaces to simplify access (remove 'ns:', 'Saa:', etc.)
        // This regex removes the xmlns declarations and tag prefixes for flat access
        $cleanXml = preg_replace('/(xmlns:?[^=]*=["\"][^"\"]*["\"])/', '', $content); 
        $cleanXml = preg_replace('/[a-zA-Z0-9]+:/', '', $cleanXml); 

        try {
            return simplexml_load_string($cleanXml);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract the Message Definition Identifier (e.g., pacs.008.001.08)
     */
    public static function getMxMessageType(\SimpleXMLElement $xml): ?string
    {
        // Check AppHdr (Business Header)
        if (isset($xml->AppHdr->MsgDefIdr)) {
            return (string)$xml->AppHdr->MsgDefIdr;
        }
        
        // Check SAA Header (Header -> Message -> MessageIdentifier)
        if (isset($xml->Header->Message->MessageIdentifier)) {
            return (string)$xml->Header->Message->MessageIdentifier;
        }

        // Check SAA Header variation (Messageldentifier with 'l' typo common in some PDFs)
        if (isset($xml->Header->Message->Messageldentifier)) {
            return (string)$xml->Header->Message->Messageldentifier;
        }

        return 'pacs.008'; // Default fallback if structure matches but ID missing
    }

    /**
     * Helper to safely get a string value from an XML node, or null if missing.
     */
    public static function get(mixed $node): ?string
    {
        return (!empty($node)) ? trim((string)$node) : null;
    }
    
    /**
     * Helper to format an Address block into a single string.
     */
    public static function formatAddress(mixed $postalAddress): ?string
    {
        if (!$postalAddress) return null;
        
        $parts = [];
        
        // Structured
        if (!empty($postalAddress->StrtNm)) $parts[] = self::get($postalAddress->StrtNm);
        if (!empty($postalAddress->BldgNb)) $parts[] = self::get($postalAddress->BldgNb);
        if (!empty($postalAddress->PstCd))  $parts[] = self::get($postalAddress->PstCd);
        if (!empty($postalAddress->TwnNm))  $parts[] = self::get($postalAddress->TwnNm);
        if (!empty($postalAddress->Ctry))   $parts[] = self::get($postalAddress->Ctry);
        
        // Unstructured
        if (!empty($postalAddress->AdrLine)) {
            foreach ($postalAddress->AdrLine as $line) {
                $parts[] = self::get($line);
            }
        }
        
        return empty($parts) ? null : implode(', ', $parts);
    }
}