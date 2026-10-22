<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="theme-color" content="#12342d">
<title>@yield('title', 'Vendor Workspace') | MissPack</title>
<script>document.documentElement.dataset.theme=localStorage.getItem('misspack-vendor-theme')||'light';</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="{{ $assetVer('assets/css/core.css') }}"><link rel="stylesheet" href="{{ $assetVer('assets/css/master-form.css') }}"><link rel="stylesheet" href="{{ $assetVer('assets/css/master-alert.css') }}"><link rel="stylesheet" href="{{ $assetVer('assets/css/vendor-portal.css') }}">
</head>
<body class="vp-body">
@php
    $vpUser = $vendorPortalUser;
    $vpVendor = $vendorPortalVendor;
@endphp
<div class="vp-shell">
<aside class="vp-sidebar"><a class="vp-brand" href="{{ route('vendor-portal.dashboard') }}"><strong>MissPack</strong><small>VENDOR WORKSPACE</small></a><div class="vp-company"><span>{{ strtoupper(substr($vpVendor->vendor_name,0,1)) }}</span><div><small>SUPPLIER ACCOUNT</small><strong>{{ $vpVendor->vendor_name }}</strong></div></div><nav><a class="{{ request()->routeIs('vendor-portal.dashboard') ? 'active' : '' }}" href="{{ route('vendor-portal.dashboard') }}"><i class="fa-solid fa-house"></i> Overview</a><a href="{{ route('vendor-portal.dashboard') }}#purchaseOrders"><i class="fa-solid fa-file-signature"></i> Purchase orders</a><a href="{{ route('vendor-portal.dashboard') }}#vendorBills"><i class="fa-solid fa-file-invoice-dollar"></i> Vendor bills</a><a href="{{ route('vendor-portal.dashboard') }}#logistics"><i class="fa-solid fa-truck-fast"></i> Logistics</a><a href="{{ route('vendor-portal.dashboard') }}#activity"><i class="fa-regular fa-comments"></i> Updates</a></nav><div class="vp-user"><span>{{ strtoupper(substr($vpUser->displayName(),0,1)) }}</span><div><strong>{{ $vpUser->displayName() }}</strong><small>{{ $vpUser->email ?: $vpUser->username }}</small></div></div></aside>
<main class="vp-main"><header class="vp-topbar"><button type="button" class="vp-menu" data-vp-menu aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button><div><small>Vendor workspace</small><strong>@yield('page-title','Overview')</strong></div><div class="vp-top-actions"><button type="button" data-vp-theme aria-label="Change colour theme"><i class="fa-regular fa-moon"></i></button><form method="POST" action="{{ route('vendor-portal.logout') }}">@csrf<button type="submit" aria-label="Log out"><i class="fa-solid fa-arrow-right-from-bracket"></i></button></form></div></header>
<div class="vp-toasts" aria-live="polite">@if(session('success'))<div class="vp-toast success"><i class="fa-solid fa-circle-check"></i><span>{{ session('success') }}</span></div>@endif @if(session('warning'))<div class="vp-toast warning"><i class="fa-solid fa-triangle-exclamation"></i><span>{{ session('warning') }}</span></div>@endif @if(session('error'))<div class="vp-toast error"><i class="fa-solid fa-circle-exclamation"></i><span>{{ session('error') }}</span></div>@endif @if($errors->any())<div class="vp-toast error"><i class="fa-solid fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>@endif</div>
<div class="vp-content">@yield('content')</div></main></div>
<script src="{{ $assetVer('assets/js/vendor-portal.js') }}"></script>
</body></html>
