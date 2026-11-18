<x-mail::message>
# Invoice {{ $invoice->invoice_number }}

@foreach (preg_split("/\n{2,}/", trim($messageText)) as $paragraph)
{{ $paragraph }}

@endforeach

<x-mail::panel>
**Invoice** {{ $invoice->invoice_number }}
**Date** {{ $invoice->invoice_date ? $invoice->invoice_date->format('d M Y') : '—' }}
@if ($invoice->due_date)
**Due** {{ $invoice->due_date->format('d M Y') }}
@endif
**Total** {{ \App\Helpers\CommonHelper::amount($invoice->receivableTotal(), $invoice->currency) }}
</x-mail::panel>

@if ($publicLink)
<x-mail::button :url="$publicLink">
View the invoice
</x-mail::button>
@endif

Thanks,<br>
{{ $invoice->seller_company_name ?: config('app.name') }}
</x-mail::message>
