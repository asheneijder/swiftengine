<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;
use App\Services\SwiftCodeTranslator;

class Mt542Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $quantity = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'SETT'));

        return [
            'Message Type' => 'MT542 (Deliver Free)',
            'Sender' => SwiftParserUtil::getSenderBic($finContent),
            'Receiver' => SwiftParserUtil::getReceiverBic($finContent),
            'Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Function' => SwiftCodeTranslator::translateFunction(SwiftParserUtil::getTagValue($block4, '23G')),
            
            'Place of Trade' => SwiftCodeTranslator::translatePlaceOfTrade(SwiftParserUtil::getTagValue($block4, '94B', 'TRAD')),
            'Trade Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'TRAD')),
            'Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'SETT')),
            
            'ISIN' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Quantity' => number_format((float)($quantity['quantity'] ?? 0)),
            
            'Settlement Condition' => SwiftCodeTranslator::translateSettlementCondition(SwiftParserUtil::getTagValue($block4, '22F', 'STCO')),
            
            // Parties
            'Buyer' => SwiftParserUtil::getTagValue($block4, '95P', 'BUYR') ?? SwiftParserUtil::getTagValue($block4, '95R', 'BUYR'),
            'Receiving Agent' => SwiftParserUtil::getTagValue($block4, '95P', 'REAG') ?? SwiftParserUtil::getTagValue($block4, '95R', 'REAG'),
            'Place of Settlement' => SwiftParserUtil::getTagValue($block4, '95P', 'PSET'),
            'Safekeeping Account' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
        ];
    }

    public function toCsv(array $data): string { return SwiftParserUtil::buildCsv($data); }
}