<?php
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . '://' . $host . '/ability_app_main/';
    define('BASE_URL', $base_url);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'aBility Dashboard'); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>assets/images/warehouse.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="sidebar-layout">
    <div class="wrapper">
        <?php include_once 'sidebar.php'; ?>
        
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <nav aria-label="breadcrumb" class="me-auto">
                        <ol class="breadcrumb mb-0 py-2">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>index.php"><i class="fas fa-home"></i></a></li>
                            <?php if (isset($breadcrumbItems)): ?>
                                <?php foreach ($breadcrumbItems as $text => $link): ?>
                                    <?php if ($link): ?>
                                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL . $link; ?>"><?php echo htmlspecialchars($text); ?></a></li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($text); ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ol>
                    </nav>
                    <button type="button" id="sidebarToggle" class="btn btn-outline-secondary ms-2 d-lg-none">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </nav>
            
            <div class="container-fluid p-4">