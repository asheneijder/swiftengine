<?php

namespace App\Services;

use Illuminate\Support\Str;

class SwiftCodeTranslator
{
    /**
     * Clean the code by removing any additional information following a slash.
     * e.g., "EXCH/XKUW" becomes "EXCH".
     */
    private static function cleanCode(?string $code): ?string
    {
        if ($code == null) {
            return null;
        }
        // If the code contains a slash, take the part before it.
        if (str_contains($code, '/')) {
            return Str::before($code, '/');
        }
        return $code;
    }

    public static function translateFunction(?string $code): ?string
    {
        $code = self::cleanCode($code);

        return match ($code) {
            'NEWM' => 'New Message',
            'CANC' => 'Cancellation Request',
            'PREA' => 'Preadvice',
            'REPL' => 'Replacement',
            'DUPL' => 'Duplicate',
            'RECO' => 'Reconciliation Only',
            default => $code,
        };
    }

    public static function translatePaymentStatus(?string $code): ?string
    {
        $code = self::cleanCode($code);

        return match ($code) {
            'APMT' => 'Against Payment',
            'FREE' => 'Free of Payment',
            default => $code,
        };
    }

    public static function translatePlaceOfTrade(?string $code): ?string
    {
        $code = self::cleanCode($code);

        return match ($code) {
            'EXCH' => 'Stock Exchange',
            'OTCO' => 'Over The Counter',
            'PRIM' => 'Primary Market',
            'SECM' => 'Secondary Market',
            'VARI' => 'Various',
            default => $code,
        };
    }

    public static function translateSettlementType(?string $code): ?string
    {
        $code = self::cleanCode($code);

        return match ($code) {
            'BSBK' => 'Buy Sell Back',
            'BYIY' => 'Buy In',
            'CLAI' => 'Market Claim',
            'CNCB' => 'Central Bank Collateral Operation',
            'COLI' => 'Collateral In',
            'COLO' => 'Collateral Out',
            'CONV' => 'DR Conversion',
            'ETFT' => 'Exchange Traded Funds',
            'FCTA' => 'Factor Update',
            'INSP' => 'Move of Stock',
            'INTT' => 'Traded Interest Changed',
            'ISSU' => 'Issuance',
            'MKDW' => 'Mark-Down',
            'MKUP' => 'Mark-Up',
            'NETT' => 'Netting',
            'NSYN' => 'Non Syndicated',
            'OWNE' => 'External Account Transfer',
            'OWNI' => 'Internal Account Transfer',
            'PAIR' => 'Pair-Off',
            'PLAC' => 'Placement',
            'PORT' => 'Portfolio Move',
            'REAL' => 'Realignment',
            'REDI' => 'Withdrawal',
            'REDM' => 'Redemption (Funds)',
            'RELE' => 'DR Release/Cancellation',
            'REPU' => 'Repo',
            'RODE' => 'Return of Delivery Without Matching',
            'RVPO' => 'Reverse Repo',
            'SBBK' => 'Sell Buy Back',
            'SBRE' => 'Borrowing Reallocation',
            'SECB' => 'Securities Borrowing',
            'SECL' => 'Securities Lending',
            'SLRE' => 'Lending Reallocation',
            'SUBS' => 'Subscription (Funds)',
            'SWIF' => 'Switch From',
            'SWIT' => 'Switch To',
            'SYND' => 'Syndicate of Underwriters',
            'TBAC' => 'TBA Closing',
            'TRAD' => 'Trade',
            'TRPO' => 'Triparty Repo',
            'TRVO' => 'Triparty Reverse Repo',
            'TURN' => 'Turnaround',
            'REDE' => 'Redemption', // General Fallback
            'SUBC' => 'Subscription', // General Fallback
            default => $code,
        };
    }
}