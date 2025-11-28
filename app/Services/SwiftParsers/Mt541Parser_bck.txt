<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;
use App\Services\SwiftCodeTranslator;

class Mt541Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = SwiftParserUtil::getSenderBic($finContent);
        $receiver = SwiftParserUtil::getReceiverBic($finContent);
        
        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $quantity = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'SETT'));
        $func = SwiftParserUtil::getTagValue($block4, '23G');

        return [
            'Message Type' => 'MT541 (Receive Free of Payment)',
            'Sender' => $sender,
            'Receiver (Copy To)' => $receiver,
            'Sender\'s Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Message Function' => SwiftCodeTranslator::translateFunction($func),
            'Payment Status' => SwiftCodeTranslator::translatePaymentStatus(SwiftParserUtil::getTagValue($block4, '22F', 'PAYM')),
            'Settlement Transaction Type' => SwiftCodeTranslator::translateSettlementType(SwiftParserUtil::getTagValue($block4, '22F', 'SETR')),
            'Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'SETT')),
            'Security (ISIN)' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Quantity' => number_format((float)($quantity['quantity'] ?? 0)) . ' ' . ($quantity['type'] ?? ''),
            'Safekeeping Account' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
            'Receiving Agent' => SwiftParserUtil::getTagValue($block4, '95P', 'REAG'),
            'Delivering Agent' => SwiftParserUtil::getTagValue($block4, '95P', 'DEAG'),
            'Seller' => SwiftParserUtil::getTagValue($block4, '95P', 'SELL'),
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}