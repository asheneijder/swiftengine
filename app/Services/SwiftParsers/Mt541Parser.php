<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;

class Mt541Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block2 = SwiftParserUtil::getBlock($finContent, '2');
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = substr($block2, 4, 11);
        $receiver = substr($block2, 19, 11);
        
        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $quantity = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'SETT'));

        return [
            'Message Type' => 'MT541 (Receive Free of Payment)',
            'Sender' => $sender,
            'Receiver (Copy To)' => $receiver,
            'Sender\'s Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Message Function' => SwiftParserUtil::getTagValue($block4, '23G'),
            'Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'SETT')),
            'Security (ISIN)' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Quantity' => ($quantity['quantity'] ?? '') . ' ' . ($quantity['type'] ?? ''),
            'Safekeeping Account' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
            'Delivering Agent' => SwiftParserUtil::getTagValue($block4, '95P', 'DEAG'),
            'Seller' => SwiftParserUtil::getTagValue($block4, '95P', 'SELL'),
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}