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
        if ($code === null) {
            return null;
        }
        if (str_contains($code, '/')) {
            return Str::before($code, '/');
        }
        return trim($code);
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
            'CODU' => 'Copy Duplicate',
            'COPY' => 'Copy',
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
            'TRAD' => 'Trade',
            'TRAN' => 'Transfer',
            'REDE' => 'Redemption',
            'SUBS' => 'Subscription',
            'SECL' => 'Securities Lending',
            'SECB' => 'Securities Borrowing',
            'REPU' => 'Repo',
            'RVPO' => 'Reverse Repo',
            'TRPO' => 'Triparty Repo',
            'TRVO' => 'Triparty Reverse Repo',
            'SBBK' => 'Sell Buy Back',
            'BSBK' => 'Buy Sell Back',
            'COLL' => 'Collateral',
            'CORP' => 'Corporate Action',
            'CLAI' => 'Market Claim',
            'CNCB' => 'Central Bank Collateral Operation',
            'ISSU' => 'Issuance',
            'REAL' => 'Realignment',
            'NETT' => 'Netting',
            'PORT' => 'Portfolio Move',
            'OWNI' => 'Internal Account Transfer',
            'OWNE' => 'External Account Transfer',
            'PAIR' => 'Pair-Off',
            'TURN' => 'Turnaround',
            'INSP' => 'Move of Stock',
            'CONV' => 'DR Conversion',
            'RELE' => 'DR Release/Cancellation',
            default => $code,
        };
    }
}