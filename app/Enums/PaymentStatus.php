<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Initialized = 'initialized';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
