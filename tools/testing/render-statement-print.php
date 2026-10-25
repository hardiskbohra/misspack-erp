<?php
// Testing-only real Blade fixtures for browser/A4 verification.
if (getenv('APP_ENV') !== 'testing') { fwrite(STDERR, "Requires APP_ENV=testing.\n"); exit(1); }
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$f = \Tests\Support\StatementListDatabaseFixture::install();
auth()->setUser($f['user']);
$f['client']->update(['billing_address'=>'Client industrial road 123','billing_city'=>'Ahmedabad','billing_state'=>'Gujarat','billing_country'=>'India','billing_pincode'=>'380004']);
$f['vendor']->update(['address'=>'Vendor factory road 456','city'=>'Shenzhen','country'=>'China']);
app('url')->forceRootUrl('http://print.test');
$old = \App\Models\SalesInvoice::create(['invoice_number'=>'PRINT-OLD','invoice_type'=>'tax','status'=>'sent','currency'=>'INR',
    'invoice_date'=>'2025-04-04','client_id'=>$f['client']->id,'total_amount'=>290280]);
\App\Models\CashflowEntry::create(['entry_date'=>'2025-11-27','particular'=>'Historical receipt','currency'=>'INR',
    'client_id'=>$f['client']->id,'transaction_type'=>'credit','credit_amount'=>89584,
    'account_id'=>\App\Models\CashflowAccount::first()->id,'accounting_status'=>'booked']);
$out = $argv[1] ?? base_path('.work/statement-print');
if (!is_dir($out)) mkdir($out,0777,true);
$render = function ($name, $type, $id, $query, $show=false) use ($out) {
    $request = \Illuminate\Http\Request::create('/cashflows/statements/'.$type.'/'.$id.($show?'':'/print'),'GET',$query);
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request',$request);app('url')->setRequest($request);
    view()->share('errors',new \Illuminate\Support\ViewErrorBag);
    $controller=app(\App\Http\Controllers\PartyStatementController::class);
    $view=$show?$controller->show($request,$type,$id):$controller->print($request,$type,$id);
    file_put_contents($out.'/'.$name.'.html',$view->render());
};
$all=['period'=>'all','currency'=>'INR','ageing'=>'0'];
$render('show','client',$f['client']->id,$all,true);
$render('client-all','client',$f['client']->id,$all);
$render('client-custom','client',$f['client']->id,['period'=>'custom','date_from'=>'2025-01-01','date_to'=>'2025-12-31','currency'=>'INR','ageing'=>'0']);
$render('vendor-all','vendor',$f['vendor']->id,['period'=>'all','currency'=>'RMB','ageing'=>'0']);
$f['client']->update(['company_name'=>str_repeat('Long client name ',6),'billing_address'=>'Long address road 123, Business Park','billing_city'=>'Ahmedabad','billing_state'=>'Gujarat','billing_pincode'=>'380004']);
$old->update(['total_amount'=>123456789.12]);
for ($i=0;$i<70;$i++) \App\Models\SalesInvoice::create(['invoice_number'=>'PRINT-ROW-'.$i,'invoice_type'=>'tax','status'=>'sent',
    'currency'=>'INR','invoice_date'=>'2025-12-01','client_id'=>$f['client']->id,'total_amount'=>100]);
$render('client-large','client',$f['client']->id,$all);
echo "Rendered real app/print fixtures: All time, custom dates, vendor, large amounts and70 extra rows.\n";
