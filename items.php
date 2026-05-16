<?php
// items.php - Main entry point for equipment management
$current_page = basename(__FILE__);
require_once 'bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Get database connection for permission checks
$conn = getConnection();

// Get user's role for the header
$user_role = getUserRole();

// Define permission requirements for each action
$action_permissions = [
    'list' => 'view_equipment',
    'view' => 'view_equipment',
    'create' => 'add_equipment',
    'edit' => 'edit_equipment'
];

// Get the action from URL
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Check if user has permission for this action
$required_permission = $action_permissions[$action] ?? 'view_equipment';

if (!hasPermission($required_permission)) {
    $_SESSION['toast_message'] = 'You do not have permission to ' .
        ($action === 'create' ? 'add equipment' : ($action === 'edit' ? 'edit equipment' : ($action === 'view' ? 'view equipment details' : 'access equipment')));
    $_SESSION['toast_type'] = 'error';
    header('Location: dashboard_full.php');
    exit();
}

$pageTitle = "Equipment Management - aBility";

// Set page title based on action
if ($action === 'create') {
    $pageTitle = "Add Equipment - aBility";
} elseif ($action === 'view' && $id) {
    // Try to get item name for page title
    try {
        $stmt = $conn->prepare("SELECT item_name FROM items WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        if ($item) {
            $pageTitle = htmlspecialchars($item['item_name']) . " - aBility";
        }
        $stmt->close();
    } catch (Exception $e) {
        // Ignore error for title
    }
} elseif ($action === 'edit' && $id) {
    $pageTitle = "Edit Equipment - aBility";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Titillium+Web:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700&display=swap');

        * {
            font-family: 'Titillium Web', sans-serif;
        }

        .titillium-web-extralight {
            font-family: "Titillium Web", sans-serif;
            font-weight: 200;
            font-style: normal;
        }

        .titillium-web-light {
            font-family: "Titillium Web", sans-serif;
            font-weight: 300;
            font-style: normal;
        }

        .titillium-web-regular {
            font-family: "Titillium Web", sans-serif;
            font-weight: 400;
            font-style: normal;
        }

        .titillium-web-semibold {
            font-family: "Titillium Web", sans-serif;
            font-weight: 600;
            font-style: normal;
        }

        .titillium-web-bold {
            font-family: "Titillium Web", sans-serif;
            font-weight: 700;
            font-style: normal;
        }

        .titillium-web-black {
            font-family: "Titillium Web", sans-serif;
            font-weight: 900;
            font-style: normal;
        }

        .titillium-web-extralight-italic {
            font-family: "Titillium Web", sans-serif;
            font-weight: 200;
            font-style: italic;
        }

        .titillium-web-light-italic {
            font-family: "Titillium Web", sans-serif;
            font-weight: 300;
            font-style: italic;
        }

        .titillium-web-regular-italic {
            font-family: "Titillium Web", sans-serif;
            font-weight: 400;
            font-style: italic;
        }

        .titillium-web-semibold-italic {
            font-family: "Titillium Web", sans-serif;
            font-weight: 600;
            font-style: italic;
        }

        .titillium-web-bold-italic {
            font-family: "Titillium Web", sans-serif;
            font-weight: 700;
            font-style: italic;
        }

        .titillium-web-black-italic {
            font-family: "Titillium Web", sans-serif;
            font-weight: 900;
            font-style: italic;
        }



        /* ==================== PAGE HEADER STYLES ==================== */
        .page-header-compact {
            background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);
            padding: 0.75rem 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .user-info-compact {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar-compact {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #20B2AA 0%, #1A8F89 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .user-details-compact h5 {
            color: white;
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            line-height: 1.3;
        }

        .user-details-compact .role-badge-compact {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .role-badge-compact i {
            font-size: 0.65rem;
        }

        .page-count-compact {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-left: 0.5rem;
        }

        .page-count-compact i {
            color: #4CAF50;
            font-size: 0.65rem;
        }

        .back-to-dashboard {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .back-to-dashboard:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-3px);
        }

        .header-actions-compact {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logout-btn-compact {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn-compact:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        /* Content Area */
        .content-wrapper {
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #234c6a 0%, #2c5a7a 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        .page-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1rem;
            }

            .page-header {
                padding: 1rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .page-header-compact .d-flex {
                flex-direction: column;
                gap: 1rem;
            }

            .user-info-compact {
                width: 100%;
            }

            .header-actions-compact {
                width: 100%;
                justify-content: flex-end;
            }
        }

        /* Stats Cards */
        .stats-row {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #234c6a;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #234c6a;
            line-height: 1.2;
        }

        .stat-card .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-icon {
            width: 45px;
            height: 45px;
            background: rgba(35, 76, 106, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #234c6a;
            font-size: 1.5rem;
        }
    </style>
</head>

<body>
    <!-- New Compact Header -->
    <div class="page-header-compact">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-4">

                <div class="user-info-compact">
                    <div class="user-avatar-compact">
                        <i class="fas <?php echo $user_role === 'admin' ? 'fa-crown' : 'fa-boxes'; ?>"></i>
                    </div>
                    <div class="user-details-compact">
                        <h5><?php echo htmlspecialchars(getUserFullName()); ?></h5>
                        <div>
                            <span class="role-badge-compact">
                                <i class="fas fa-user-tag"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $user_role)); ?>
                            </span>
                            <span class="page-count-compact">
                                <i class="fas fa-file-alt"></i>
                                1 Page
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="header-actions-compact">
                <a href="dashboard_full.php" class="back-to-dashboard">
                    Dashboard
                </a>
                <a href="#" class="logout-btn-compact" onclick="showLogoutToast(event)">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Logout Toast -->
    <div id="logoutToast" class="logout-toast" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; padding: 20px; box-shadow: 0 5px 30px rgba(0,0,0,0.3); z-index: 10000; min-width: 300px; text-align: center;">
        <div class="mb-3">
            <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center p-3">
                <i class="fas fa-sign-out-alt fa-2x text-warning"></i>
            </div>
        </div>
        <h5 class="mb-2">Confirm Logout</h5>
        <p class="text-muted mb-3">Are you sure you want to logout?</p>
        <div class="d-flex gap-2 justify-content-center">
            <button class="btn btn-secondary" onclick="hideLogoutToast()">Cancel</button>
            <a href="logout.php" class="btn btn-primary">Yes, Logout</a>
        </div>
    </div>

    <div id="toastOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999;" onclick="hideLogoutToast()"></div>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Page Header with Action Title -->

        <?php if ($action === 'list'): ?>

        <?php elseif ($action !== 'list'): ?>
            <a href="items.php" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        <?php endif; ?>
        <!-- Include the appropriate view based on action -->
        <?php
        switch ($action) {
            case 'create':
                require_once 'views/items/create.php';
                break;
            case 'edit':
                require_once 'views/items/edit.php';
                break;
            case 'view':
                require_once 'views/items/view.php';
                break;
            case 'list':
            default:
                require_once 'views/items/index.php';
                break;
        }
        ?>
    </div>

    <!-- // Add this to your items table to show QR codes -->
    <td>
        <?php if (!empty($item['qr_code'])): ?>
            <img src="<?php echo htmlspecialchars($item['qr_code']); ?>"
                alt="QR Code" style="width: 50px; height: 50px;">
            <br>
            <small>ID: <?php echo $item['id']; ?></small>
        <?php else: ?>
            <button class="btn btn-sm btn-outline-primary"
                onclick="generateQRCode(<?php echo $item['id']; ?>)">
                Generate QR
            </button>
        <?php endif; ?>
    </td>

    <!-- jQuery MUST be loaded first -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS Bundle (depends on jQuery) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables (depends on jQuery) -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- AOS (doesn't depend on jQuery) -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });

        // ==================== LOGOUT TOAST FUNCTIONS ====================
        function showLogoutToast(event) {
            event.preventDefault();
            document.getElementById('logoutToast').style.display = 'block';
            document.getElementById('toastOverlay').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function hideLogoutToast() {
            document.getElementById('logoutToast').style.display = 'none';
            document.getElementById('toastOverlay').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Close on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideLogoutToast();
            }
        });

        function generateQRCode(itemId) {
            if (confirm('Generate QR code for this item?')) {
                fetch('api/generate_qr.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: itemId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('QR code generated successfully');
                            location.reload();
                        } else {
                            alert('Failed to generate QR code');
                        }
                    });
            }
        }

        // Toast notification function for session messages
        function showToast(message, type = 'success', duration = 5000) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show`;
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '9999';
            toast.style.minWidth = '300px';
            toast.style.boxShadow = '0 5px 15px rgba(0,0,0,0.2)';
            toast.innerHTML = `
                ${type === 'success' ? '<i class="fas fa-check-circle me-2"></i>' : 
                  type === 'error' ? '<i class="fas fa-exclamation-triangle me-2"></i>' : 
                  '<i class="fas fa-info-circle me-2"></i>'}
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(toast);

            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, duration);
        }

        // Check for session messages
        <?php if (isset($_SESSION['toast_message'])): ?>
            showToast('<?php echo addslashes($_SESSION['toast_message']); ?>', '<?php echo $_SESSION['toast_type'] ?? 'success'; ?>');
            <?php
            unset($_SESSION['toast_message']);
            unset($_SESSION['toast_type']);
            ?>
        <?php endif; ?>
    </script>
</body>

</html>