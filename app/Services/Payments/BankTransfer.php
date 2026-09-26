<?php

namespace App\Services\Payments;

use App\Models\Setting;

/** The account students pay into by bank transfer. Set by an admin in Console > Content > Site settings. */
class BankTransfer
{
    /** @return array{bank:string,number:string,name:string,note:?string}|null null until an admin has filled the account in */
    public static function details(): ?array
    {
        $bank = Setting::get('bank.name');
        $number = Setting::get('bank.account_number');
        $name = Setting::get('bank.account_name');

        return $bank && $number && $name ? ['bank' => $bank, 'number' => $number, 'name' => $name, 'note' => Setting::get('bank.note')] : null;
    }

    public static function configured(): bool
    {
        return self::details() !== null;
    }
}
