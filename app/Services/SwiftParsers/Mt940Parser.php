<?php

namespace App\Services\SwiftParsers;

use App\Services\SwiftParserUtil;

class Mt940Parser implements SwiftMessageParser
{
    public function parse(string $finContent): array
    {
        $block4 = SwiftParserUtil::getBlock($finContent, '4');
        if (!$block4) return [];

        $sender = SwiftParserUtil::getSenderBic($finContent);
        $receiver = SwiftParserUtil::getReceiverBic($finContent);

        $summary = [
            'Message Type' => 'MT940 (Customer Statement)',
            'Sender' => $sender,
            'Receiver' => $receiver,
            'Statement Date' => substr(SwiftParserUtil::getTagValue($block4, '20'), -8, 8), // Assuming YYYYMMDD at end
            'Account' => SwiftParserUtil::getTagValue($block4, '25'),
            'Opening Balance' => SwiftParserUtil::getTagValue($block4, '60F'),
            'Closing Balance' => SwiftParserUtil::getTagValue($block4, '62F'),
            'Available Balance' => SwiftParserUtil::getTagValue($block4, '64'),
        ];
        
        $transactions = [];
        // Regex to find all :61: tags and their following :86: tags
        $pattern = "/^:61:(.*?)\n(:86:(.*?)\n)?/ms";
        
        if (preg_match_all($pattern, $block4, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tag61 = $match[1];
                $tag86 = $match[3] ?? ''; // :86: is optional

                // Parse :61: 250712 0712 D 12550000.00 NTRF //PARB/SET/99A45
                $tx = [];
                if (preg_match('/^(\d{6})(\d{4})?([DC])([0-9,.]+)([A-Z]{4})(.*?)$/', $tag61, $txMatch)) {
                    $tx['Date'] = SwiftParserUtil::formatSwiftDate($txMatch[1]);
                    $tx['Type'] = ($txMatch[3] === 'D' ? 'Debit' : 'Credit');
                    $tx['Amount (MYR)'] = str_replace(',', '', $txMatch[4]);
                    $tx['Reference'] = trim(substr($txMatch[6], 2)); // Get text after //
                    $tx['Description'] = str_replace(["\r", "\n"], ' ', $tag86); // Clean up newlines in description
                    
                    $transactions[] = $tx;
                }
            }
        }
        
        return [
            'summary' => $summary,
            'transactions' => $transactions,
        ];
    }

    public function toCsv(array $data): string
    {
        // For MT940, we return the CSV of the transactions, as per your example
        $headers = ['Date', 'Type', 'Amount (MYR)', 'Reference', 'Description'];
        return SwiftParserUtil::buildMultiRowCsv($headers, $data['transactions']);
    }
}