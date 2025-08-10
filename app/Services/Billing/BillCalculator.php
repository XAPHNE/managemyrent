<?php

namespace App\Services\Billing;

class BillCalculator
{
    public function calculate(
        float $waterCharge,
        float $electricityChargePerUnit,
        int $initialUnits,
        int $previousUnits,
        int $presentUnits,
        float $monthlyRent,
        float $advancePayment,
        float $securityDeposit
    ): float {
       // Correct consumption: present - previous (previous already considers initial baseline)
        $consumedUnits = max(0, $presentUnits - $previousUnits);

        $electricityBill = $consumedUnits * $electricityChargePerUnit;

        // Usually advance/security are not billed monthly—include only if that's your business rule.
        $total = $monthlyRent + $waterCharge + $electricityBill /* + $advancePayment + $securityDeposit */;

        return round($total, 2);
    }
}
