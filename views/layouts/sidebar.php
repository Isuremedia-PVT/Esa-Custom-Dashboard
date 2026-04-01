<?php
$current_page = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ".php");
$user_role = isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '';
$s_user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : ''; // Define user_id from session
?>

<style>
    /* ── Sidebar Base ── */
    .side-nav {
        background-color: #5ba590 !important;
        border-right: none !important;
        width: 235px !important; /* Reduced width */
        transition: width 0.3s ease !important;
    }
    
    .side-nav-collapsed {
        width: 70px !important;
    }

    /* ── Logo Area ── */
    .side-nav .side-nav-header {
        border-bottom: 1px solid rgba(255,255,255,0.08) !important;
        padding: 0 1.25rem !important;
        height: 65px !important; /* Slightly taller for breathing room */
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
    }
    .side-nav .side-nav-header .logo img {
        max-height: 32px !important;
        width: auto !important;
        object-fit: contain !important;
    }

    /* ── Content Spacing (Menu touches Logo fix) ── */
    .side-nav .side-nav-content nav.menu {
        padding-top: 20px !important; /* More spacing from logo */
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    /* ── KILL THEME DEFAULTS (Kill double hover/active highlights) ── */
    .menu-item-link::before,
    .menu-item-link::after,
    .menu-item::before,
    .menu-item::after,
    .menu-item-link:hover,
    .menu-item.active,
    .menu-item-single.active {
        background-color: transparent !important;
        box-shadow: none !important;
        border: none !important;
    }

    /* ── Our Clean Nav Styling ── */
    .side-nav .menu-item {
        margin: 2px 0 !important;
        padding: 0 !important;
        position: relative !important;
        background: transparent !important;
    }
    
    .side-nav .menu-item-link {
        padding: 9px 12px !important;
        border-radius: 6px !important;
        font-size: 13.5px !important;
        font-weight: 500 !important;
        color: rgba(255, 255, 255, 0.85) !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        transition: all 0.2s ease !important;
        height: auto !important;
        min-height: 40px !important;
        text-decoration: none !important;
        background: transparent !important;
    }

    /* ── THE ONLY HOVER/ACTIVE EFFECT WE WANT ── */
    .side-nav .menu-item-link:hover,
    .side-nav .menu-item.active > .menu-item-link,
    .side-nav .menu-item-single.active > .menu-item-link {
        background-color: rgba(0, 0, 0, 0.15) !important;
        color: #ffffff !important;
        box-shadow: none !important;
    }

    /* ── Icons ── */
    .side-nav .menu-item-icon {
        width: 18px !important;
        height: 18px !important;
        min-width: 18px !important;
        color: rgba(255, 255, 255, 0.82) !important;
        flex-shrink: 0 !important;
    }
    .side-nav .menu-item-link:hover .menu-item-icon,
    .side-nav .menu-item.active .menu-item-icon {
        color: #ffffff !important;
    }

    /* ── COLLAPSED STATE ── */
    .side-nav-collapsed nav.menu {
        padding: 20px 0 0 0 !important;
    }
    .side-nav-collapsed .menu-item {
        margin: 4px 0 !important;
        display: flex !important;
        justify-content: center !important;
    }
    .side-nav-collapsed .menu-item-link {
        width: 44px !important;
        height: 44px !important;
        padding: 0 !important;
        justify-content: center !important;
        border-radius: 8px !important;
        margin: 0 auto !important;
    }
    .side-nav-collapsed .menu-item-text {
        display: none !important;
    }
    .side-nav-collapsed .side-nav-header {
        justify-content: center !important;
        padding: 0 !important;
    }
    .side-nav-collapsed .side-nav-header .logo img {
        max-height: 26px !important;
        max-width: 40px !important;
    }

    /* ── Mobile Sidebar ── */
    .side-nav-mobile { background-color: #5ba590 !important; }
    .side-nav-mobile .menu-item-link {
        color: #ffffff !important;
        padding: 12px 16px !important;
        border-radius: 8px !important;
        margin: 2px 10px !important;
    }
    .side-nav-mobile .menu-item-link:hover {
        background-color: rgba(0,0,0,0.1) !important;
    }
    .drawer-header {
        background-color: #5ba590 !important;
        color: #ffffff !important;
        border-bottom: 1px solid rgba(255,255,255,0.1) !important;
    }
</style>


<div class="side-nav side-nav-transparent side-nav-expand">
    <div class="side-nav-header">
        <div class="logo">
            <a href="./dashboard.php">
                <img src="./public/img/logo/logo.png" alt="Cocoon Baby Logo">
            </a>
        </div>
    </div>
    <div class="side-nav-content relative side-nav-scroll">
        <nav class="menu menu-transparent px-4 pb-4" style="transition: padding 0.3s ease;">
            <div class="menu-group">
                <ul>
    <?php if ($user_role === 'staff'): ?>
        <!-- Staff: Show only Dashboard with user ID -->
        <li data-menu-item="dashboard" class="menu-item menu-item-single mb-2 <?= $current_page === 'dashboard' ? 'active' : '' ?>">
            <a class="menu-item-link" href="./view_staff.php?id=<?= htmlspecialchars($s_user_id) ?>" aria-label="Go to Dashboard">
                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span class="menu-item-text">Your Details</span>
            </a>
        </li>
        </li>
    <?php elseif ($user_role === 'patient'): ?>
        <!-- Patient: Show only Your Details -->
        <li data-menu-item="patient-details" class="menu-item menu-item-single mb-2 <?= $current_page === 'patient-details' ? 'active' : '' ?>">
            <a class="menu-item-link" href="./patient-details.php" aria-label="Go to Your Details">
                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span class="menu-item-text">Your Details</span>
            </a>
        </li>
    <?php else: ?>
        <!-- Default: Show all menu items (for admins or undefined roles) -->
        <li data-menu-item="dashboard" class="menu-item menu-item-single mb-2 <?= $current_page === 'dashboard' ? 'active' : '' ?>">
            <a class="menu-item-link" href="./dashboard.php" aria-label="Go to Dashboard">
                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span class="menu-item-text">Dashboard</span>
            </a>
        </li>
        <li data-menu-item="new-registration" class="menu-item menu-item-single mb-2 <?= $current_page === 'new-registration' ? 'active' : '' ?>">
            <a class="menu-item-link" href="./new-registration.php" aria-label="Go to New Registration">
                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
                <span class="menu-item-text">New Registration</span>
            </a>
        </li>
        <li data-menu-item="staff" class="menu-item menu-item-single mb-2 <?= in_array($current_page, ['staff', 'edit_staff']) ? 'active' : '' ?>">
            <a class="menu-item-link" href="./staff.php" aria-label="Go to Staff">
                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span class="menu-item-text">Staff</span>
            </a>
        </li>
        <li data-menu-item="patient" class="menu-item menu-item-single mb-2 <?= $current_page === 'patient' ? 'active' : '' ?>">
            <a class="menu-item-link" href="./patient.php" aria-label="Go to Patients">
                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="menu-item-text">Patients</span>
            </a>
        </li>
        <li data-menu-item="questionaries" class="menu-item menu-item-single mb-2 <?= $current_page === 'questionaries' ? 'active' : '' ?>">
            <a class="menu-item-link" href="./question.php" aria-label="Go to Questionaries">
                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="menu-item-text">Questions</span>
            </a>
        </li>
        <li data-menu-item="shifts" class="menu-item menu-item-single mb-2 <?= $current_page === 'shifts' ? 'active' : '' ?>">
            <a class="menu-item-link" href="./shifts.php" aria-label="Go to Shifts">
                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="menu-item-text">Shifts</span>
            </a>
        </li>
    <?php endif; ?>
</ul>
            </div>
        </nav>
    </div>
</div>

<div class="modal fade" id="mobile-nav-drawer" tabindex="-1" aria-modal="true" role="dialog">
    <div class="modal-dialog drawer drawer-start !w-[330px]">
        <div class="drawer-content">
            <div class="drawer-header">
                <h4>Navigation</h4>
                <span class="close-btn" role="button" data-bs-dismiss="modal">
                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 20 20" aria-hidden="true" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </span>
            </div>
            <div class="drawer-body p-0">
                <div class="side-nav-mobile">
                    <div class="side-nav-content relative side-nav-scroll">
                        <nav class="menu menu-transparent px-4 pb-4">
                            <div class="menu-group">
                                <ul>
                                    <?php if ($user_role === 'staff'): ?>
                                        <!-- Staff: Show only Dashboard -->
                                        <li data-menu-item="dashboard" class="menu-item menu-item-single mb-2 <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                                            <a class="menu-item-link" href="./dashboard.php" aria-label="Go to Dashboard">
                                                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                                </svg>
                                                <span class="menu-item-text">Dashboard</span>
                                            </a>
                                        </li>
                                    <?php elseif ($user_role === 'patient'): ?>
                                        <!-- Patient: Show only Your Details -->
                                        <li data-menu-item="patient-details" class="menu-item menu-item-single mb-2 <?= $current_page === 'patient-details' ? 'active' : '' ?>">
                                            <a class="menu-item-link" href="./patient-details.php" aria-label="Go to Your Details">
                                                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                                <span class="menu-item-text">Your Details</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <!-- Default: Show all menu items (for admins or undefined roles) -->
                                        <li data-menu-item="dashboard" class="menu-item menu-item-single mb-2 <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                                            <a class="menu-item-link" href="./dashboard.php" aria-label="Go to Dashboard">
                                                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                                </svg>
                                                <span class="menu-item-text">Dashboard</span>
                                            </a>
                                        </li>
                                        <li data-menu-item="new-registration" class="menu-item menu-item-single mb-2 <?= $current_page === 'new-registration' ? 'active' : '' ?>">
                                            <a class="menu-item-link" href="./new-registration.php" aria-label="Go to New Registration">
                                                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                                </svg>
                                                <span class="menu-item-text">New Registration</span>
                                            </a>
                                        </li>
                                        <li data-menu-item="staff" class="menu-item menu-item-single mb-2 <?= in_array($current_page, ['staff', 'edit_staff']) ? 'active' : '' ?>">
                                            <a class="menu-item-link" href="./staff.php" aria-label="Go to Staff">
                                                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                <span class="menu-item-text">Staff</span>
                                            </a>
                                        </li>
                                        <li data-menu-item="patient" class="menu-item menu-item-single mb-2 <?= $current_page === 'patient' ? 'active' : '' ?>">
                                            <a class="menu-item-link" href="./patient.php" aria-label="Go to Patients">
                                                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                </svg>
                                                <span class="menu-item-text">Patients</span>
                                            </a>
                                        </li>
                                        <li data-menu-item="questionaries" class="menu-item menu-item-single mb-2 <?= $current_page === 'questionaries' ? 'active' : '' ?>">
                                            <a class="menu-item-link" href="./question.php" aria-label="Go to Questionaries">
                                                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span class="menu-item-text">Questions</span>
                                            </a>
                                        </li>
                                        <li data-menu-item="shifts" class="menu-item menu-item-single mb-2 <?= $current_page === 'shifts' ? 'active' : '' ?>">
                                            <a class="menu-item-link" href="./shifts.php" aria-label="Go to Shifts">
                                                <svg class="menu-item-icon" stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span class="menu-item-text">Shifts</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>