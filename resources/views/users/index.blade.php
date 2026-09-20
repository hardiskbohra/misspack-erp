@extends('layouts.app')

@section('title', 'Users')
@section('page-title', 'User Management')

@push('styles')
<style>
    /* =========================================================
       User Management Page
       Inherits theme tokens from layouts.app:
       --bg-card, --bg-input, --text-primary, --text-secondary,
       --text-muted, --border, --accent, --accent-light,
       --danger, --danger-light, --success, --warning, --shadow
       ========================================================= */

    /* ---------- Toolbar ---------- */
    

    .master-search-form {
        flex: 1 1 320px;
        max-width: 430px;
        min-width: 220px;
    }

    .master-search {
        position: relative;
        width: 100%;
    }

    .master-search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 13px;
        pointer-events: none;
    }

    .master-search-input {
        width: 100%;
        height: 46px;
        background: var(--bg-card);
        border: 1.5px solid var(--border);
        border-radius: 13px;
        padding: 11px 14px 11px 40px;
        color: var(--text-primary);
        font-size: 14px;
        outline: none;
        transition: all 0.2s;
    }

    .master-search-input::placeholder {
        color: var(--text-muted);
    }

    .master-search-input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-light);
    }

    /* ---------- Table ---------- */
    .master-card {
        border-radius: 20px;
        overflow: hidden;
    }

    .master-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .master-table {
        width: 100%;
        min-width: 960px;
        border-collapse: collapse;
    }

    .master-table thead tr {
        background: rgba(79, 142, 247, 0.04);
        border-bottom: 1px solid var(--border);
    }

    .master-table th {
        padding: 13px 18px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        color: black;
        white-space: nowrap;
    }

    .master-table td {
        padding: 15px 18px;
        font-size: 14px;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }

    .master-table tbody tr:last-child td {
        border-bottom: none;
    }

    .master-table tbody tr:hover {
        background: var(--table-row-hover);
    }

    .master-col-number {
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 500;
        width: 56px;
        white-space: nowrap;
    }

    .master-person {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 250px;
    }

    .master-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        font-size: 14px;
        color: #fff;
        flex: 0 0 42px;
        overflow: hidden;
    }

    .master-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }

    .master-name {
        font-weight: 500;
        font-size: 14px;
        color: var(--text-primary);
        line-height: 1.25;
    }

    .master-email {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 3px;
        overflow-wrap: anywhere;
    }

    .master-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 500;
        line-height: 1.2;
        white-space: nowrap;
    }

    .master-badge.department {
        background: rgba(79, 142, 247, 0.12);
        color: var(--accent);
    }

    .master-badge.designation {
        background: rgba(56, 217, 169, 0.12);
        color: var(--success);
    }

    .master-badge.verified {
        background: rgba(56, 217, 169, 0.12);
        color: var(--success);
    }

    .master-badge.pending {
        background: rgba(245, 158, 11, 0.12);
        color: var(--warning);
    }

    .master-muted {
        color: var(--text-muted);
    }

    .master-actions {
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .master-icon-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: 0;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        text-decoration: none;
        transition: all 0.2s;
    }

    .master-icon-btn.edit {
        background: var(--accent-light);
        color: var(--accent);
    }

    .master-icon-btn.edit:hover {
        background: var(--accent);
        color: #fff;
    }

    .master-icon-btn.delete {
        background: var(--danger-light);
        color: var(--danger);
    }

    .master-icon-btn.delete:hover {
        background: var(--danger);
        color: #fff;
    }

    .master-empty {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }

    .master-empty i {
        font-size: 48px;
        opacity: 0.25;
        margin-bottom: 16px;
        display: block;
    }

    .master-pagination {
        padding: 14px 18px;
        border-top: 1px solid var(--border);
    }

    /* ---------- Modal ---------- */
    .master-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1200;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
    }

    .master-modal-overlay.open {
        display: flex;
    }

    .master-modal-box,
    .master-confirm-box {
        width: 100%;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 20px;
        box-shadow: 0 24px 70px rgba(0, 0, 0, 0.5);
        overflow: hidden;
        animation: usersModalPop .22s ease;
    }

    .master-modal-box {
        max-width: 580px;
        max-height: calc(100vh - 36px);
        display: flex;
        flex-direction: column;
    }

    .master-confirm-box {
        max-width: 410px;
        padding: 34px 30px;
        text-align: center;
    }

    @keyframes usersModalPop {
        from { transform: scale(.94) translateY(12px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }

    .master-modal-head {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        flex: 0 0 auto;
    }

    .master-modal-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: var(--accent-light);
        color: var(--accent);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex: 0 0 42px;
    }

    .master-modal-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        line-height: 1.2;
    }

    .master-modal-subtitle {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 3px;
        font-weight: 500;
    }

    .master-modal-close {
        margin-left: auto;
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: var(--bg-input);
        border: 1px solid var(--border);
        color: var(--text-muted);
        cursor: pointer;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .master-modal-close:hover {
        background: var(--danger-light);
        color: var(--danger);
        border-color: var(--danger);
    }

    .master-modal-body {
        padding: 22px 24px;
        overflow-y: auto;
        flex: 1 1 auto;
    }

    .master-modal-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 11px;
        align-items: center;
        flex-wrap: wrap;
        flex: 0 0 auto;
    }

    .master-avatar-row {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
        padding-bottom: 18px;
        border-bottom: 1px solid var(--border);
    }

    .master-avatar-preview {
        width: 66px;
        height: 66px;
        border-radius: 50%;
        background: var(--accent-light);
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        font-weight: 500;
        border: 3px solid var(--border);
        overflow: hidden;
        flex: 0 0 66px;
    }

    .master-avatar-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }

    .master-avatar-info {
        min-width: 0;
    }

    .master-avatar-info p {
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 10px;
        line-height: 1.45;
    }

    .master-avatar-actions {
        display: flex;
        align-items: center;
        gap: 9px;
        flex-wrap: wrap;
    }

    .master-upload-btn,
    .master-remove-avatar-btn {
        border-radius: 10px;
        padding: 8px 15px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        transition: all 0.2s;
    }

    .master-upload-btn {
        background: var(--accent);
        color: #fff;
        border: 1px solid var(--accent);
    }

    .master-upload-btn:hover {
        background: var(--accent-hover);
    }

    .master-remove-avatar-btn {
        background: var(--danger-light);
        color: var(--danger);
        border: 1px solid rgba(229, 62, 106, .25);
    }

    .master-remove-avatar-btn:hover {
        background: var(--danger);
        color: #fff;
    }

    .master-file-name {
        font-size: 12px;
        color: var(--text-muted);
        overflow-wrap: anywhere;
    }

    .master-section-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin: 18px 0 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .master-section-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }

    .master-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .master-form-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .master-form-field.full {
        grid-column: 1 / -1;
    }

    .master-required {
        color: var(--danger);
    }

    .master-error {
        font-size: 12px;
        color: var(--danger);
    }

    .master-password-wrap {
        position: relative;
    }

    .master-password-wrap .master-input {
        padding-right: 42px;
    }

    .master-password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: 0;
        cursor: pointer;
        color: var(--text-muted);
        font-size: 13px;
        padding: 2px;
    }

    .master-password-toggle:hover {
        color: var(--accent);
    }

    .master-info-box {
        background: rgba(79, 142, 247, 0.07);
        border: 1px solid rgba(79, 142, 247, 0.2);
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 12px;
        color: var(--accent);
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        line-height: 1.45;
    }

    .master-delete-icon {
        width: 66px;
        height: 66px;
        border-radius: 50%;
        background: var(--danger-light);
        color: var(--danger);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        margin: 0 auto 18px;
    }

    .master-delete-title {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text-primary);
    }

    .master-delete-desc {
        font-size: 14px;
        color: var(--text-secondary);
        margin-bottom: 26px;
        line-height: 1.6;
    }

    .master-delete-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 1199px) {
        .master-table {
            min-width: 880px;
        }
    }

    @media (max-width: 767px) {
        .master-toolbar {
            align-items: stretch;
            flex-direction: column;
            gap: 12px;
        }

        .master-search-form,
        .master-btn-primary {
            width: 100%;
            max-width: 100%;
        }

        .master-table-wrap {
            overflow-x: visible;
        }

        .master-table {
            min-width: 0;
        }

        .master-table thead {
            display: none;
        }

        .master-table,
        .master-table tbody,
        .master-table tr,
        .master-table td {
            display: block;
            width: 100%;
        }

        .master-table tbody {
            padding: 12px;
            display: grid;
            gap: 12px;
        }

        .master-table tr {
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--bg-card);
            overflow: hidden;
        }

        .master-table tr:hover {
            background: var(--bg-card);
        }

        .master-table td {
            display: grid;
            grid-template-columns: 112px minmax(0, 1fr);
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
        }

        .master-table td:last-child {
            border-bottom: 0;
        }

        .master-table td::before {
            content: attr(data-label);
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .master-table td.master-user-td {
            grid-template-columns: 1fr;
        }

        .master-table td.master-user-td::before {
            margin-bottom: 4px;
        }

        .master-col-number {
            width: auto;
        }

        .master-person {
            min-width: 0;
        }

        .master-actions {
            justify-content: flex-start;
        }

        .master-pagination {
            padding: 12px;
        }

        .master-modal-overlay {
            align-items: flex-end;
            padding: 10px;
        }

        .master-modal-box {
            max-width: 100%;
            max-height: calc(100vh - 20px);
            border-radius: 18px 18px 0 0;
        }

        .master-modal-head,
        .master-modal-body,
        .master-modal-footer {
            padding-left: 18px;
            padding-right: 18px;
        }

        .master-form-grid {
            grid-template-columns: 1fr;
        }

        .master-form-field.full {
            grid-column: 1;
        }

        .master-avatar-row {
            align-items: flex-start;
        }

        .master-modal-footer {
            flex-direction: column;
            align-items: stretch;
        }

        .master-modal-footer .master-btn-primary,
        .master-modal-footer {
            width: 100%;
        }

        .master-confirm-box {
            border-radius: 18px 18px 0 0;
            max-width: 100%;
        }

        .master-delete-actions {
            flex-direction: column;
        }
    }
    
    
    @media (max-width: 767px) {
        .master-toolbar,
        .page-header {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            justify-content: flex-start !important;
            gap: 12px !important;
    
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
    
            margin-bottom: 16px !important;
            padding-bottom: 0 !important;
        }
    
        .master-search-form,
        .page-header form {
            width: 100% !important;
            max-width: 100% !important;
            flex: 0 0 auto !important;
            margin: 0 !important;
        }
    
        .master-search,
        .search-wrap {
            width: 100% !important;
            max-width: 100% !important;
        }
    
        .master-btn-primary,
        .btn-add-user {
            width: 100% !important;
            justify-content: center !important;
            margin: 0 !important;
            flex: 0 0 auto !important;
        }
    
        .master-card,
        .table-card {
            margin-top: 0 !important;
        }
    }

    @media (max-width: 420px) {
        .master-table td {
            grid-template-columns: 92px minmax(0, 1fr);
            gap: 10px;
            padding: 11px 12px;
        }

        .master-avatar {
            width: 38px;
            height: 38px;
            flex-basis: 38px;
            font-size: 13px;
        }

        .master-name {
            font-size: 13px;
        }

        .master-email {
            font-size: 11px;
        }

        .master-badge {
            white-space: normal;
            line-height: 1.3;
        }
    }
    
    /* Desktop */
    .master-mobile-list{
        display:none;
    }
    
    /* Mobile */
    @media (max-width:767px){
    
        .master-table-wrap{
            display:none;
        }
    
        .master-mobile-list{
            display:flex;
            flex-direction:column;
            gap:18px;
        }
    
        .user-card{
            background:#fff;
            border-radius:18px;
            overflow:hidden;
            border:1px solid #ececec;
            box-shadow: 0 4px 12px rgba(0,0,0,.12);
        }
        
        .user-card-header{
            display: flex;
            align-items: center;
            justify-content: center; /* Centers the whole group */
            gap: 16px;
            padding: 20px;
            text-align: left;
        }
        
        .user-card-header .master-avatar{
            width: 90px;
            height: 90px;
            min-width: 90px;
            min-height: 90px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,.12);
            flex-shrink: 0;
        }
        
        .user-card-header .master-avatar img{
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .user-card-info{
            flex: 0 0 auto;      /* Remove flex:1 */
            text-align: left;
        }
        
        .user-card-info h4{
            margin: 0;
            font-size: 20px;
            font-weight: 500;
            line-height: 1.2;
            color: #1f2a44;
        }
        
        .user-card-info p{
            margin: 6px 0 0;
            font-size: 15px;
            color: #777;
            line-height: 1.2;
        }
    
        .user-card-body{
            padding:18px 20px;
        }
    
        .user-row{
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:10px 0;
            border-bottom:1px solid #ececec;
        }
    
        .user-row:last-child{
            border-bottom:none;
        }
    
        .user-row span:first-child{
            color:#777;
            font-size:14px;
        }
    
        .user-row strong{
            font-size:14px;
            text-align:right;
            word-break:break-word;
        }
    
        .user-card-footer{
            display:flex;
            justify-content:center;
            gap:20px;
        }
    
        .user-card-footer .master-icon-btn{
            width:46px;
            height:46px;
            border:none;
            border-radius:50%;
            background:#fff;
            cursor:pointer;
            font-size:18px;
            box-shadow:0 2px 8px rgba(0,0,0,.08);
        }
    
        .user-card-footer .edit{
            color:#0d6efd;
        }
    
        .user-card-footer .delete{
            color:#dc3545;
        }
    }
    
    /* =========================================================
       User Add/Edit Modal Mobile Responsive Fix
       ========================================================= */
    
    @media (max-width: 767px) {
        .modal-overlay,
        .master-modal-overlay {
            align-items: flex-end !important;
            justify-content: center !important;
            padding: 10px !important;
            overflow-y: auto !important;
        }
    
        .modal-box,
        .master-modal-box {
            width: 100% !important;
            max-width: 100% !important;
            max-height: calc(100vh - 20px) !important;
            height: auto !important;
            border-radius: 18px 18px 0 0 !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }
    
        .modal-head,
        .master-modal-head {
            flex: 0 0 auto !important;
            padding: 16px 18px !important;
        }
    
        .modal-body,
        .master-modal-body {
            flex: 1 1 auto !important;
            max-height: calc(100vh - 170px) !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
            padding: 18px !important;
        }
    
        .modal-foot,
        .master-modal-footer {
            flex: 0 0 auto !important;
            padding: 14px 18px !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 10px !important;
        }
    
        .modal-foot button,
        .master-modal-footer button,
        .modal-foot .btn-submit,
        .modal-foot .btn-cancel-m,
        .master-modal-footer .master-btn-primary,
        .master-modal-footer .master-btn-light {
            width: 100% !important;
            justify-content: center !important;
        }
    
        .modal-grid,
        .master-form-grid {
            grid-template-columns: 1fr !important;
            gap: 14px !important;
        }
    
        .fg,
        .fg.full,
        .master-form-field,
        .master-form-field.full {
            grid-column: 1 / -1 !important;
            width: 100% !important;
            min-width: 0 !important;
        }
    
        .finp,
        .master-input {
            width: 100% !important;
            max-width: 100% !important;
        }
    
        .av-row,
        .master-avatar-row {
            align-items: flex-start !important;
            gap: 14px !important;
        }
    
        .av-circle,
        .master-avatar-preview {
            width: 58px !important;
            height: 58px !important;
            flex: 0 0 58px !important;
        }
    
        .av-info,
        .master-avatar-info {
            min-width: 0 !important;
            flex: 1 !important;
        }
    
        .av-btns,
        .master-avatar-actions {
            gap: 8px !important;
        }
    
        .av-filename,
        .master-file-name {
            display: block !important;
            width: 100% !important;
            overflow-wrap: anywhere !important;
        }
    }
    
    @media (max-width: 420px) {
        .modal-overlay,
        .master-modal-overlay {
            padding: 6px !important;
        }
    
        .modal-box,
        .master-modal-box {
            max-height: calc(100vh - 12px) !important;
            border-radius: 16px 16px 0 0 !important;
        }
    
        .modal-head,
        .master-modal-head {
            padding: 14px 16px !important;
        }
    
        .modal-body,
        .master-modal-body {
            max-height: calc(100vh - 155px) !important;
            padding: 16px !important;
        }
    
        .modal-foot,
        .master-modal-footer {
            padding: 12px 16px !important;
        }
    
        .modal-head-title,
        .master-modal-title {
            font-size: 16px !important;
        }
    
        .modal-head-sub,
        .master-modal-subtitle {
            font-size: 11px !important;
        }
    
        .av-row,
        .master-avatar-row {
            flex-direction: column !important;
            align-items: center !important;
            text-align: center !important;
        }
    
        .av-btns,
        .master-avatar-actions {
            justify-content: center !important;
        }
    }
</style>
@endpush

@section('content')
<div class="users">
    {{-- Toolbar --}}
    <div class="master-toolbar" style="padding:0;padding-bottom:18px;">
        <form method="GET" action="{{ route('users.index') }}" class="master-search-form">
            <div class="master-search">
                <i class="fas fa-search master-search-icon"></i>
                <input type="text"
                       name="search"
                       class="master-search-input"
                       placeholder="Search by name, email, department…"
                       value="{{ $search }}">
            </div>
        </form>

        <button type="button" class="master-btn master-btn-primary" data-open-add-modal>
            <i class="fas fa-plus"></i> Add User
        </button>
    </div>

    {{-- Users Table --}}
    <div class="master-card">
        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th class="master-col-number">#</th>
                        <th>User</th>
                        <th>Mobile</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Verified</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $i => $user)
                        @php
                            $colors = ['#4f8ef7','#6c63ff','#38d9a9','#f6ad55','#e879c0','#e53e6a','#22d3ee'];
                            $color  = $colors[$user->id % count($colors)];
                            $initials = $user->initials ?? strtoupper(substr($user->name ?? 'U', 0, 2));
                        @endphp
                        <tr>
                            <td class="master-col-number" data-label="#">{{ $users->firstItem() + $i }}</td>
                            <td class="master-user-td" data-label="User">
                                <div class="master-person">
                                    <div class="master-avatar" style="background:{{ $color }};">
                                        @if($user->avatar)
                                            <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}">
                                        @else
                                            {{ $initials }}
                                        @endif
                                    </div>
                                    <div>
                                        <div class="master-name">{{ $user->name }}</div>
                                        <div class="master-email">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Mobile">{{ $user->mobile ?? '—' }}</td>
                            <td data-label="Department">
                                @if($user->department)
                                    <span class="master-badge department">{{ $user->department }}</span>
                                @else
                                    <span class="master-muted">—</span>
                                @endif
                            </td>
                            <td data-label="Designation">
                                    {{ $user->designation ?? '-' }}
                            </td>
                            <td data-label="Verified">
                                @if($user->email_verified_at)
                                    <span class="master-badge verified"><i class="fas fa-check-circle"></i> Verified</span>
                                @else
                                    <span class="master-badge pending"><i class="fas fa-clock"></i> Pending</span>
                                @endif
                            </td>
                            <td data-label="Action">
                                <div class="master-actions">
                                    <button type="button"
                                            class="master-icon-btn edit"
                                            title="Edit"
                                            data-edit-user="{{ $user->id }}">
                                        <i class="fas fa-pen"></i>
                                    </button>

                                    @if($user->id !== auth()->id())
                                        <button type="button"
                                                class="master-icon-btn delete"
                                                title="Delete"
                                                data-delete-user="{{ $user->id }}"
                                                data-delete-name="{{ $user->name }}">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="master-empty">
                                    <i class="fas fa-master-cog"></i>
                                    <p>No users found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        {{-- Mobile Cards --}}
        <div class="master-mobile-list">
            @forelse($users as $user)
                @php
                    $colors = ['#4f8ef7','#6c63ff','#38d9a9','#f6ad55','#e879c0','#e53e6a','#22d3ee'];
                    $color  = $colors[$user->id % count($colors)];
                    $initials = $user->initials ?? strtoupper(substr($user->name ?? 'U', 0, 2));
                @endphp
        
                <div class="user-card">
        
                    <div class="user-card-header">
                        <div class="master-avatar" style="background:{{ $color }}">
                            @if($user->avatar)
                                <img src="{{ asset('storage/'.$user->avatar) }}" alt="">
                            @else
                                {{ $initials }}
                            @endif
                        </div>
                    
                        <div class="user-card-info">
                            <h4>{{ $user->name }}</h4>
                            <p>{{ $user->designation ?? 'No Designation' }}</p>
                        </div>
                    </div>
        
                    <div class="user-card-body">
        
                        <div class="user-row">
                            <span>Email</span>
                            <strong>{{ $user->email }}</strong>
                        </div>
        
                        <div class="user-row">
                            <span>Mobile</span>
                            <strong>{{ $user->mobile ?? '—' }}</strong>
                        </div>
        
                        <div class="user-row">
                            <span>Department</span>
                            <strong>{{ $user->department ?? '—' }}</strong>
                        </div>
        
                        <div class="user-row">
                            <span>Status</span>
        
                            @if($user->email_verified_at)
                                <span class="master-badge verified">
                                    <i class="fas fa-check-circle"></i> Verified
                                </span>
                            @else
                                <span class="master-badge pending">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            @endif
                        </div>
        
                    </div>
        
                    <div class="user-card-footer" style="margin-bottom:10px;">
        
                        <button type="button"
                                class="master-icon-btn edit"
                                data-edit-user="{{ $user->id }}">
                            <i class="fas fa-pen"></i>
                        </button>
        
                        @if($user->id !== auth()->id())
                            <button type="button"
                                    class="master-icon-btn delete"
                                    data-delete-user="{{ $user->id }}"
                                    data-delete-name="{{ $user->name }}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        @endif
        
                    </div>
        
                </div>
        
            @empty
        
                <div class="master-empty">
                    No users found.
                </div>
        
            @endforelse
        </div>

        <div class="master-pagination">
            <x-pagination :items="$users" />
        </div>
    </div>
</div>

{{-- Add User Modal --}}
<div class="master-modal-overlay" id="addModal" aria-hidden="true">
    <div class="master-modal-box" role="dialog" aria-modal="true" aria-labelledby="addUserTitle">
        <div class="master-modal-head">
            <div class="master-modal-icon"><i class="fas fa-user-plus"></i></div>
            <div>
                <div class="master-modal-title" id="addUserTitle">Add User</div>
                <div class="master-modal-subtitle">Create a new user account</div>
            </div>
            <button type="button" class="master-modal-close" data-close-modal="addModal" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" id="addForm">
            @csrf
            <div class="master-modal-body">
                <div class="master-avatar-row">
                    <div class="master-avatar-preview" id="addAvatarCircle"><i class="fas fa-user"></i></div>
                    <div class="master-avatar-info">
                        <p>Upload a profile photo. JPG, PNG or GIF, max 2MB.</p>
                        <div class="master-avatar-actions">
                            <label class="master-upload-btn" for="addAvatarFile">
                                <i class="fas fa-upload"></i> Choose file
                                <input type="file" id="addAvatarFile" name="avatar" accept="image/*" hidden>
                            </label>
                            <span class="master-file-name" id="addAvName">No file chosen</span>
                        </div>
                    </div>
                </div>

                <div class="master-section-label">Basic Information</div>
                <div class="master-form-grid">
                    <div class="master-form-field">
                        <label class="master-label">Full Name <span class="master-required">*</span></label>
                        <input type="text" name="name" class="master-input {{ $errors->has('name') ? 'is-invalid' : '' }}" placeholder="e.g. Jonathan Deo" value="{{ old('name') }}" required>
                        @error('name')<span class="master-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="master-form-field">
                        <label class="master-label">Email Address <span class="master-required">*</span></label>
                        <input type="email" name="email" class="master-input {{ $errors->has('email') ? 'is-invalid' : '' }}" placeholder="user@misspack.com" value="{{ old('email') }}" required>
                        @error('email')<span class="master-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="master-form-field">
                        <label class="master-label">Mobile Number</label>
                        <input type="text" name="mobile" class="master-input" placeholder="+91 9876543210" value="{{ old('mobile') }}">
                    </div>
                    <div class="master-form-field">
                        <label class="master-label">Department</label>
                        <input type="text" name="department" class="master-input" placeholder="Technology" value="{{ old('department') }}">
                    </div>
                    <div class="master-form-field full">
                        <label class="master-label">Designation</label>
                        <input type="text" name="designation" class="master-input" placeholder="Senior Developer" value="{{ old('designation') }}">
                    </div>
                </div>

                <div class="master-section-label">Account Security</div>
                <div class="master-form-grid">
                    <div class="master-form-field">
                        <label class="master-label">Password <span class="master-required">*</span></label>
                        <div class="master-password-wrap">
                            <input type="password" name="password" id="addPw1" class="master-input {{ $errors->has('password') ? 'is-invalid' : '' }}" placeholder="Min. 6 characters" required>
                            <button type="button" class="master-password-toggle" data-toggle-password="addPw1" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                        @error('password')<span class="master-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="master-form-field">
                        <label class="master-label">Confirm Password <span class="master-required">*</span></label>
                        <div class="master-password-wrap">
                            <input type="password" name="password_confirmation" id="addPw2" class="master-input" placeholder="Repeat password" required>
                            <button type="button" class="master-password-toggle" data-toggle-password="addPw2" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="submit" class="master-btn master-btn-primary"><i class="fas fa-user-plus"></i> Add User</button>
                <button type="button" class="master-btn master-btn-light" data-close-modal="addModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit User Modal --}}
<div class="master-modal-overlay" id="editModal" aria-hidden="true">
    <div class="master-modal-box" role="dialog" aria-modal="true" aria-labelledby="editUserTitle">
        <div class="master-modal-head">
            <div class="master-modal-icon"><i class="fas fa-user-edit"></i></div>
            <div>
                <div class="master-modal-title" id="editUserTitle">Update User</div>
                <div class="master-modal-subtitle" id="editModalSub">Update user details</div>
            </div>
            <button type="button" class="master-modal-close" data-close-modal="editModal" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" id="editForm" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="remove_avatar" id="editRemoveAvatar" value="0">

            <div class="master-modal-body">
                <div class="master-avatar-row">
                    <div class="master-avatar-preview" id="editAvatarCircle"><i class="fas fa-user"></i></div>
                    <div class="master-avatar-info">
                        <p>Upload a new photo or remove the existing one.</p>
                        <div class="master-avatar-actions">
                            <label class="master-upload-btn" for="editAvatarFile">
                                <i class="fas fa-upload"></i> Change Photo
                                <input type="file" id="editAvatarFile" name="avatar" accept="image/*" hidden>
                            </label>
                            <button type="button" class="master-remove-avatar-btn" id="editRemoveBtn" style="display:none;">
                                <i class="fas fa-trash-alt"></i> Remove
                            </button>
                            <span class="master-file-name" id="editAvName">No file chosen</span>
                        </div>
                    </div>
                </div>

                <div class="master-section-label">Basic Information</div>
                <div class="master-form-grid">
                    <div class="master-form-field">
                        <label class="master-label">Full Name <span class="master-required">*</span></label>
                        <input type="text" name="name" id="editName" class="master-input" required>
                    </div>
                    <div class="master-form-field">
                        <label class="master-label">Email Address <span class="master-required">*</span></label>
                        <input type="email" name="email" id="editEmail" class="master-input" required>
                    </div>
                    <div class="master-form-field">
                        <label class="master-label">Mobile Number</label>
                        <input type="text" name="mobile" id="editMobile" class="master-input">
                    </div>
                    <div class="master-form-field">
                        <label class="master-label">Department</label>
                        <input type="text" name="department" id="editDepartment" class="master-input">
                    </div>
                    <div class="master-form-field full">
                        <label class="master-label">Designation</label>
                        <input type="text" name="designation" id="editDesignation" class="master-input">
                    </div>
                </div>

                <div class="master-section-label">Change Password</div>
                <div class="master-info-box">
                    <i class="fas fa-info-circle"></i>
                    Leave both fields blank to keep the current password unchanged.
                </div>
                <div class="master-form-grid">
                    <div class="master-form-field">
                        <label class="master-label">New Password</label>
                        <div class="master-password-wrap">
                            <input type="password" name="password" id="editPw1" class="master-input" placeholder="Min. 6 characters">
                            <button type="button" class="master-password-toggle" data-toggle-password="editPw1" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="master-form-field">
                        <label class="master-label">Confirm New Password</label>
                        <div class="master-password-wrap">
                            <input type="password" name="password_confirmation" id="editPw2" class="master-input" placeholder="Repeat new password">
                            <button type="button" class="master-password-toggle" data-toggle-password="editPw2" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="submit" class="master-btn master-btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                <button type="button" class="master-btn master-btn-light" data-close-modal="editModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Delete Confirmation --}}
<div class="master-modal-overlay" id="deleteModal" aria-hidden="true">
    <div class="master-confirm-box" role="dialog" aria-modal="true" aria-labelledby="deleteUserTitle">
        <div class="master-delete-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="master-delete-title" id="deleteUserTitle">Delete User</div>
        <div class="master-delete-desc" id="deleteDesc">Are you sure you want to delete this user? This action cannot be undone.</div>
        <div class="master-delete-actions">
            <form method="POST" id="deleteForm">
                @csrf
                @method('DELETE')
                <button type="submit" class="master-btn-danger">Yes, Delete</button>
            </form>
            <button type="button" class="master-btn-light" data-close-modal="deleteModal">Cancel</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        'use strict';

        function byId(id) {
            return document.getElementById(id);
        }

        function openModal(id) {
            var modal = byId(id);
            if (!modal) return;
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('master-modal-open');
        }

        function closeModal(id) {
            var modal = byId(id);
            if (!modal) return;
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('master-modal-open');
        }

        function resetAvatar(circleId, nameId) {
            var circle = byId(circleId);
            var name = byId(nameId);
            if (circle) circle.innerHTML = '<i class="fas fa-user"></i>';
            if (name) name.textContent = 'No file chosen';
        }

        function previewAvatar(input, circleId, nameId) {
            if (!input.files || !input.files[0]) return;

            var reader = new FileReader();
            reader.onload = function (event) {
                var circle = byId(circleId);
                if (circle) {
                    circle.innerHTML = '<img src="' + event.target.result + '" alt="Preview">';
                }
            };
            reader.readAsDataURL(input.files[0]);

            var fileName = byId(nameId);
            if (fileName) fileName.textContent = input.files[0].name;

            var removeButton = byId('editRemoveBtn');
            if (removeButton) removeButton.style.display = 'inline-flex';

            var removeInput = byId('editRemoveAvatar');
            if (removeInput) removeInput.value = '0';
        }

        function togglePassword(fieldId, button) {
            var field = byId(fieldId);
            if (!field) return;

            var show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            button.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        }

        function openAddModal() {
            var form = byId('addForm');
            if (form) form.reset();
            resetAvatar('addAvatarCircle', 'addAvName');
            openModal('addModal');
        }

        function openEditModal(userId) {
            if (byId('editPw1')) byId('editPw1').value = '';
            if (byId('editPw2')) byId('editPw2').value = '';
            if (byId('editAvatarFile')) byId('editAvatarFile').value = '';
            if (byId('editAvName')) byId('editAvName').textContent = 'No file chosen';
            if (byId('editRemoveAvatar')) byId('editRemoveAvatar').value = '0';

            fetch('/users/' + userId + '/data')
                .then(function (response) { return response.json(); })
                .then(function (user) {
                    byId('editModalSub').textContent = 'Update details for ' + user.name;
                    byId('editForm').action = '/users/' + user.id;
                    byId('editName').value = user.name || '';
                    byId('editEmail').value = user.email || '';
                    byId('editMobile').value = user.mobile || '';
                    byId('editDepartment').value = user.department || '';
                    byId('editDesignation').value = user.designation || '';

                    var circle = byId('editAvatarCircle');
                    var removeButton = byId('editRemoveBtn');

                    if (user.avatar) {
                        circle.innerHTML = '<img src="' + user.avatar + '" alt="' + user.name + '">';
                        removeButton.style.display = 'inline-flex';
                    } else {
                        circle.innerHTML = '<span style="font-weight:500;font-size:20px;">' + String(user.name || 'U').substring(0, 2).toUpperCase() + '</span>';
                        removeButton.style.display = 'none';
                    }

                    openModal('editModal');
                })
                .catch(function () {
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load user data. Please try again.' });
                    } else {
                        alert('Could not load user data. Please try again.');
                    }
                });
        }

        function removeEditAvatar() {
            resetAvatar('editAvatarCircle', 'editAvName');
            byId('editAvName').textContent = 'Avatar will be removed';
            byId('editRemoveAvatar').value = '1';
            byId('editRemoveBtn').style.display = 'none';
        }

        function openDeleteModal(id, name) {
            byId('deleteDesc').textContent = 'Are you sure you want to delete "' + name + '"? This action cannot be undone.';
            byId('deleteForm').action = '/users/' + id;
            openModal('deleteModal');
        }

        document.addEventListener('DOMContentLoaded', function () {
            var addButton = document.querySelector('[data-open-add-modal]');
            if (addButton) addButton.addEventListener('click', openAddModal);

            document.querySelectorAll('[data-close-modal]').forEach(function (button) {
                button.addEventListener('click', function () {
                    closeModal(button.getAttribute('data-close-modal'));
                });
            });

            document.querySelectorAll('.master-modal-overlay').forEach(function (overlay) {
                overlay.addEventListener('click', function (event) {
                    if (event.target === overlay) closeModal(overlay.id);
                });
            });

            document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
                button.addEventListener('click', function () {
                    togglePassword(button.getAttribute('data-toggle-password'), button);
                });
            });

            document.querySelectorAll('[data-edit-user]').forEach(function (button) {
                button.addEventListener('click', function () {
                    openEditModal(button.getAttribute('data-edit-user'));
                });
            });

            document.querySelectorAll('[data-delete-user]').forEach(function (button) {
                button.addEventListener('click', function () {
                    openDeleteModal(button.getAttribute('data-delete-user'), button.getAttribute('data-delete-name'));
                });
            });

            var addAvatar = byId('addAvatarFile');
            if (addAvatar) addAvatar.addEventListener('change', function () { previewAvatar(addAvatar, 'addAvatarCircle', 'addAvName'); });

            var editAvatar = byId('editAvatarFile');
            if (editAvatar) editAvatar.addEventListener('change', function () { previewAvatar(editAvatar, 'editAvatarCircle', 'editAvName'); });

            var removeButton = byId('editRemoveBtn');
            if (removeButton) removeButton.addEventListener('click', removeEditAvatar);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    document.querySelectorAll('.master-modal-overlay.open').forEach(function (modal) {
                        closeModal(modal.id);
                    });
                }
            });

            @if($errors->any())
                openAddModal();
            @endif
        });
    })();
</script>
@endpush
