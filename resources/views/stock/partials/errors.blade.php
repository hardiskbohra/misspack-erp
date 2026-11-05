@if ($errors->any())
    <div class="stock-alert stock-alert--warning" role="alert">
        <strong>Please review the following:</strong>
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
