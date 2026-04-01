<?php
// Default values if not provided
$entityName = isset($entityName) ? $entityName : 'item';
$actionUrl = isset($actionUrl) ? $actionUrl : '';
?>

<!-- Delete Modal -->
<div class="modal fade modal-premium" id="delete<?php echo htmlspecialchars($entityName); ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog dialog">
        <div class="dialog-content">
            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </span>
            <div class="modal-premium-body" style="text-align:center;">
                <div class="modal-icon-container modal-icon-container-red">
                    <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </div>
                <h4>Delete <?php echo ucfirst(htmlspecialchars($entityName)); ?></h4>
                <p class="modal-desc">Are you sure you want to delete this <?php echo htmlspecialchars($entityName); ?>?<br><span style="color:#94a3b8; font-size:12.5px;">This action cannot be undone.</span></p>
                <div style="margin-top:1rem; padding:0.6rem 1rem; background:linear-gradient(135deg,#fef2f2,#fff1f2); border:1px solid rgba(239,68,68,0.12); border-radius:10px; display:inline-block;">
                    <p id="delete_<?php echo htmlspecialchars($entityName); ?>_text" style="font-weight:700; color:#1e293b; font-size:13.5px; margin:0;"></p>
                </div>
            </div>
            <form action="<?php echo htmlspecialchars($actionUrl); ?>" method="POST">
                <div class="modal-premium-footer" style="justify-content:center;">
                    <input type="hidden" name="<?php echo htmlspecialchars($entityName); ?>_id" id="delete_<?php echo htmlspecialchars($entityName); ?>_id">
                    <input type="hidden" name="delete_<?php echo htmlspecialchars($entityName); ?>" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">No, Cancel</button>
                    <button class="btn-modal bg-red-600 text-white hover:bg-red-700" type="submit">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Activate Modal -->
<div class="modal fade modal-premium" id="activate<?php echo htmlspecialchars($entityName); ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog dialog">
        <div class="dialog-content">
            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </span>
            <div class="modal-premium-body" style="text-align:center;">
                <div class="modal-icon-container modal-icon-container-green">
                    <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h4>Activate <?php echo ucfirst(htmlspecialchars($entityName)); ?></h4>
                <p class="modal-desc">Are you sure you want to activate this <?php echo htmlspecialchars($entityName); ?>?<br><span style="color:#94a3b8; font-size:12.5px;">It will become available in the system again.</span></p>
                <div style="margin-top:1rem; padding:0.6rem 1rem; background:linear-gradient(135deg,#f0fdf4,#dcfce7); border:1px solid rgba(22,163,74,0.12); border-radius:10px; display:inline-block;">
                    <p id="activate_<?php echo htmlspecialchars($entityName); ?>_text" style="font-weight:700; color:#1e293b; font-size:13.5px; margin:0;"></p>
                </div>
            </div>
            <form action="<?php echo htmlspecialchars($actionUrl); ?>" method="POST">
                <div class="modal-premium-footer" style="justify-content:center;">
                    <input type="hidden" name="<?php echo htmlspecialchars($entityName); ?>_id" id="activate_<?php echo htmlspecialchars($entityName); ?>_id">
                    <input type="hidden" name="activate_<?php echo htmlspecialchars($entityName); ?>" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">No, Cancel</button>
                    <button class="btn-modal bg-green-600 text-white hover:bg-green-700" type="submit">Yes, Activate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Handle Delete modal
    const deleteModal = document.getElementById('delete<?php echo htmlspecialchars($entityName); ?>');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const entityId = button.getAttribute('data-entity-id');
            const entityText = button.getAttribute('data-entity-text');
            const entityStatus = button.getAttribute('data-entity-status');

            if (entityStatus === 'active') {
                const inputEntityId = deleteModal.querySelector('#delete_<?php echo htmlspecialchars($entityName); ?>_id');
                const displayEntityText = deleteModal.querySelector('#delete_<?php echo htmlspecialchars($entityName); ?>_text');
                inputEntityId.value = entityId;
                displayEntityText.textContent = entityText;
            } else {
                event.preventDefault();
                alert(`Cannot delete an inactive <?php echo htmlspecialchars($entityName); ?>.`);
            }
        });
    }

    // Handle Activate modal
    const activateModal = document.getElementById('activate<?php echo htmlspecialchars($entityName); ?>');
    if (activateModal) {
        activateModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const entityId = button.getAttribute('data-entity-id');
            const entityText = button.getAttribute('data-entity-text');
            const entityStatus = button.getAttribute('data-entity-status');

            if (entityStatus === 'inactive') {
                const inputEntityId = activateModal.querySelector('#activate_<?php echo htmlspecialchars($entityName); ?>_id');
                const displayEntityText = activateModal.querySelector('#activate_<?php echo htmlspecialchars($entityName); ?>_text');
                inputEntityId.value = entityId;
                displayEntityText.textContent = entityText;
            } else {
                event.preventDefault();
                alert(`Cannot activate an active <?php echo htmlspecialchars($entityName); ?>.`);
            }
        });
    }
});
</script>