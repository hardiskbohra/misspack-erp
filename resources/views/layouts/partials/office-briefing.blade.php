@php($briefing = $officeBriefing ?? ['unread' => 0, 'items' => [], 'toasts' => [], 'popup' => null])

<div class="ob" id="officeBriefing" hidden
    data-ob
    data-ob-inbox="{{ route('office-alerts.inbox') }}"
    data-ob-payload='@json($briefing)'>
    <div class="ob-backdrop" data-ob-close></div>
    <div class="ob-panel master-modal-card is-narrow" role="dialog" aria-label="Office briefings">
        <div class="master-modal-header">
            <div class="master-modal-heading">
                <span class="master-modal-icon" aria-hidden="true"><i class="fas fa-bell"></i></span>
                <div>
                    <p class="master-modal-subtitle">Your desk</p>
                    <h2 class="master-modal-title">Briefings</h2>
                </div>
            </div>
            <button type="button" class="master-modal-close" data-ob-close aria-label="Close">&times;</button>
        </div>
        <div class="master-modal-body ob-body" data-ob-list></div>
        <div class="master-modal-footer ob-foot">
            <p class="master-modal-lead">
                Critical items clear for the office when one person marks them read.
                <a href="{{ route('settings.briefings') }}">Organisation settings</a>
            </p>
        </div>
    </div>
</div>

<div class="master-modal" id="officeBriefingModal" aria-hidden="true">
    <div class="master-modal-card ob-popup-card" role="alertdialog" aria-modal="true" aria-labelledby="officeBriefingModalTitle">
        <div class="master-modal-header">
            <div class="master-modal-heading">
                <span class="master-modal-icon" aria-hidden="true"><i class="fas fa-bell"></i></span>
                <div>
                    <p class="master-modal-subtitle" data-ob-modal-team></p>
                    <h3 class="master-modal-title" id="officeBriefingModalTitle" data-ob-modal-title>Briefing</h3>
                </div>
            </div>
        </div>
        <div class="master-modal-body">
            <p class="master-sub" data-ob-modal-body></p>
        </div>
        <div class="master-modal-footer ob-popup-footer">
            <p class="master-modal-lead">
                <a class="master-btn master-btn-soft" data-ob-modal-link hidden>Open</a>
            </p>
            <button type="button" class="master-btn master-btn-ghost" data-ob-modal-snooze="1h">Snooze 1h</button>
            <button type="button" class="master-btn master-btn-ghost" data-ob-modal-snooze="tomorrow">Tomorrow 9:00</button>
            <button type="button" class="master-btn master-btn-primary" data-ob-modal-ack>Mark as read</button>
        </div>
    </div>
</div>
