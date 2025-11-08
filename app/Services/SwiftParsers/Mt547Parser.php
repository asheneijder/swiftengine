<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;

class Mt547Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = SwiftParserUtil::getSenderBic($finContent); // e.g., PARBMYKLXXX
        $receiver = SwiftParserUtil::getReceiverBic($finContent); // e.g., AMTBMYKLXXX

        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $quantity = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'ESTT'));
        $settlementAmount = SwiftParserUtil::parseCurrencyAmount(SwiftParserUtil::getTagValue($block4, '19A', 'ESTT'));

        return [
            'Message Type' => 'MT547 (Settlement Confirmation, Against Payment)',
            'Sender' => $sender,
            'Receiver' => $receiver,
            'Sender\'s Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Linked Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'RELA'),
            'Trade Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'TRAD')),
            'Effective Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'ESET')),
            'Security (ISIN)' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Settled Quantity' => number_format((float)($quantity['quantity'] ?? 0)) . ' Units',
            'Settled Amount' => ($settlementAmount['currency'] ?? '') . ' ' . number_format((float)($settlementAmount['amount'] ?? 0), 2, '.', ','),
            'Safekeeping Account' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
            'Value Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'VALU')),
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}