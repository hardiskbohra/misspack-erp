@php($briefing = $officeBriefing ?? ['unread' => 0, 'items' => [], 'toasts' => [], 'popup' => null])

<div class="ob" id="officeBriefing" hidden
    data-ob
    data-ob-inbox="{{ route('office-alerts.inbox') }}"
    data-ob-payload='@json($briefing)'>
    <div class="ob-backdrop" data-ob-close></div>
    <div class="ob-panel" role="dialog" aria-label="Office briefings">
        <div class="ob-head">
            <div>
                <p class="ob-eyebrow">Office</p>
                <h2 class="ob-title">Briefings</h2>
            </div>
            <button type="button" class="ob-close" data-ob-close aria-label="Close">&times;</button>
        </div>
        <div class="ob-body" data-ob-list></div>
        <p class="ob-foot">Critical items stay until you mark them read. FYI notes disappear after you have seen them once.</p>
    </div>
</div>

<div class="master-modal" id="officeBriefingModal" aria-hidden="true">
    <div class="master-modal-card is-narrow" role="alertdialog" aria-modal="true" aria-labelledby="officeBriefingModalTitle">
        <div class="master-modal-header">
            <div class="master-modal-heading">
                <span class="master-modal-icon" aria-hidden="true">!</span>
                <div>
                    <p class="master-modal-subtitle" data-ob-modal-team></p>
                    <h3 class="master-modal-title" id="officeBriefingModalTitle" data-ob-modal-title>Briefing</h3>
                </div>
            </div>
        </div>
        <div class="master-modal-body">
            <p data-ob-modal-body></p>
        </div>
        <div class="master-modal-footer">
            <a class="master-btn master-btn-soft" data-ob-modal-link hidden>Open</a>
            <button type="button" class="master-btn master-btn-primary" data-ob-modal-ack>Mark as read</button>
        </div>
    </div>
</div>
