<?php

namespace App\Services;

// ashraf azmi - 2025-11-08
class SwiftCodeTranslator
{
    public static function translateFunction(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return match ($code) {
            'NEWM' => 'New Message',
            'CANC' => 'Cancellation',
            'REPL' => 'Replacement',
            'DUPL' => 'Duplicate',
            default => $code,
        };
    }

    public static function translatePaymentStatus(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return match ($code) {
            'APMT' => 'Against Payment',
            'FREE' => 'Free of Payment',
            default => $code,
        };
    }

    public static function translateSettlementType(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return match ($code) {
            'TRAD' => 'Trade',
            'TRAN' => 'Transfer',
            'REDE' => 'Redemption',
            'SUBC' => 'Subscription',
            default => $code,
        };
    }
}