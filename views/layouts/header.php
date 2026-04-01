<?php
try {
    require_once './config/database.php';
    $user = null;
    if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id']) && $_SESSION['user']['id'] > 0) {
        $stmt = $pdo->prepare("SELECT username, email, profile_url, role FROM users WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['user']['id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            $user = [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'profile_url' => $userData['profile_url'] ?: 'public/img/avatars/default.jpg',
                'role' => $userData['role'] ?: 'user',
                'logout_url' => './auth/logout.php'
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $user = null;
}
?>
    <!-- Modern Compact Styling -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── Variables ── */
        :root {
            --primary:       #5ba590;
            --primary-hover: #4a9079;
            --border:        #e5e7eb;
            --bg:            #f7f8fa;
            --text:          #374151;
            --text-light:    #6b7280;
        }

        /* ── Base ── */
        body {
            font-family: 'Inter', sans-serif !important;
            font-size: 13.5px !important;
            color: var(--text) !important;
            background-color: var(--bg) !important;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Headings ── */
        h1 { font-size: 20px !important; font-weight: 700 !important; color: #111827 !important; }
        h2 { font-size: 17px !important; font-weight: 600 !important; color: #111827 !important; }
        h3 { font-size: 15px !important; font-weight: 600 !important; color: #1f2937 !important; }
        h4 { font-size: 14px !important; font-weight: 600 !important; color: #1f2937 !important; }
        h5, h6 { font-size: 13px !important; font-weight: 600 !important; }

        /* ── Top Header Bar ── */
        .header {
            box-shadow: none !important;
            border-bottom: 1px solid var(--border) !important;
            height: 56px !important;
        }
        .header-wrapper { height: 56px !important; }

        /* ── Page Container ── */
        .page-container { padding: 1.25rem 1.5rem !important; }

        /* ── Cards ── */
        .card {
            border: 1px solid var(--border) !important;
            box-shadow: none !important;
            border-radius: 8px !important;
            margin-bottom: 1rem !important;
        }
        .card-body { padding: 1.1rem !important; }

        /* ── Tables ── */
        .table-default th {
            font-size: 10.5px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.06em !important;
            color: var(--text-light) !important;
            background-color: #f8fafc !important;
            padding: 9px 14px !important;
            border-bottom: 1px solid var(--border) !important;
            white-space: nowrap !important;
        }
        .table-default td {
            padding: 8px 14px !important;
            font-size: 13px !important;
            color: var(--text) !important;
            border-bottom: 1px solid #f0f4f8 !important;
            vertical-align: middle !important;
        }
        .table-default tr:last-child td { border-bottom: none !important; }

        /* Responsive Table Wrapper */
        .table-responsive {
            display: block !important;
            width: 100% !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
        }

        /* ── Buttons ── */
        .btn {
            font-family: 'Inter', sans-serif !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            padding: 0 16px !important;
            border-radius: 6px !important;
            line-height: 1 !important;
            height: 34px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            box-shadow: none !important;
            transition: all 0.15s ease !important;
            white-space: nowrap !important;
        }
        .btn-sm {
            height: 30px !important;
            padding: 0 12px !important;
            font-size: 12px !important;
        }
        .btn-solid {
            background-color: var(--primary) !important;
            border: 1px solid var(--primary) !important;
            color: #fff !important;
        }
        .btn-solid:hover {
            background-color: var(--primary-hover) !important;
            border-color: var(--primary-hover) !important;
        }
        .btn-outline {
            background: transparent !important;
            border: 1px solid var(--border) !important;
            color: var(--text) !important;
        }
        .btn-outline:hover {
            background: #f3f4f6 !important;
            border-color: #d1d5db !important;
        }

        /* ── Inputs ── */
        .input, input[type="text"], input[type="email"], input[type="password"],
        input[type="search"], input[type="tel"], input[type="number"], select {
            font-family: 'Inter', sans-serif !important;
            font-size: 13px !important;
            padding: 0 12px;
            height: 34px;
            border-radius: 6px !important;
            border: 1px solid var(--border) !important;
            box-shadow: none !important;
            transition: all 0.15s ease !important;
            background-color: #fff !important;
        }
        textarea.input, textarea {
            font-family: 'Inter', sans-serif !important;
            font-size: 13px !important;
            padding: 8px 12px !important;
            min-height: 80px !important;
            height: auto !important;
            border-radius: 6px !important;
            border: 1px solid var(--border) !important;
        }
        .input-sm {
            height: 30px !important;
            padding: 0 10px !important;
            font-size: 12px !important;
        }
        .input:focus, input:focus, textarea:focus, select:focus {
            border-color: var(--primary) !important;
            outline: none !important;
            box-shadow: 0 0 0 1px var(--primary) !important; /* Subtle glow */
        }

        /* ── Scrollbar (Enhanced) ── */
        .table-responsive::-webkit-scrollbar,
        .sidebar::-webkit-scrollbar,
        .dropdown-menu::-webkit-scrollbar { 
            width: 4px; 
            height: 4px; 
        }
        .table-responsive::-webkit-scrollbar-thumb,
        .sidebar::-webkit-scrollbar-thumb,
        .dropdown-menu::-webkit-scrollbar-thumb { 
            background: transparent; 
            border-radius: 10px; 
        }
        .table-responsive:hover::-webkit-scrollbar-thumb,
        .sidebar:hover::-webkit-scrollbar-thumb,
        .dropdown-menu:hover::-webkit-scrollbar-thumb { 
            background: #cbd5e1; 
        }
        ::-webkit-scrollbar-track { background: transparent; }

        /* ── Avatar Letter (fallback) ── */
        .avatar-letter {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: var(--primary);
            color: #fff;
            font-weight: 600;
            font-size: inherit;
        }
    </style>
    <style>
        /* Loader Container */
        .loader-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            transition: opacity 0.4s ease;
        }

        /* Spinner Loader */
        .loader {
            width: 44px;
            height: 44px;
            border: 3px solid #e5e7eb;
            border-top-color: #66b19c;
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Hide content initially */
        .content-hidden {
            visibility: hidden;
            opacity: 0;
        }
        .content-visible {
            visibility: visible;
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        /* Avatar Letter Styles */
        .avatar-letter {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #66b19c;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            width: 100%;
            height: 100%;
            border-radius: 50% !important;
            aspect-ratio: 1 / 1;
            overflow: hidden;
        }
        .avatar-circle {
            border-radius: 50% !important;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 1 / 1;
        }

        /* ── Responsive Overrides ── */
        @media (max-width: 1024px) {
            .page-container { padding: 1rem !important; }
            .grid { gap: 1rem !important; }
        }

        @media (max-width: 768px) {
            .flex.items-center.gap-4 { gap: 0.75rem !important; }
            .avatar-md { width: 34px !important; height: 34px !important; min-width: 34px !important; line-height: 34px !important; }
            h3 { font-size: 14px !important; }
            .card-body { padding: 0.85rem !important; }
        }

        @media (max-width: 480px) {
            .page-container { padding: 0.75rem !important; }
            h1 { font-size: 18px !important; }
            h2 { font-size: 16px !important; }
        }

        @keyframes badge-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        #notification-badge {
            position: absolute !important;
            top: -2px !important;
            right: -2px !important;
            width: 16px !important;
            height: 16px !important;
            background-color: #ef4444 !important;
            color: white !important;
            font-size: 9px !important;
            font-weight: 800 !important;
            border-radius: 50% !important;
            border: 1.5px solid white !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            line-height: 1 !important;
            z-index: 10 !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.15) !important;
        }
    </style>
<?php
// Global helper function for avatars
if (!function_exists('renderAvatar')) {
    function renderAvatar($url, $name, $sizeClass = 'w-[32px]', $textSizeClass = 'text-sm') {
        $initial = !empty($name) ? strtoupper(substr($name, 0, 1)) : '?';
        
        // Extract size if it's in w-[XXpx] format
        $sizeStyle = "";
        if (preg_match('/w-\[(\d+px)\]/', $sizeClass, $matches)) {
            $size = $matches[1];
            $sizeStyle = "width: $size; height: $size; line-height: $size;";
        }

        // Robust onerror script: hide image and show letter
        $onError = "this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';";
        
        $html = '<span class="avatar avatar-circle ' . $sizeClass . '" style="min-width: unset; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; ' . $sizeStyle . '">';
        if (!empty($url)) {
            $html .= '<img class="avatar-img avatar-circle" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" loading="lazy" alt="Avatar" onerror="' . $onError . '" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">';
            $html .= '<div class="avatar-letter ' . $textSizeClass . '" style="display:none; width: 100%; height: 100%;">' . htmlspecialchars($initial) . '</div>';
        } else {
            $html .= '<div class="avatar-letter ' . $textSizeClass . '" style="width: 100%; height: 100%;">' . htmlspecialchars($initial) . '</div>';
        }
        $html .= '</span>';
        return $html;
    }
}
?>
    <div class="loader-container" id="page-loader">
        <span class="loader"></span>
    </div>
    <div id="main-content" class="content-hidden">
        <header class="header border-b border-gray-200 dark:border-gray-700">
    <div class="header-wrapper h-16">
        <!-- Header Nav Start -->
        <div class="header-action header-action-start">
            <div id="side-nav-toggle" class="side-nav-toggle header-action-item header-action-item-hoverable">
                <div class="text-2xl">
                    <svg class="side-nav-toggle-icon-expand" stroke="currentColor" fill="none" stroke-width="0"
                        viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h7"></path>
                    </svg>
                    <svg class="side-nav-toggle-icon-collapsed hidden" stroke="currentColor" fill="none"
                        stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" height="1em" width="1em"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </div>
            </div>
            <div class="side-nav-toggle-mobile header-action-item header-action-item-hoverable" data-bs-toggle="modal"
                data-bs-target="#mobile-nav-drawer">
                <div class="text-2xl">
                    <svg stroke="currentColor" fill="none" stroke-width="0" viewBox="0 0 24 24" height="1em" width="1em"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h7"></path>
                    </svg>
                </div>
            </div>
        </div>
        <!-- Header Nav End -->
        <div class="header-action header-action-end">
            <!-- Notification Bell -->
            <?php if ($user && strtolower($user['role']) !== 'patient'): ?>
                <div class="dropdown mr-3">
                    <button class="dropdown-toggle relative flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 transition-all duration-200 border-none p-0 flex-shrink-0" id="notification-dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="width: 38px !important; height: 38px !important; min-width: 38px !important; min-height: 38px !important; max-width: 38px !important; max-height: 38px !important; aspect-ratio: 1/1 !important;">
                        <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="20" width="20" xmlns="http://www.w3.org/2000/svg" class="text-gray-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <span id="notification-badge" style="display:none;">0</span>
                    </button>
                    <div class="dropdown-menu bottom-end !min-w-[320px] !p-0 overflow-hidden rounded-xl shadow-xl border border-gray-100">
                        <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                            <span class="font-bold text-gray-900">Notifications</span>
                            <button id="mark-all-read" class="text-[11px] font-bold text-teal-600 hover:text-teal-700 uppercase tracking-wider">Mark all as read</button>
                        </div>
                        <div id="notification-list" class="max-h-[360px] overflow-y-auto">
                            <!-- Notifications will be loaded here -->
                            <div class="p-8 text-center text-gray-400">
                                <i class="far fa-bell text-2xl mb-2 opacity-20 block"></i>
                                <span class="text-sm">No new notifications</span>
                            </div>
                        </div>
                        <div class="p-2 border-t border-gray-100 text-center">
                            <a href="notifications.php" class="text-[12px] font-semibold text-gray-500 hover:text-gray-700">View All Notifications</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($user): // Only render user dropdown if $user is set ?>
                <div class="dropdown">
                    <div class="dropdown-toggle" id="user-dropdown" data-bs-toggle="dropdown">
                        <div class="header-action-item flex items-center gap-2">
                            <?php echo renderAvatar($user['profile_url'], $user['username'], 'w-[32px]'); ?>
                            <div class="hidden md:block">
                                <div class="text-xs capitalize"><?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="font-bold"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                    </div>
                    <ul class="dropdown-menu bottom-end min-w-[240px]">
                        <li class="menu-item-header">
                            <div class="py-2 px-3 flex items-center gap-2">
                                <?php echo renderAvatar($user['profile_url'], $user['username'], 'w-[45px]', 'text-xl'); ?>
                                <div>
                                    <div class="font-bold text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="text-xs"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </li>
                        <li class="menu-item-divider"></li>
                        <li id="menu-item-29-2VewETdxAb" class="menu-item-divider"></li>
                        <li class="menu-item menu-item-hoverable gap-2 h-[35px]">
                            <a href="./auth/logout.php" class="flex items-center gap-2 w-full h-full">
                                <span class="text-xl opacity-50">
                                    <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                </span>
                                <span>Sign Out</span>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php else: // If no user, show a login link ?>
                <div class="header-action-item">
                    <a href="./login.php" class="flex items-center gap-2">
                        <span class="text-xl opacity-50">
                            <svg stroke="currentColor" fill="none" stroke-width="2" viewBox="0 0 24 24" height="1em"
                                width="1em" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                                </path>
                            </svg>
                        </span>
                        <span>Sign In</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
    </div>


    <!-- JavaScript to Remove Loader -->
    <script>
        window.addEventListener('load', function () {
            const loaderContainer = document.getElementById('page-loader');
            const mainContent = document.getElementById('main-content');

            if (loaderContainer && mainContent) {
                loaderContainer.style.opacity = '0';
                setTimeout(() => {
                    loaderContainer.style.display = 'none';
                    mainContent.classList.remove('content-hidden');
                    mainContent.classList.add('content-visible');
                }, 400);
            }
        });
    </script>
