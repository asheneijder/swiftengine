<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;
use App\Services\SwiftCodeTranslator;

class Mt546Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = SwiftParserUtil::getSenderBic($finContent);
        $receiver = SwiftParserUtil::getReceiverBic($finContent);

        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $quantity = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'ESTT'));

        return [
            'Message Type' => 'MT546 (Deliver Free)',
            'Sender (From)' => $sender,
            'Receiver (To)' => $receiver,
            'Sender Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Function' => SwiftCodeTranslator::translateFunction(SwiftParserUtil::getTagValue($block4, '23G')),
            
            // Trade Details
            'Trade Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'TRAD')),
            'Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'ESET')),
            'Settlement Transaction' => SwiftCodeTranslator::translateSettlementType(SwiftParserUtil::getTagValue($block4, '22F', 'SETR')),
            
            // Financial Instrument
            'Security (ISIN)' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Quantity' => isset($quantity['quantity']) ? number_format((float)$quantity['quantity']) . ' Units' : null,
            
            // Parties
            'Buyer' => SwiftParserUtil::getTagValue($block4, '95P', 'BUYR') ?? SwiftParserUtil::getTagValue($block4, '95R', 'BUYR'),
            'Receiving Agent' => SwiftParserUtil::getTagValue($block4, '95P', 'REAG') ?? SwiftParserUtil::getTagValue($block4, '95R', 'REAG'),
            'Place of Settlement' => SwiftParserUtil::getTagValue($block4, '95P', 'PSET'),
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}