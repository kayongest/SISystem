<?php
session_start();
if (!defined('BASE_URL')) {
    define('BASE_URL', '/ability_app_main/');
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

$user_role = getUserRole();
if ($user_role === 'driver') {
    header("Location: dashboard_full.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Events - Ability App</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Marvel:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- DataTables for Equipment List -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <style>

        :root {
            --bg-light: #f5f7fb;
            --card-dark: #ffffff;
            --accent-gold: #5aa9e2;
            --accent-gold-hover: #5aa9e2;
            --text-light: #333333;
            --text-muted: #6c757d;
            --border-color: #e0e0e0;
            --bs-body-font-family: 'Marvel', sans-serif;
            --bs-font-sans-serif: 'Marvel', sans-serif;
        }

        * {
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-light);
            font-family: var(--bs-body-font-family) !important;
            margin: 0;
            padding: 0; 
        }

        /* Toolbar */
        .events-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 15px;
        }

        .view-controls {
            display: flex;
            gap: 5px;
            background: var(--card-dark);
            padding: 5px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .btn-view {
            background: transparent;
            border: none;
            color: var(--text-muted);
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-view.active {
            background: var(--accent-gold);
            color: #121212;
        }

        .btn-view:hover:not(.active) {
            color: var(--text-light);
            background: rgba(255,255,255,0.1);
        }

        .search-wrapper {
            flex-grow: 1;
            max-width: 400px;
            position: relative;
        }

        .search-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .search-input {
            width: 100%;
            background: var(--card-dark);
            border: 1px solid var(--border-color);
            color: var(--text-light);
            padding: 10px 15px 10px 40px;
            border-radius: 20px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 2px rgba(226, 183, 90, 0.2);
        }

        /* Event Card (Grid View) */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .event-card {
            background-color: var(--card-dark);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            font-family: var(--bs-body-font-family) !important;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border-color: #444;
        }

        .event-img-wrapper {
            position: relative;
            height: 200px;
            width: 100%;
            background: #2a2a2a;
        }

        .event-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #444;
            background: linear-gradient(45deg, #1e1e1e, #2a2a2a);
        }

        .event-status-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #0d47a1;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .event-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .event-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .event-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .event-meta i {
            width: 16px;
            color: var(--accent-gold);
        }

        .event-actions {
            margin-top: auto;
            padding-top: 20px;
        }

        .btn-view-details {
            background: var(--accent-gold);
            color: #121212;
            width: 100%;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: background 0.2s;
        }

        .btn-view-details:hover {
            background: var(--accent-gold-hover);
        }

        /* List View */
        .events-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .events-list .event-card {
            flex-direction: row;
            height: 150px;
        }
        
        .events-list .event-img-wrapper {
            width: 200px;
            height: 100%;
        }
        
        .events-list .event-actions {
            margin-top: 0;
            padding-top: 0;
            display: flex;
            align-items: center;
            padding-left: 20px;
            border-left: 1px solid var(--border-color);
            width: 200px;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .events-list .event-card {
                flex-direction: column;
                height: auto;
            }
            .events-list .event-img-wrapper {
                width: 100%;
                height: 150px;
            }
            .events-list .event-actions {
                width: 100%;
                border-left: none;
                border-top: 1px solid var(--border-color);
                padding: 15px 20px;
            }
        }

        /* Quick Nav Modal */
        .quick-nav-modal .modal-content {
            background: var(--card-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            color: var(--text-light);
        }
        
        .quick-nav-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .quick-nav-item:hover {
            background: rgba(255,255,255,0.05);
        }

        .quick-nav-item:last-child {
            border-bottom: none;
        }

        .shortcut-key {
            background: rgba(255,255,255,0.1);
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .btn-got-it {
            background: var(--accent-gold);
            color: #121212;
            width: 100%;
            border: none;
            padding: 10px;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 15px;
        }

        /* Details Modal */
        .details-modal .modal-content {
            background-color: var(--card-dark);
            border: 1px solid var(--border-color);
            color: var(--text-light) !important;
            font-family: 'Marvel', sans-serif !important;
        }
        
        .details-modal .modal-header {
            border-bottom: 1px solid var(--border-color);
            background: var(--card-dark);
        }

        .details-modal .btn-close {
            
        }

        /* DataTables Dark Theme Overrides */
        table.dataTable {
            color: var(--text-light) !important;
        }
        table.dataTable tbody tr {
            background-color: var(--card-dark) !important;
        }
        table.dataTable tbody tr:hover {
            background-color: #2a2a2a !important;
        }
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter, 
        .dataTables_wrapper .dataTables_info, 
        .dataTables_wrapper .dataTables_processing, 
        .dataTables_wrapper .dataTables_paginate {
            color: var(--text-muted) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current, 
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--accent-gold) !important;
            color: #121212 !important;
            border-color: var(--accent-gold) !important;
        }
        .page-item.disabled .page-link {
            background-color: transparent;
            border-color: var(--border-color);
        }
        .page-link {
            background-color: var(--card-dark);
            border-color: var(--border-color);
            color: var(--text-light);
        }
        .table-light {
            --bs-table-bg: var(--card-dark);
            --bs-table-border-color: var(--border-color);
        }

        /* Bulletproof Font Override (Excludes Icons) */
        *:not(i):not(.fas):not(.far):not(.fal):not(.fad):not(.fab):not(.fa) {
            font-family: 'Marvel', sans-serif !important;
        }
    </style>
</head>
<body>
    
    <?php include 'includes/navbar_main.php'; ?>

    <div class="container-fluid py-4 px-lg-5">
        
        <div class="events-toolbar">
            <h2 class="fw-bold mb-0">Explore Events</h2>
            
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="eventSearch" placeholder="Search events by title or location...">
            </div>

            <div class="d-flex align-items-center gap-3">
                <?php if (isAdmin()): ?>
                <button class="btn btn-outline-warning rounded-pill px-3" style="color: var(--accent-gold); border-color: var(--accent-gold);" onclick="openCreateModal()">
                    <i class="fas fa-plus me-1"></i> Create Event
                </button>
                <?php endif; ?>

                <div class="view-controls">
                    <button class="btn-view active" id="btnGridView" title="Grid View (G)">
                        <i class="fas fa-th-large"></i>
                    </button>
                    <button class="btn-view" id="btnListView" title="List View (L)">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
                
                <button class="btn btn-outline-secondary rounded-circle" data-bs-toggle="modal" data-bs-target="#quickNavModal" style="width:40px; height:40px; border-color:var(--border-color); color:var(--text-muted);">
                    <i class="fas fa-question"></i>
                </button>
            </div>
        </div>

        <!-- Event Source Tabs -->
        <ul class="nav nav-pills mb-4" id="eventsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" id="batch-tab" data-target="batch" type="button" role="tab" style="color: var(--accent-gold);">Batch Submissions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="manual-tab" data-target="manual" type="button" role="tab" style="color: var(--text-muted);">Manually Added</button>
            </li>
        </ul>

        <div id="eventsContainer" class="events-grid">
            <!-- Events will be injected here via JS -->
            <div class="text-center w-100 py-5">
                <div class="spinner-border text-warning" role="status"></div>
                <p class="mt-2 text-muted">Loading events...</p>
            </div>
        </div>

        <!-- Pagination Controls -->
        <nav aria-label="Events Pagination" class="mt-4" id="paginationNav" style="display: none;">
            <ul class="pagination justify-content-center pagination-dark">
                <li class="page-item" id="prevPageBtn">
                    <button class="page-link" onclick="changePage(currentPage - 1)">Previous</button>
                </li>
                <!-- Page numbers injected via JS -->
                <span id="pageNumbers" class="d-flex"></span>
                <li class="page-item" id="nextPageBtn">
                    <button class="page-link" onclick="changePage(currentPage + 1)">Next</button>
                </li>
            </ul>
        </nav>

    </div>

    <!-- Quick Navigation Modal -->
    <div class="modal fade quick-nav-modal" id="quickNavModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content shadow-lg p-3">
                <div class="modal-body p-0">
                    <h5 class="fw-bold mb-2">Quick navigation</h5>
                    <p class="text-muted small mb-3">Switch views and filter events instantly using keyboard shortcuts.</p>
                    
                    <div class="quick-nav-list">
                        <div class="quick-nav-item" onclick="document.getElementById('btnGridView').click(); $('#quickNavModal').modal('hide');">
                            <span><i class="fas fa-th-large me-2"></i> Grid view</span>
                            <span class="shortcut-key">G</span>
                        </div>
                        <div class="quick-nav-item" onclick="document.getElementById('btnListView').click(); $('#quickNavModal').modal('hide');">
                            <span><i class="fas fa-list me-2"></i> List view</span>
                            <span class="shortcut-key">L</span>
                        </div>
                        <div class="quick-nav-item" onclick="$('#eventSearch').focus(); $('#quickNavModal').modal('hide');">
                            <span><i class="fas fa-search me-2"></i> Search</span>
                            <span class="shortcut-key">S</span>
                        </div>
                    </div>
                    
                    <button class="btn-got-it" data-bs-dismiss="modal">Got it</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Details & Equipment Modal -->
    <div class="modal fade details-modal" id="eventDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="modalEventTitle">Event Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="container-fluid p-0">
                        <div class="row g-0">
                            <!-- Left sidebar with event info -->
                            <div class="col-md-4 p-4 border-end border-dark" style="background: var(--card-dark);">
                                <div id="modalEventImage" class="mb-4 rounded overflow-hidden" style="height: 200px; background: #2a2a2a;">
                                    <!-- Image goes here -->
                                </div>
                                <h3 id="modalEventName" class="fw-bold mb-3 text-dark">Event Name</h3>
                                
                                <div class="d-flex flex-column gap-3 mb-4" style="color: var(--text-light);">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-calendar-alt" style="color: var(--accent-gold); width:20px;"></i>
                                        <span id="modalEventDate">Date</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-map-marker-alt" style="color: var(--accent-gold); width:20px;"></i>
                                        <span id="modalEventLocation">Location</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-user-tie" style="color: var(--accent-gold); width:20px;"></i>
                                        <span id="modalEventManager">Manager</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-tools" style="color: var(--accent-gold); width:20px;"></i>
                                        <span id="modalEventTechnician">Technician</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2" id="modalDriverContainer" style="display:none;">
                                        <i class="fas fa-truck" style="color: var(--accent-gold); width:20px;"></i>
                                        <span id="modalEventDriver">Driver</span>
                                    </div>
                                </div>
                                
                                <h6 class="fw-bold text-dark mb-2">Description</h6>
                                <p id="modalEventDesc" class="text-muted small lh-lg">No description provided.</p>
                            </div>
                            
                            <!-- Right area with equipment table -->
                            <div class="col-md-8 p-4">
                                <h5 class="fw-bold mb-3 text-dark d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-boxes me-2" style="color: var(--accent-gold);"></i> Assigned Equipment</span>
                                    <span class="badge bg-secondary rounded-pill" id="equipmentCountBadge">0 Items</span>
                                </h5>
                                
                                <div class="table-responsive">
                                    <table id="equipmentTable" class="table table-light table-hover w-100 align-middle">
                                        <thead>
                                            <tr>
                                                <th>Item Name</th>
                                                <th>Serial Number</th>
                                                <th>Quantity</th>
                                                <th>Status</th>
                                                <th>Batch #</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Data injected by DataTables -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Form Modal -->
    <div class="modal fade details-modal" id="eventFormModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="eventForm" onsubmit="saveEvent(event)">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark" id="eventFormModalLabel">Create Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 text-dark">
                        <input type="hidden" id="formEventId" name="id">
                        <input type="hidden" id="formMethod" name="_method" value="POST">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Event Title *</label>
                            <input type="text" class="form-control bg-light text-dark border-secondary" id="formTitle" name="title" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label text-muted">Date *</label>
                                <input type="date" class="form-control bg-light text-dark border-secondary" id="formDate" name="date" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted">Duration (Days)</label>
                                <input type="number" class="form-control bg-light text-dark border-secondary" id="formDuration" name="duration" min="1" value="1">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Location</label>
                            <input type="text" class="form-control bg-light text-dark border-secondary" id="formLocation" name="location">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Project Manager</label>
                            <input type="text" class="form-control bg-light text-dark border-secondary" id="formManager" name="manager">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Description</label>
                            <textarea class="form-control bg-light text-dark border-secondary" id="formDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Event Image</label>
                            <input type="file" class="form-control bg-light text-dark border-secondary" id="formImage" name="image" accept="image/*">
                            <small class="text-muted d-block mt-1">Leave blank to keep existing image</small>
                            <div id="currentImagePreview" class="mt-2" style="display:none;">
                                <img id="previewImg" src="" style="height: 100px; border-radius: 8px; object-fit: cover;">
                            </div>
                            <input type="hidden" id="existingImage" name="existingImage">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" id="saveEventBtn" style="background: var(--accent-gold); color: #121212;">Save Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        const isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
        let allEvents = [];
        let filteredData = [];
        let currentView = 'grid'; // 'grid' or 'list'
        let equipmentTable;
        let currentPage = 1;
        const itemsPerPage = 4;
        let currentTabSource = 'batch';

        $(document).ready(function() {
            // Load events initially
            loadEvents();

            // Tab Switching
            $('#eventsTab button').on('click', function (e) {
                e.preventDefault();
                $('#eventsTab button').removeClass('active').css('color', 'var(--text-muted)');
                $(this).addClass('active').css('color', 'var(--accent-gold)');
                currentTabSource = $(this).data('target');
                currentPage = 1;
                filterAndRenderEvents();
            });

            // Setup DataTables
            equipmentTable = $('#equipmentTable').DataTable({
                responsive: true,
                pageLength: 10,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                columns: [
                    { data: 'item_name', render: function(data) { return `<span class="fw-bold text-dark">${data}</span>`; } },
                    { data: 'serial_number', render: function(data) { return data ? `<small class="text-muted">${data}</small>` : '-'; } },
                    { data: 'quantity', className: 'text-center text-dark' },
                    { data: 'item_status', render: function(data) {
                        let color = 'secondary';
                        if(data === 'approved' || data === 'completed' || data === 'delivered') color = 'success';
                        else if(data === 'pending') color = 'warning';
                        else if(data === 'rejected') color = 'danger';
                        return `<span class="badge bg-${color}">${data ? data.toUpperCase() : 'N/A'}</span>`;
                    }},
                    { data: 'batch_number', render: function(data) { return `<small class="text-muted">#${data}</small>`; } }
                ],
                language: {
                    emptyTable: "No equipment assigned to this event yet.",
                    search: "Filter equipment:"
                }
            });

            // View Toggles
            $('#btnGridView').click(function() {
                currentView = 'grid';
                $(this).addClass('active');
                $('#btnListView').removeClass('active');
                renderEvents();
            });

            $('#btnListView').click(function() {
                currentView = 'list';
                $(this).addClass('active');
                $('#btnGridView').removeClass('active');
                renderEvents();
            });

            // Search Filter
            $('#eventSearch').on('input', function() {
                currentPage = 1;
                filterAndRenderEvents();
            });

            // Keyboard Shortcuts
            $(document).keydown(function(e) {
                // Ignore if in an input
                if($(e.target).is('input, textarea')) return;

                if(e.key.toLowerCase() === 'g') {
                    $('#btnGridView').click();
                } else if(e.key.toLowerCase() === 'l') {
                    $('#btnListView').click();
                } else if(e.key.toLowerCase() === 's') {
                    e.preventDefault();
                    $('#eventSearch').focus();
                }
            });
        });

        function loadEvents() {
            $.ajax({
                url: 'api/events/explore_list.php',
                method: 'GET',
                success: function(events) {
                    allEvents = events;
                    filterAndRenderEvents();
                },
                error: function() {
                    $('#eventsContainer').html('<div class="alert alert-danger">Failed to load events. Please try again.</div>');
                }
            });
        }

        function filterAndRenderEvents() {
            const term = $('#eventSearch').val().toLowerCase();
            filteredData = allEvents.filter(ev => {
                const matchesTab = ev.source === currentTabSource;
                const matchesSearch = (ev.title && ev.title.toLowerCase().includes(term)) || 
                                      (ev.location && ev.location.toLowerCase().includes(term));
                return matchesTab && matchesSearch;
            });
            renderEvents();
        }

        function renderEvents() {
            const container = $('#eventsContainer');
            container.empty();

            // Set container class based on view
            container.removeClass('events-grid events-list');
            container.addClass(currentView === 'grid' ? 'events-grid' : 'events-list');

            if (filteredData.length === 0) {
                $('#paginationNav').hide();
                container.html(`
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="fas fa-calendar-times fs-1 mb-3"></i>
                        <h5>No events found</h5>
                        <p>Try adjusting your search criteria</p>
                    </div>
                `);
                return;
            }

            // Pagination Logic
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            if (currentPage < 1) currentPage = 1;
            if (currentPage > totalPages) currentPage = totalPages;

            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pagedEvents = filteredData.slice(startIndex, endIndex);

            pagedEvents.forEach(ev => {
                const dateStr = ev.date ? new Date(ev.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'TBD';
                
                let imageHtml = `<div class="event-img-placeholder"><i class="fas fa-calendar-alt"></i></div>`;
                if (ev.event_image) {
                    imageHtml = `<img src="${ev.event_image}" class="event-img" alt="${ev.title}" onerror="this.outerHTML='<div class=\\'event-img-placeholder\\'><i class=\\'fas fa-calendar-alt\\'></i></div>'">`;
                }

                let adminActions = '';
                if(isAdmin) {
                    adminActions = `
                        <div class="d-flex gap-2 w-100 mt-2">
                            <button class="btn btn-sm btn-outline-secondary w-50" onclick='openEditModal(${JSON.stringify(ev).replace(/'/g, "&#39;")})'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger w-50" onclick='deleteEvent(${ev.event_id ? JSON.stringify(ev.event_id).replace(/'/g, "&#39;") : "null"}, ${JSON.stringify(ev.title || "").replace(/'/g, "&#39;")})'>
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    `;
                }

                const cardHtml = `
                    <div class="event-card">
                        <div class="event-img-wrapper">
                            ${imageHtml}
                            <div class="event-status-badge">Event</div>
                        </div>
                        <div class="event-content">
                            <h3 class="event-title">${escapeHtml(ev.title)}</h3>
                            <div class="event-meta">
                                <i class="fas fa-calendar-days"></i>
                                <span>${dateStr}</span>
                            </div>
                            <div class="event-meta">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>${escapeHtml(ev.location || 'Location TBA')}</span>
                            </div>
                            <div class="event-meta">
                                <i class="fas fa-user-tie"></i>
                                <span>${escapeHtml(ev.project_manager || 'Not Assigned')}</span>
                            </div>
                            <div class="event-meta">
                                <i class="fas fa-tools"></i>
                                <span>${escapeHtml(ev.technician || 'Tech Not Specified')}</span>
                            </div>
                            <div class="event-meta">
                                <i class="fas fa-truck"></i>
                                <span>${escapeHtml((ev.movement_type && ev.movement_type.toLowerCase() === 'stock to stock' && ev.driver) ? ev.driver : 'Not Applicable')}</span>
                            </div>
                            
                            <div class="event-actions d-flex flex-column gap-2 mt-auto pt-3">
                                <button class="btn-view-details" onclick='openEventDetails(${JSON.stringify(ev).replace(/'/g, "&#39;")})'>
                                    View Details
                                </button>
                                ${adminActions}
                            </div>
                        </div>
                    </div>
                `;
                container.append(cardHtml);
            });

            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            if(totalPages <= 1) {
                $('#paginationNav').hide();
                return;
            }
            $('#paginationNav').show();
            $('#prevPageBtn').toggleClass('disabled', currentPage === 1);
            $('#nextPageBtn').toggleClass('disabled', currentPage === totalPages);

            let pagesHtml = '';
            for(let i=1; i<=totalPages; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                pagesHtml += `<li class="page-item ${activeClass}"><button class="page-link" onclick="changePage(${i})">${i}</button></li>`;
            }
            $('#pageNumbers').html(pagesHtml);
        }

        function changePage(page) {
            currentPage = page;
            renderEvents();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function openEventDetails(eventObj) {
            // Populate Modal Info
            $('#modalEventName').text(eventObj.title || 'Unknown Event');
            $('#modalEventDate').text(eventObj.date ? new Date(eventObj.date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'TBD');
            $('#modalEventLocation').text(eventObj.location || 'Location TBA');
            $('#modalEventManager').text(eventObj.project_manager || 'Not Assigned');
            $('#modalEventTechnician').text(eventObj.technician || 'Not Specified');
            
            if (eventObj.movement_type && eventObj.movement_type.toLowerCase() === 'stock to stock' && eventObj.driver) {
                $('#modalEventDriver').text(eventObj.driver);
            } else {
                $('#modalEventDriver').text('Not Applicable');
            }
            $('#modalDriverContainer').show();
            
            $('#modalEventDesc').text(eventObj.description || 'No description provided.');
            
            if (eventObj.event_image) {
                $('#modalEventImage').html(`<img src="${eventObj.event_image}" style="width:100%; height:100%; object-fit:cover;" onerror="this.style.display='none'">`);
            } else {
                $('#modalEventImage').html(`<div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted fs-1"><i class="fas fa-image"></i></div>`);
            }

            // Fetch Equipment for this event
            equipmentTable.clear().draw();
            $('#equipmentCountBadge').text('Loading...');
            $('#equipmentTable_processing').show(); // Optional if you want to show built-in loader

            $.ajax({
                url: 'api/events/equipment.php',
                method: 'GET',
                data: { event_title: eventObj.title },
                success: function(res) {
                    if(res.success && res.data) {
                        equipmentTable.rows.add(res.data).draw();
                        $('#equipmentCountBadge').text(`${res.total_quantity} Items`);
                    } else {
                        $('#equipmentCountBadge').text('0 Items');
                    }
                },
                error: function() {
                    $('#equipmentCountBadge').text('Error fetching data');
                }
            });

            $('#eventDetailsModal').modal('show');
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // CRUD Operations
        function openCreateModal() {
            $('#eventForm')[0].reset();
            $('#formEventId').val('');
            $('#formMethod').val('POST');
            $('#eventFormModalLabel').text('Create Event');
            $('#saveEventBtn').text('Save Event');
            $('#currentImagePreview').hide();
            $('#existingImage').val('');
            $('#eventFormModal').modal('show');
        }

        function openEditModal(ev) {
            $('#eventForm')[0].reset();
            $('#formEventId').val(ev.event_id || '');
            $('#formMethod').val('PUT'); // Use PUT for edits
            $('#eventFormModalLabel').text('Edit Event');
            $('#saveEventBtn').text('Update Event');
            
            $('#formTitle').val(ev.title || '');
            if(ev.date) {
                // convert to YYYY-MM-DD
                const d = new Date(ev.date);
                if(!isNaN(d)) $('#formDate').val(d.toISOString().split('T')[0]);
            }
            $('#formLocation').val(ev.location || '');
            $('#formManager').val(ev.project_manager || '');
            $('#formDescription').val(ev.description || '');

            if(ev.event_image) {
                $('#previewImg').attr('src', ev.event_image);
                $('#currentImagePreview').show();
                $('#existingImage').val(ev.event_image);
            } else {
                $('#currentImagePreview').hide();
                $('#existingImage').val('');
            }

            $('#eventFormModal').modal('show');
        }

        function saveEvent(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('eventForm'));
            
            // Check if it's an update to a stock_movement event that doesn't have an ID yet
            if ($('#formMethod').val() === 'PUT' && !$('#formEventId').val()) {
                // If no ID exists in the events table yet, we must POST to create it.
                formData.set('_method', 'POST');
            }

            $.ajax({
                url: 'api/events.php',
                method: 'POST', // Always POST due to FormData (override inside)
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if(res.success) {
                        $('#eventFormModal').modal('hide');
                        loadEvents();
                    } else {
                        alert(res.message || 'Error saving event');
                    }
                },
                error: function() {
                    alert('Server error occurred.');
                }
            });
        }

        function deleteEvent(eventId, title) {
            if(!eventId) {
                alert('Cannot delete this event directly because it was auto-generated from stock movements. To remove it, delete the associated stock movements.');
                return;
            }

            if(confirm(`Are you sure you want to delete "${title}"?`)) {
                $.ajax({
                    url: `api/events.php?id=${eventId}`,
                    method: 'DELETE',
                    success: function(res) {
                        if(res.success) {
                            loadEvents();
                        } else {
                            alert(res.message || 'Error deleting event');
                        }
                    },
                    error: function() {
                        alert('Server error occurred.');
                    }
                });
            }
        }
    </script>
</body>
</html>
