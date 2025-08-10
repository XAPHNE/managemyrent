<x-mail::message>
# Hello {{ $tenantName }}

Your monthly bill is ready.

**Total Amount:** ₹{{ $totalAmount }}

<x-mail::button :url="''">
Scan the QR below to pay:
</x-mail::button>

![QR Code]({{ $qrCodeUrl }})

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
