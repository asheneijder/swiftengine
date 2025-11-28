<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;
use App\Services\SwiftCodeTranslator;

class Mt565Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $securityLines = SwiftParserUtil::getMultiLineTagValue($block4, '35B');
        $eligibleBal = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '93B', 'ELIG'));
        $instructedQty = SwiftParserUtil::parseQuantity(SwiftParserUtil::getTagValue($block4, '36B', 'QINS'));

        return [
            'Message Type' => 'MT565 (Corporate Action Instruction)',
            'Sender' => SwiftParserUtil::getSenderBic($finContent),
            'Receiver' => SwiftParserUtil::getReceiverBic($finContent),
            'Reference' => SwiftParserUtil::getTagValue($block4, '20C', 'SEME'),
            'Corp Action Ref' => SwiftParserUtil::getTagValue($block4, '20C', 'CORP'),
            'Function' => SwiftCodeTranslator::translateFunction(SwiftParserUtil::getTagValue($block4, '23G')),
            
            // Corporate Action Details
            'Event Type' => SwiftCodeTranslator::translateCorporateActionEvent(SwiftParserUtil::getTagValue($block4, '22F', 'CAEV')),
            'Previous Ref' => SwiftParserUtil::getTagValue($block4, '20C', 'PREV'), // Linkage
            'Linked Msg' => SwiftParserUtil::getTagValue($block4, '13A', 'LINK'),
            
            // Security
            'ISIN' => ltrim($securityLines[0] ?? '', 'ISIN '),
            'Security Name' => $securityLines[1] ?? null,
            'Safekeeping Account' => SwiftParserUtil::getTagValue($block4, '97A', 'SAFE'),
            
            // Instruction
            'Option' => SwiftCodeTranslator::translateCorporateActionOption(SwiftParserUtil::getTagValue($block4, '22F', 'CAOP')),
            'Eligible Balance' => number_format((float)($eligibleBal['quantity'] ?? 0)),
            'Quantity Instructed' => number_format((float)($instructedQty['quantity'] ?? 0)),
        ];
    }

    public function toCsv(array $data): string { return SwiftParserUtil::buildCsv($data); }
}