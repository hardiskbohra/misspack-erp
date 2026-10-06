<x-mail::message>
# {{ $alert->title }}

{{ $alert->body }}

@if ($alert->action_url)
<x-mail::button :url="$alert->action_url">
{{ $alert->action_label ?: 'Open in MissPack' }}
</x-mail::button>
@endif

This stays in the office bell until someone marks it read for the whole desk.

Thanks,<br>
MissPack
</x-mail::message>
