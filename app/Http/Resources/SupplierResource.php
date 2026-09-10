<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'trade_name' => $this->trade_name,
            'business_structure' => $this->business_structure,
            'provides_regulated_health_products' => $this->provides_regulated_health_products,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'tax_number' => $this->tax_number,
            'billing_address' => $this->billing_address,
            'delivery_address' => $this->delivery_address,
            'status' => $this->status?->value ?? $this->status,
            'accreditation_status' => $this->effectiveAccreditationStatus()->value,
            'accreditation_expires_at' => $this->accreditation_expires_at?->toDateString(),
            'procurement_eligible' => $this->isProcurementEligible(),
            'standard_lead_time_days' => $this->standard_lead_time_days,
            'payment_terms' => $this->payment_terms,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
