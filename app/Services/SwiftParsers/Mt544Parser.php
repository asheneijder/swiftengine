<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;
use App\Services\SwiftCodeTranslator;

class Mt544Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = SwiftParserUtil::getSenderBic($finContent);
        $receiver = SwiftParserUtil::getReceiverBic($finContent);
        
        // MT544 is Receive Free, so Payment Status is implicitly FREE, 
        // though it might not be explicitly present in field 22F::PAYM.
        
        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $quantity = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'ESTT')); // Using ESTT (Estimated) or SETT per sample
        
        // PSET: Place of Settlement
        $placeOfSettlement = SwiftParserUtil::getTagValue($block4, '95P', 'PSET'); 

        return [
            'Message Type' => 'MT544 (Receive Free)',
            'Sender (From)' => $sender,
            'Receiver (To)' => $receiver,
            'Sender Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Function' => SwiftCodeTranslator::translateFunction(SwiftParserUtil::getTagValue($block4, '23G')),
            'Preparation Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98C', 'PREP')),
            
            // Trade Details
            'Trade Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'TRAD')),
            'Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'ESET')), // Effective Settlement
            'Settlement Transaction' => SwiftCodeTranslator::translateSettlementType(SwiftParserUtil::getTagValue($block4, '22F', 'SETR')),
            
            // Financial Instrument
            'Security (ISIN)' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Quantity' => isset($quantity['quantity']) ? number_format((float)$quantity['quantity']) . ' Units' : null,
            
            // Parties
            'Seller' => SwiftParserUtil::getTagValue($block4, '95P', 'SELL') ?? SwiftParserUtil::getTagValue($block4, '95R', 'SELL'),
            'Custodian (Safe)' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
            'Place of Settlement' => $placeOfSettlement,
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}