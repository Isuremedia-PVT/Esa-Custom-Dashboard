<?php
require_once './middleware/auth_check.php';

// Verify user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: signin.php");
    exit;
}

require_once './config/database.php';
require_once './controllers/PatientNoteController.php';

$controller = new PatientNoteController($pdo);
$role = $_SESSION['user']['role'];
$staff_id = $_SESSION['user']['id'];

if ($role === 'patient') {
    $_SESSION['message'] = "Unauthorized access.";
    $_SESSION['message_type'] = "error";
    header("Location: patient-details.php");
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    echo "Invalid patient selection.";
    exit;
}

// Fetch patient info
$stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ? AND role = 'patient'");
$stmt->execute([$user_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    echo "Patient not found.";
    exit;
}

// Fetch all available shifts (note types)
$stmt = $pdo->query("SELECT id, name FROM note_types ORDER BY id ASC");
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Delete
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['note_id'])) {
    $res = $controller->deleteNote($_POST['note_id'], $staff_id, $role);
    if (isset($res['success'])) {
        $_SESSION['message'] = $res['success'];
        $_SESSION['message_type'] = "success";
    }
    header("Location: patient_notes.php?user_id=$user_id");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_note') {
    // 'note_status' is set by JS to 'draft' or 'final' before form.submit()
    $submitted_status = $_POST['note_status'] ?? 'final';
    $data = [
        'id'         => !empty($_POST['note_id']) ? $_POST['note_id'] : null,
        'patient_id' => $user_id,
        'staff_id'   => $staff_id,
        'shift_id'   => $_POST['shift_id'],
        'note_text'  => $_POST['note_text'],
        'status'     => in_array($submitted_status, ['draft', 'final']) ? $submitted_status : 'final'
    ];
    $res = $controller->saveNote($data);
    if (isset($res['success'])) {
        $_SESSION['message'] = $res['success'];
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = implode(', ', $res['errors']);
        $_SESSION['message_type'] = "error";
    }
    header("Location: patient_notes.php?user_id=$user_id");
    exit;
}

// Pagination & Filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$shift_filter = isset($_GET['shift_filter']) && $_GET['shift_filter'] !== '' ? (int)$_GET['shift_filter'] : null;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

$perPage = 10;
$result = $controller->getNotesByPatient($user_id, $page, $perPage, $staff_id, $role, $shift_filter, $start_date, $end_date);
$notes = $result['notes'] ?? [];
$totalNotes = $result['total'] ?? 0;
$totalPages = ceil($totalNotes / $perPage);

// Include layout files
$page_title = 'Patient Notes - ' . htmlspecialchars($patient['username']);
include './views/layouts/head.php';
require_once './views/layouts/alert.php';
?>

<style>
.pagination { display: flex; justify-content: space-between; align-items: center; width: 100%; }
.pagination-total { font-size: 1rem; color: #4b5563; }
.pagination .flex { gap: 0.5rem; }
.pagination-pager a, .pagination-pager span { padding: 0.5rem 1rem; border-radius: 0.25rem; text-decoration: none; color: #66b19c; }
.pagination-pager.active a { background-color: #66b19c; color: white; }
.status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
.status-final { background-color: #e6f4ea; color: #1e8e3e; }
.status-draft { background-color: #fef7e0; color: #b06000; }

/* Action icon buttons — circular colored backgrounds */
.action-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s, filter 0.15s;
    flex-shrink: 0;
}
.action-icon-btn:hover {
    transform: scale(1.1);
    filter: brightness(0.93);
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
}
.action-icon-btn.edit-icon {
    background: #e8eeff;
    color: #3b5bdb;
}
.action-icon-btn.delete-icon {
    background: #fee2e2;
    color: #dc2626;
}

/* Shift badge */
.shift-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: #e6f4f1;
    color: #0f766e;
    border: 1px solid #99d6cc;
    white-space: nowrap;
    letter-spacing: 0.02em;
}

.filter-container select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
    -webkit-appearance: none;
    appearance: none;
}

/* New Pagination Style */
.pagination-container {
    display: flex;
    align-items: center;
    gap: 15px;
}

.pagination-btn {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.2s;
    background: transparent;
}

.pagination-btn.active {
    background: #66b19c;
    color: white;
}

.pagination-btn.inactive {
    color: #66b19c;
}

.pagination-btn:hover:not(.active) {
    background: rgba(102, 177, 156, 0.1);
}

.pagination-arrow {
    color: #66b19c;
    font-size: 1.2rem;
    padding: 0 5px;
}

.pagination-arrow.disabled {
    color: #d1d5db;
    pointer-events: none;
}
</style>

<div class="app-layout-modern flex flex-auto flex-col">
    <div class="flex flex-auto min-w-0">
        <?php include './views/layouts/sidebar.php'; ?>
        <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200">
            <?php include './views/layouts/header.php'; ?>
            <div class="px-4 mt-4"><?= displayAlert() ?></div>
            <div class="h-full flex flex-auto flex-col justify-between">
                <main class="h-full">
                    <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                        <div class="container mx-auto">
                            <!-- Header & Navigation -->
                            <div class="mb-10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 border-b pb-6">
                                <div>
                                    <h3 class="text-2xl font-semibold">Notes: <?= htmlspecialchars($patient['username']) ?></h3>
                                    <p class="text-gray-500">Manage daily records and notes</p>
                                </div>
                                <div class="flex flex-wrap gap-3 items-center">
                                    <div class="filter-container">
                                        <form method="GET" action="patient_notes.php" class="flex flex-wrap items-center gap-4">
                                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-gray-500 uppercase">Shift:</span>
                                                <select name="shift_filter" onchange="this.form.submit()" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 h-[38px] min-w-[150px]">
                                                    <option value="">All Shifts</option>
                                                    <?php foreach ($shifts as $s): ?>
                                                        <option value="<?= $s['id'] ?>" <?= $shift_filter == $s['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($s['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-gray-500 uppercase">From:</span>
                                                <input type="date" name="start_date" value="<?= $start_date ?>" onchange="this.form.submit()" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2 h-[38px]">
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-gray-500 uppercase">To:</span>
                                                <input type="date" name="end_date" value="<?= $end_date ?>" onchange="this.form.submit()" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2 h-[38px]">
                                            </div>
                                            <?php if ($start_date || $end_date || $shift_filter): ?>
                                                <a href="patient_notes.php?user_id=<?= $user_id ?>" class="text-sm text-emerald-600 font-bold hover:text-emerald-700">Clear All</a>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                    <a href="patient_question_details.php?user_id=<?= $user_id ?>" class="btn btn-default btn-sm h-[38px] flex items-center">Back to Profile</a>
                                    <?php if ($role === 'staff' || $role === 'admin'): ?>
                                        <button type="button" class="btn btn-solid btn-sm add-note-btn h-[38px]" data-bs-toggle="modal" data-bs-target="#noteModal">
                                            Add Note
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Notes List -->
                            <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50/50 border-b border-gray-200">
                                            <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Date</th>
                                            <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Staff Member</th>
                                            <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Shift</th>
                                            <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Clinical Note</th>
                                            <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Status</th>
                                            <th class="px-6 py-4 font-bold text-gray-700 uppercase tracking-wider text-[11px] text-left">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($notes)): ?>
                                            <tr><td colspan="6" class="text-center p-8 text-gray-500">No notes recorded yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($notes as $note): ?>
                                                <tr class="border-b border-gray-100 hover:bg-gray-50/80 transition-all duration-200">
                                                    <td class="px-6 py-4 text-sm text-gray-600"><?= date('d M, Y H:i', strtotime($note['created_at'])) ?></td>
                                                    <td class="px-6 py-4 text-sm text-gray-600 font-medium"><?= htmlspecialchars($note['staff_name']) ?></td>

                                                    <!-- Single Shift column: staff's assigned shift -->
                                                    <td class="px-6 py-4 text-sm text-gray-600">
                                                        <?php
                                                            // Show staff's assigned shift; fallback to note's own shift_name
                                                            $shiftLabel = !empty($note['staff_shift_name']) ? $note['staff_shift_name'] : ($note['shift_name'] ?? null);
                                                        ?>
                                                        <?php if ($shiftLabel): ?>
                                                        <span class="shift-badge">
                                                            <?= htmlspecialchars($shiftLabel) ?>
                                                        </span>
                                                        <?php else: ?>
                                                        <span class="text-gray-400 text-xs">&mdash;</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td class="px-6 py-4 text-sm text-gray-600">
                                                        <?= nl2br(htmlspecialchars($note['note_text'])) ?>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <span class="status-badge <?= $note['status'] === 'final' ? 'status-final' : 'status-draft' ?>">
                                                            <?= ucfirst($note['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="flex items-center gap-2">
                                                            <?php if ($role === 'admin' || ($role === 'staff' && $note['staff_id'] == $staff_id)): ?>
                                                                <!-- Edit icon button — visible on all notes -->
                                                                <button type="button" class="edit-note-btn action-icon-btn edit-icon"
                                                                    title="Edit Note"
                                                                    data-id="<?= $note['id'] ?>"
                                                                    data-shift="<?= $note['shift_id'] ?>"
                                                                    data-text="<?= htmlspecialchars($note['note_text'], ENT_QUOTES, 'UTF-8') ?>"
                                                                    data-status="<?= $note['status'] ?>"
                                                                    data-bs-toggle="modal" data-bs-target="#noteModal">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                                </button>

                                                                <!-- Delete icon button -->
                                                                <form action="patient_notes.php?user_id=<?= $user_id ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this note?');" class="inline">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                                                                    <button type="submit" class="action-icon-btn delete-icon" title="Delete Note">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Footer -->
                            <div class="px-6 py-4 mt-10 flex flex-col sm:flex-row justify-between items-center gap-4">
                                <div class="text-sm text-gray-500">
                                    Showing <span class="font-semibold text-gray-700"><?= count($notes) ?></span> of <span class="font-semibold text-gray-700"><?= $totalNotes ?></span> records
                                </div>
                                <?php if ($totalPages > 1): ?>
                                <nav class="pagination-container">
                                    <a href="?user_id=<?= $user_id ?>&page=<?= $page - 1 ?>&shift_filter=<?= $shift_filter ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                                       class="pagination-arrow <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <a href="?user_id=<?= $user_id ?>&page=<?= $i ?>&shift_filter=<?= $shift_filter ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                                           class="pagination-btn <?= $page == $i ? 'active' : 'inactive' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>

                                    <a href="?user_id=<?= $user_id ?>&page=<?= $page + 1 ?>&shift_filter=<?= $shift_filter ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                                       class="pagination-arrow <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>

                <!-- Modal: Add / Edit Note -->
                <div class="modal fade modal-premium" id="noteModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog dialog max-w-lg w-full">
                        <div class="dialog-content">
                            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </span>
                            <div class="modal-premium-header">
                                <h4 class="text-xl font-bold" id="noteModalTitle">Add Note</h4>
                                <p class="modal-desc">Record clinical observations for this patient</p>
                            </div>
                            <form action="patient_notes.php?user_id=<?= $user_id ?>" method="POST" id="noteForm">
                                <div class="modal-premium-body">
                                    <input type="hidden" name="action" value="save_note">
                                    <input type="hidden" name="note_id" id="form_note_id" value="">
                                    <!-- Status set by JS ('draft' or 'final') before form.submit() -->
                                    <input type="hidden" name="note_status" id="form_note_status" value="final">
                                     <div class="mb-2">
                                        <label class="block text-sm font-bold text-gray-700 mb-2 border-b pb-1">Shift Type *</label>
                                        <select name="shift_id" id="form_shift_id" class="input w-full p-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none" required>
                                            <option value="">Select Shift</option>
                                            <?php foreach ($shifts as $s): ?>
                                                <option value="<?= $s['id'] ?>" <?= (!empty($_SESSION['user']['shift_id']) && (int)$_SESSION['user']['shift_id'] === (int)$s['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($s['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-2">
                                        <label class="block text-sm font-bold text-gray-700 mb-2 border-b pb-1">Note Content *</label>
                                        <textarea name="note_text" id="form_note_text" class="input w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition-all" style="height: 200px;" placeholder="Enter clinical observations or notes here..." required></textarea>
                                    </div>
                                </div>
                                <div class="modal-premium-footer">
                                    <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" id="btn_save_draft" class="btn-modal bg-gray-200 text-gray-700 hover:bg-gray-300">Save as Draft</button>
                                    <button type="button" id="btn_save_final" class="btn-modal bg-emerald-600 text-white hover:bg-emerald-700">Save Final</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php include './views/layouts/footer.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form          = document.getElementById('noteForm');
    const modalTitle    = document.getElementById('noteModalTitle');
    const inputNoteId   = document.getElementById('form_note_id');
    const inputStatus   = document.getElementById('form_note_status');
    const selectShift   = document.getElementById('form_shift_id');
    const inputNoteText = document.getElementById('form_note_text');

    // ── Add Note button ─ reset form for a fresh entry ──────────────────────
    document.querySelector('.add-note-btn')?.addEventListener('click', function () {
        modalTitle.textContent = 'Add Note';
        inputNoteId.value  = '';
        inputNoteText.value = '';
        inputStatus.value  = 'final';
        if (selectShift) selectShift.selectedIndex = 0;
    });

    // ── Edit Note buttons ────────────────────────────────────────────────────
    document.querySelectorAll('.edit-note-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            modalTitle.textContent = 'Edit Note';
            inputNoteId.value  = this.dataset.id;
            inputNoteText.value = this.dataset.text;
            // Preserve the note's existing status (draft stays draft, final stays final)
            inputStatus.value  = this.dataset.status || 'final';
            if (selectShift) selectShift.value = this.dataset.shift;
        });
    });

    // ── Save as Draft ────────────────────────────────────────────────────────
    document.getElementById('btn_save_draft')?.addEventListener('click', function () {
        inputStatus.value = 'draft';
        form.submit();
    });

    // ── Save Final ───────────────────────────────────────────────────────────
    document.getElementById('btn_save_final')?.addEventListener('click', function () {
        if (!confirm('Confirm finalizing? This cannot be undone.')) return;
        inputStatus.value = 'final';
        form.submit();
    });
});
</script>
</body>
</html>
