/* ==========================================================================
   CLIENTS.JS — Client module (admin list screen)
   --------------------------------------------------------------------------
   Loaded on clients/index only (quick-create + delete modals, KYC link
   copy). Behaviour is byte-for-byte the former inline script of
   clients/index.blade.php.
   ========================================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const quickModal = document.getElementById('quickClientModal');
    const deleteModal = document.getElementById('deleteClientModal');
    const deleteForm = document.getElementById('deleteClientForm');
    const deleteDesc = document.getElementById('deleteClientDesc');
    function openModal(modal) { modal?.classList.add('open'); modal?.setAttribute('aria-hidden', 'false'); document.body.classList.add('master-modal-open'); }
    function closeModal(modal) { modal?.classList.remove('open'); modal?.setAttribute('aria-hidden', 'true'); document.body.classList.remove('master-modal-open'); }
    document.getElementById('openQuickClientModal')?.addEventListener('click', () => openModal(quickModal));
    document.getElementById('closeQuickClientModal')?.addEventListener('click', () => closeModal(quickModal));
    document.getElementById('cancelQuickClientModal')?.addEventListener('click', () => closeModal(quickModal));
    document.querySelectorAll('.master-delete-btn').forEach(function (button) { button.addEventListener('click', function () { deleteDesc.textContent = 'Are you sure you want to delete "' + button.dataset.name + '"? This action cannot be undone.'; deleteForm.action = button.dataset.deleteUrl; openModal(deleteModal); }); });
    document.getElementById('closeDeleteClientModal')?.addEventListener('click', () => closeModal(deleteModal));
    document.getElementById('cancelDeleteClientModal')?.addEventListener('click', () => closeModal(deleteModal));
    [quickModal, deleteModal].forEach(function (modal) { modal?.addEventListener('click', function (event) { if (event.target === modal) closeModal(modal); }); });
    document.addEventListener('keydown', function (event) { if (event.key !== 'Escape') return; closeModal(quickModal); closeModal(deleteModal); });
});
function copyClientKycLink(url) { if (navigator.clipboard) { navigator.clipboard.writeText(url).then(() => alert('KYC link copied.')); } else { prompt('Copy KYC link:', url); } }
