<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;

class Mt545Parser implements SwiftMessageParser
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
            'Message Type' => 'MT545 (Settlement Confirmation, Free of Payment)',
            'Sender' => $sender,
            'Receiver' => $receiver,
            'Sender\'s Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Linked Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'RELA'),
            'Effective Settlement Date' => SwiftParserUtil::formatSwiftDate(SwiftParserUtil::getTagValue($block4, '98A', 'ESET')),
            'Security (ISIN)' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Settled Quantity' => number_format((float)($quantity['quantity'] ?? 0)) . ' ' . ($quantity['type'] ?? ''),
            'Safekeeping Account' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
        ];
    }

    public function toCsv(array $data): string
    {
        return SwiftParserUtil::buildCsv($data);
    }
}