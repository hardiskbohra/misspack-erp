<?php

namespace App\Services;

use App\Models\Client;

/** One projection for preselected clients, selector options and inline creation. */
class ClientInvoiceSnapshot
{
    public static function from(Client $client): array
    {
        return [
            'client_company_name' => $client->company_name ?? null,
            'client_brand_name' => $client->brand_name ?? null,
            'client_contact_name' => $client->account_person_name ?: ($client->ceo_name ?? null),
            'client_email' => $client->account_person_email ?: ($client->ceo_email ?? null),
            'client_mobile' => $client->account_person_contact ?: ($client->ceo_contact ?? null),
            'client_gstin' => $client->gstin ?? null,
            'client_pan' => $client->pan ?? null,
            'billing_address' => $client->billing_address ?? null,
            'billing_city' => $client->billing_city ?? null,
            'billing_state' => $client->billing_state ?? null,
            'billing_country' => $client->billing_country ?? null,
            'billing_pincode' => $client->billing_pincode ?? null,
            'shipping_address' => $client->shipping_address ?: ($client->billing_address ?? null),
            'shipping_city' => $client->shipping_city ?: ($client->billing_city ?? null),
            'shipping_state' => $client->shipping_state ?: ($client->billing_state ?? null),
            'shipping_country' => $client->shipping_country ?: ($client->billing_country ?? null),
            'shipping_pincode' => $client->shipping_pincode ?: ($client->billing_pincode ?? null),
            'place_of_supply' => $client->billing_state ?? null,
        ];
    }
}
