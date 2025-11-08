<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;

class Mt210Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block2 = SwiftParserUtil::getBlock($finContent, '2');
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];
        
        $sender = substr($block2, 4, 11);
        $receiver = substr($block2, 19, 11);

        $currencyAmount = SwiftParserUtil::parseCurrencyAmount(SwiftParserUtil::getTagValue($block4, '32B'));

        return [
            'Message Type' => 'MT210 (Notice to Receive)',
            'Sender' => $sender,
            'Receiver (Copy To)' => $receiver,
            'Sender\'s Reference' => SwiftParserUtil::getTagValue($block4, '20'),
            'Related Reference' => SwiftParserUtil::getTagValue($block4, '21'),
            'Expected Value Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '30')),
            'Expected Amount' => ($currencyAmount['currency'] ?? '') . ' ' . ($currencyAmount['amount'] ?? ''),
            'Receiving Bank' => SwiftParserUtil::getTagValue($block4, '52A'),
            'Sender\'s Correspondent' => SwiftParserUtil::getTagValue($block4, '56A'),
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}