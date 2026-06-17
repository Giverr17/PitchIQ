<?php

namespace App\Enums;

enum TokenTransactionType: string
{
    case Earned     = 'earned';
    case Spent      = 'spent';
    case Bonus      = 'bonus';
    case Withdrawal = 'withdrawal';
    case Purchase   = 'purchase';

    public function label(): string
    {
        return match($this) {
            self::Earned     => 'Earned',
            self::Spent      => 'Spent',
            self::Bonus      => 'Bonus',
            self::Withdrawal => 'Withdrawal',
            self::Purchase   => 'Purchase',
        };
    }

    /** True if this transaction adds tokens to the user's balance */
    public function isCredit(): bool
    {
        return match($this) {
            self::Earned, self::Bonus, self::Purchase => true,
            self::Spent, self::Withdrawal             => false,
        };
    }
}
