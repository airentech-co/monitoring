<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            transition: all 0.3s ease;
        }
        
        .main-content {
            transition: margin-left 0.3s ease;
        }
        
        #sidebarToggle {
            transition: all 0.2s ease;
        }
        
        #sidebarToggle:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        
        #sidebarToggle.active {
            background-color: #495057;
            border-color: #495057;
            color: white;
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1000;
                width: 100%;
                max-width: 300px;
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .sidebar-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
        }
        .sidebar .nav-link {
            color: #adb5bd;
        }
        .sidebar .nav-link:hover {
            color: #fff;
        }
        .sidebar .nav-link.active {
            color: #fff;
            background: #495057;
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1000;
                width: 100%;
                max-width: 300px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .sidebar-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
        }
        
        @media (min-width: 768px) {
            .sidebar.collapsed {
                width: 0;
                overflow: hidden;
                padding: 0;
                flex: 0 0 0;
                min-width: 0;
            }
            .main-content.expanded {
                margin-left: 0;
                max-width: 100%;
                width: 100%;
            }
        }
        .main-content {
            /* min-height: 100vh; */
        }
        .screenshot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        .screenshot-card {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            overflow: hidden;
        }
        .screenshot-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .logs-sidebar {
            height: calc(100vh - 100px);
            overflow-y: auto;
        }
        .logs-content {
            height: calc(100vh - 100px);
            overflow-y: auto;
        }
        .log-file {
            cursor: pointer;
            padding: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        .log-file:hover {
            background: #f8f9fa;
        }
        .log-file.active {
            background: #e9ecef;
        }
        
        /* Ensure USB icon displays properly */
        .fa-usb {
            font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", "FontAwesome";
            font-weight: 900;
        }
        
        /* Fallback for USB icon if not available */
        .fa-usb:before {
            content: "\f287";
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white"><?php echo APP_NAME; ?></h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_page === 'users' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>users">
                                <i class="fas fa-users me-2"></i>
                                User Management
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_page === 'monitors' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>monitors">
                                <i class="fas fa-desktop me-2"></i>
                                IP Mapping
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_page === 'view' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>view">
                                <i class="fas fa-eye me-2"></i>
                                Real-time Monitoring
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_page === 'logout' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>auth/logout">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content" id="mainContent">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-outline-secondary me-3" type="button" id="sidebarToggle" aria-label="Toggle navigation" title="Toggle Sidebar" data-bs-toggle="tooltip" data-bs-placement="bottom">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="h2 mb-0"><?php echo $page_title ?? 'Dashboard'; ?></h1>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <?php echo $content ?? ''; ?>
            </main>
        </div>
    </div>
    <?php else: ?>
        <?php echo $content ?? ''; ?>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Sidebar toggle functionality
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        const sidebar = $('#sidebar');
        const mainContent = $('.main-content');
        const sidebarToggle = $('#sidebarToggle');
        const toggleIcon = sidebarToggle.find('i');
        
        // Check if sidebar state is saved in localStorage
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        // Initialize sidebar state
        if (sidebarCollapsed && window.innerWidth >= 768) {
            sidebar.addClass('collapsed');
            mainContent.addClass('expanded');
            toggleIcon.removeClass('fa-bars').addClass('fa-chevron-right');
            sidebarToggle.addClass('active');
        }
        
        // Toggle button click handler
        sidebarToggle.on('click', function() {
            if (window.innerWidth < 768) {
                // Mobile behavior
                if (sidebar.hasClass('show')) {
                    sidebar.removeClass('show');
                    $('.sidebar-backdrop').remove();
                } else {
                    sidebar.addClass('show');
                    $('body').append('<div class="sidebar-backdrop"></div>');
                    
                    // Close sidebar when clicking backdrop
                    $('.sidebar-backdrop').on('click', function() {
                        sidebar.removeClass('show');
                        $(this).remove();
                    });
                }
            } else {
                // Desktop behavior
                if (sidebar.hasClass('collapsed')) {
                    sidebar.removeClass('collapsed');
                    mainContent.removeClass('expanded');
                    toggleIcon.removeClass('fa-chevron-right').addClass('fa-bars');
                    sidebarToggle.removeClass('active');
                    localStorage.setItem('sidebarCollapsed', 'false');
                } else {
                    sidebar.addClass('collapsed');
                    mainContent.addClass('expanded');
                    toggleIcon.removeClass('fa-bars').addClass('fa-chevron-right');
                    sidebarToggle.addClass('active');
                    localStorage.setItem('sidebarCollapsed', 'true');
                }
            }
        });
        
        // Handle window resize
        $(window).on('resize', function() {
            if (window.innerWidth >= 768) {
                // Desktop mode
                sidebar.removeClass('show');
                $('.sidebar-backdrop').remove();
                if (sidebarCollapsed) {
                    sidebar.addClass('collapsed');
                    mainContent.addClass('expanded');
                    toggleIcon.removeClass('fa-bars').addClass('fa-chevron-right');
                }
            } else {
                // Mobile mode
                sidebar.removeClass('collapsed');
                mainContent.removeClass('expanded');
                toggleIcon.removeClass('fa-chevron-right').addClass('fa-bars');
            }
        });
        
        // Close mobile sidebar when clicking a link
        sidebar.find('.nav-link').on('click', function() {
            if (window.innerWidth < 768) {
                sidebar.removeClass('show');
                $('.sidebar-backdrop').remove();
            }
        });
        
        // Keyboard shortcut: Ctrl/Cmd + B to toggle sidebar
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                sidebarToggle.click();
            }
        });
    });
    </script>
    
    <?php if (isset($scripts)): ?>
        <?php echo $scripts; ?>
    <?php endif; ?>
</body>
</html> 