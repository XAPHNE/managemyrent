<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

class UpiQrService
{
    public function generate(string $vpa, string $name, float $amount, string $note): array
    {
        $amount = number_format($amount, 2, '.', '');
        $upi = "upi://pay?pa={$vpa}&pn=".urlencode($name)."&am={$amount}&cu=INR&tn=".urlencode($note);

        $file = 'qrs/'.Str::uuid().'.png';
        // store to storage/app/public/qrs
        Storage::disk('public')->put($file, QrCode::format('png')->size(400)->generate($upi));

        return ['intent' => $upi, 'path' => $file];
    }
}
