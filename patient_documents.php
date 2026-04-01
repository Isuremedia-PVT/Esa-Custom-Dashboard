<?php
session_start();
require_once './config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: ./signin.php");
    exit;
}

$is_admin = $_SESSION['user']['role'] === 'admin';
$is_staff = $_SESSION['user']['role'] === 'staff';
$is_patient = $_SESSION['user']['role'] === 'patient';

$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$user_id) {
    if ($is_patient) {
        $user_id = $_SESSION['user']['id'];
    } else {
        die("Invalid User ID");
    }
}

// Security: Patients can only view their own documents
if ($is_patient && (int)$user_id !== (int)$_SESSION['user']['id']) {
    die("Unauthorized access.");
}

$page_title = 'Patient Documents';
include './views/layouts/head.php';
require_once './views/layouts/alert.php';
?>

<div id="root">
    <div class="app-layout-modern flex flex-auto flex-col">
        <div class="flex flex-auto min-w-0">
            <?php include './views/layouts/sidebar.php'; ?>
            
            <div class="flex flex-col flex-auto min-h-screen min-w-0 relative w-full bg-white border-l border-gray-200">
                <?php include './views/layouts/header.php'; ?>
                
                <main class="h-full">
                    <div class="page-container relative h-full flex flex-auto flex-col px-4 sm:px-6 md:px-8 py-4 sm:py-6">
                        <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b pb-4">
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-800">Patient Documents</h3>
                                <p class="text-gray-500 text-sm">Manage and store important records for this patient.</p>
                            </div>
                            <div class="flex space-x-2 items-center">
                                 <a href="patient_question_details.php?user_id=<?= $user_id ?>" class="btn btn-default btn-sm">
                                    <i class="fas fa-arrow-left"></i> <span class="ml-1">Back to Profile</span>
                                 </a>
                                <?php if ($is_admin || $is_staff): ?>
                                 <button type="button" data-bs-toggle="modal" data-bs-target="#uploadDocModal" class="btn btn-solid btn-sm">
                                    <i class="fas fa-upload"></i> <span class="ml-1">Upload Document</span>
                                 </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="alertContainer"><?= displayAlert() ?></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="documentsList">
                            <!-- Documents will be loaded here -->
                        </div>
                        
                        <div id="emptyState" class="hidden text-center py-20">
                            <i class="fas fa-folder-open text-gray-300 text-6xl mb-4"></i>
                            <p class="text-gray-500">No documents found for this patient.</p>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade modal-premium" id="uploadDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog dialog max-w-lg w-full">
        <div class="dialog-content">
            <span class="close-btn absolute z-10" role="button" data-bs-dismiss="modal">
                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </span>
            <div class="modal-premium-header">
                <h4 class="text-xl font-bold">Upload New Document</h4>
                <p class="modal-desc">Add a new file to the patient's records</p>
            </div>
            <form id="uploadDocForm">
                <div class="modal-premium-body">
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Document Name (Optional)</label>
                            <input type="text" name="document_name" class="input w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" placeholder="e.g., Blood Test Report">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Category</label>
                            <select name="category" class="input w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all">
                                <option value="Report">Report</option>
                                <option value="Prescription">Prescription</option>
                                <option value="Scan">Scan</option>
                                <option value="Image">Image</option>
                                <option value="Other" selected>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Description (Optional)</label>
                        <textarea name="description" class="input w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all" rows="2" placeholder="Brief description of the document..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Select File</label>
                        <input type="file" name="document" class="input w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition-all cursor-pointer file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100" required>
                        <p class="text-xs text-gray-400 mt-1.5">Supported formats: PDF, Images (JPG, PNG), Docx</p>
                    </div>
                </div>
                <div class="modal-premium-footer">
                    <button type="button" class="btn-modal btn-modal-gray" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modal bg-teal-600 text-white hover:bg-teal-700" id="uploadBtn">
                        <i class="fas fa-upload mr-2"></i>Start Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include './views/layouts/footer.php'; ?>

<script>
$(document).ready(function() {
    const userId = <?= $user_id ?>;
    const isAdmin = <?= json_encode($is_admin) ?>;
    const isStaff = <?= json_encode($is_staff) ?>;

    function loadDocuments() {
        $.get(`api/api.php?action=get_documents&user_id=${userId}`, function(response) {
            console.log('Get documents response:', response);
            const res = typeof response === 'string' ? JSON.parse(response) : response;
            const list = $('#documentsList');
            list.empty();
            
            if (res.success && res.data.length > 0) {
                $('#emptyState').addClass('hidden');
                res.data.forEach(function(doc) {
                    const isImage = doc.file_type && doc.file_type.startsWith('image/');
                    const isPdf = doc.file_type === 'application/pdf';
                    let previewHtml = '';
                    const fileUrl = doc.file_path;
                    
                    if (isImage) {
                        previewHtml = `<img src="${fileUrl}" class="w-full h-40 object-cover rounded-t-xl" alt="Preview">`;
                    } else if (isPdf) {
                        previewHtml = `<div class="w-full h-40 bg-red-50 flex items-center justify-center rounded-t-xl text-red-500">
                                            <i class="fas fa-file-pdf text-5xl"></i>
                                       </div>`;
                    } else {
                        previewHtml = `<div class="w-full h-40 bg-gray-50 flex items-center justify-center rounded-t-xl text-gray-400">
                                            <i class="fas fa-file-alt text-5xl"></i>
                                       </div>`;
                    }

                    const categoryColors = {
                        'Report': 'bg-blue-100 text-blue-800',
                        'Prescription': 'bg-green-100 text-green-800',
                        'Scan': 'bg-purple-100 text-purple-800',
                        'Image': 'bg-yellow-100 text-yellow-800',
                        'Other': 'bg-gray-100 text-gray-800'
                    };
                    const badgeClass = categoryColors[doc.category] || categoryColors['Other'];

                    const cardHtml = `
                        <div class="group relative bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300 border border-gray-100 overflow-hidden">
                            ${previewHtml}
                            <div class="p-4">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${badgeClass}">${doc.category}</span>
                                </div>
                                <h4 class="font-bold text-gray-800 truncate" title="${doc.file_name}">${doc.file_name}</h4>
                                <p class="text-xs text-gray-500 mt-1 line-clamp-1">${doc.description || ''}</p>
                                <div class="flex justify-between items-center mt-3">
                                    <span class="text-xs text-gray-400">${new Date(doc.created_at).toLocaleDateString()}</span>
                                    <div class="flex space-x-2">
                                        <a href="${fileUrl}" target="_blank" class="action-btn action-btn-blue p-1" title="Download/View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        ${(isAdmin || isStaff) ? `
                                        <button onclick="deleteDoc(${doc.id})" class="action-btn action-btn-red p-1" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    list.append(cardHtml);
                });
            } else {
                $('#emptyState').removeClass('hidden');
            }
        });
    }

    $('#uploadDocForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const btn = $('#uploadBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Uploading...');

        $.ajax({
            url: 'api/api.php?action=upload_document',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                console.log('Upload response:', response);
                const res = typeof response === 'string' ? JSON.parse(response) : response;
                if (res.success) {
                    $('#uploadDocModal').modal('hide');
                    showAlert('success', res.message);
                    loadDocuments();
                    $('#uploadDocForm')[0].reset();
                } else {
                    showAlert('error', res.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Upload AJAX error:', error);
                showAlert('error', 'An error occurred while uploading the document.');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-upload mr-2"></i> Upload Document');
            }
        });
    });

    window.deleteDoc = function(docId) {
        if (confirm('Are you sure you want to delete this document?')) {
            $.ajax({
                url: 'api/api.php?action=delete_document',
                type: 'POST',
                data: JSON.stringify({ document_id: docId }),
                contentType: 'application/json',
                success: function(response) {
                    const res = typeof response === 'string' ? JSON.parse(response) : response;
                    if (res.success) {
                        showAlert('success', res.message);
                        loadDocuments();
                    } else {
                        showAlert('error', res.message);
                    }
                },
            });
        }
    };

    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show mb-4" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $('#alertContainer').html(alertHtml);
        setTimeout(() => $('.alert').alert('close'), 4000);
    }

    loadDocuments();
});
</script>
</body>
</html>
