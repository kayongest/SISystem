<?php
// cases.php - Controller for Fly Cases Management
$current_page = basename(__FILE__);
require_once 'bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Get database connection
$conn = getConnection();

// Get user's role for the header
$user_role = getUserRole();

// Restrict access for drivers
if ($user_role === 'driver') {
    $_SESSION['toast_message'] = 'Drivers do not have permission to access Fly Case Management.';
    $_SESSION['toast_type'] = 'error';
    header('Location: driver_batches.php');
    exit();
}

// Get action and ID
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

$pageTitle = "Fly Cases - aBility";

// Set page title based on action
if ($action === 'view' && $id) {
    try {
        $stmt = $conn->prepare("SELECT item_name FROM items WHERE id = ? AND (category = 'Cases' OR item_name LIKE '%Case%')");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        if ($item) {
            $pageTitle = htmlspecialchars($item['item_name']) . " - Packing Details";
        }
        $stmt->close();
    } catch (Exception $e) {
        // Ignore and use default title
    }
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

    <!-- Fixed Toastr CSS (specific stable version) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">

    <!-- jQuery, Bootstrap JS, DataTables JS (loaded early for inline script execution) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Titillium+Web:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700&display=swap');

        * {
            font-family: 'Titillium Web', sans-serif;
        }

        body {
            background-color: #f4f7f6;
        }

        .header-container {
            background: linear-gradient(135deg, #10314b 0%, #1a2e3f 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            border-bottom: 4px solid #20B2AA;
        }

        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            border-radius: 15px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.08);
        }

        .table-container {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.03);
        }

        .badge-status {
            font-size: 0.8rem;
            padding: 0.35em 0.7em;
            border-radius: 30px;
        }

        /* Toast Styling */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <?php 
    $skip_navbar = false; 
    include 'views/partials/header.php'; 
    ?>

    <div class="container mt-4 mb-5">
        <?php
        // Route actions
        switch ($action) {
            case 'view':
                require_once 'views/cases/view.php';
                break;
            case 'list':
            default:
                require_once 'views/cases/index.php';
                break;
        }
        ?>
    </div>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Toast configuration
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000"
        };

        // Trigger toast messages from PHP session if they exist
        <?php if (isset($_SESSION['toast_message'])): ?>
            let toastType = '<?php echo $_SESSION['toast_type'] ?? 'success'; ?>';
            let toastMessage = '<?php echo addslashes($_SESSION['toast_message']); ?>';
            if (toastType === 'error') toastType = 'error';
            if (toastType === 'warning') toastType = 'warning';
            if (toastType === 'info') toastType = 'info';
            toastr[toastType](toastMessage);
            <?php
            unset($_SESSION['toast_message']);
            unset($_SESSION['toast_type']);
            ?>
        <?php endif; ?>
    </script>
</body>

</html>
