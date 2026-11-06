<?php
/**
 * includes/badge_attendance_helper.php
 * Shared helper for User ID Badges, Stock & Venue Access Levels, and Attendance Management
 */

if (!function_exists('getClearanceLevelsMeta')) {
    function getClearanceLevelsMeta()
    {
        return [
            'L4_ALL_ACCESS' => [
                'code' => 'L4',
                'title' => 'Level 4 — Full All-Access',
                'short' => 'All-Access (Stock & Venues)',
                'color' => '#dc2626',
                'bg' => '#fef2f2',
                'border' => '#fecaca',
                'badge_bg' => 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)',
                'icon' => 'fa-shield-halved',
                'description' => 'Unrestricted 24/7 entry to all Stock Warehouses, Production Vaults, Backstage Technical Zones, and Event Venues.'
            ],
            'L3_STOCK_WAREHOUSE' => [
                'code' => 'L3',
                'title' => 'Level 3 — Stock & Warehouse',
                'short' => 'Stock & Assigned Venues',
                'color' => '#059669',
                'bg' => '#ecfdf5',
                'border' => '#a7f3d0',
                'badge_bg' => 'linear-gradient(135deg, #059669 0%, #065f46 100%)',
                'icon' => 'fa-warehouse',
                'description' => 'Full access to authorized Stock Warehouses, Loading Bays, and Assigned Event Venues.'
            ],
            'L2_TECH_STAGE' => [
                'code' => 'L2',
                'title' => 'Level 2 — Technical & Stage',
                'short' => 'Tech Stage & Dispatch Stock',
                'color' => '#4f46e5',
                'bg' => '#eef2ff',
                'border' => '#c7d2fe',
                'badge_bg' => 'linear-gradient(135deg, #4f46e5 0%, #3730a3 100%)',
                'icon' => 'fa-sliders',
                'description' => 'Access to Technical Stage areas, FOH/Backstage at assigned Event Venues, and designated Dispatch Stock Locations.'
            ],
            'L1_VENUE_BASIC' => [
                'code' => 'L1',
                'title' => 'Level 1 — Basic Venue Crew',
                'short' => 'Assigned Venues Only',
                'color' => '#0284c7',
                'bg' => '#f0f9ff',
                'border' => '#bae6fd',
                'badge_bg' => 'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)',
                'icon' => 'fa-location-dot',
                'description' => 'Standard crew access restricted strictly to assigned Event Venues during scheduled calltimes.'
            ]
        ];
    }
}

if (!function_exists('getRoleAccentMeta')) {
    function getRoleAccentMeta($role)
    {
        $role = strtolower(trim((string)$role));
        $map = [
            'admin' => ['label' => 'ADMINISTRATOR', 'color' => '#dc2626', 'header' => 'linear-gradient(135deg, #1e293b 0%, #7f1d1d 100%)'],
            'manager' => ['label' => 'OPERATIONS MANAGER', 'color' => '#ea580c', 'header' => 'linear-gradient(135deg, #1e293b 0%, #7c2d12 100%)'],
            'stock_manager' => ['label' => 'STOCK MANAGER', 'color' => '#059669', 'header' => 'linear-gradient(135deg, #1e293b 0%, #064e3b 100%)'],
            'stock_controller' => ['label' => 'STOCK CONTROLLER', 'color' => '#0d9488', 'header' => 'linear-gradient(135deg, #1e293b 0%, #134e4a 100%)'],
            'tech_lead' => ['label' => 'TECHNICAL LEAD', 'color' => '#6366f1', 'header' => 'linear-gradient(135deg, #1e293b 0%, #312e81 100%)'],
            'technician' => ['label' => 'AV TECHNICIAN', 'color' => '#2563eb', 'header' => 'linear-gradient(135deg, #1e293b 0%, #1e3a8a 100%)'],
            'driver' => ['label' => 'LOGISTICS DRIVER', 'color' => '#0891b2', 'header' => 'linear-gradient(135deg, #1e293b 0%, #164e63 100%)'],
            'user' => ['label' => 'PRODUCTION STAFF', 'color' => '#475569', 'header' => 'linear-gradient(135deg, #1e293b 0%, #334155 100%)'],
        ];
        return $map[$role] ?? [
            'label' => strtoupper(str_replace('_', ' ', $role ?: 'STAFF')),
            'color' => '#234c6a',
            'header' => 'linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%)'
        ];
    }
}

if (!function_exists('getDefaultBadgeSpecForRole')) {
    function getDefaultBadgeSpecForRole($role, $allStockIds = [], $allEventIds = [])
    {
        $role = strtolower(trim((string)$role));
        $firstStockIds = array_slice($allStockIds, 0, 2);

        switch ($role) {
            case 'admin':
            case 'manager':
                return [
                    'clearance_level' => 'L4_ALL_ACCESS',
                    'stock_access_mode' => 'all',
                    'allowed_stock_ids' => json_encode($allStockIds),
                    'venue_access_mode' => 'all',
                    'allowed_event_ids' => json_encode($allEventIds)
                ];
            case 'stock_manager':
            case 'stock_controller':
                return [
                    'clearance_level' => 'L3_STOCK_WAREHOUSE',
                    'stock_access_mode' => 'all',
                    'allowed_stock_ids' => json_encode($allStockIds),
                    'venue_access_mode' => 'assigned_only',
                    'allowed_event_ids' => json_encode(array_slice($allEventIds, 0, 2))
                ];
            case 'tech_lead':
                return [
                    'clearance_level' => 'L3_STOCK_WAREHOUSE',
                    'stock_access_mode' => 'selected',
                    'allowed_stock_ids' => json_encode($firstStockIds),
                    'venue_access_mode' => 'all',
                    'allowed_event_ids' => json_encode($allEventIds)
                ];
            case 'technician':
            case 'driver':
                return [
                    'clearance_level' => 'L2_TECH_STAGE',
                    'stock_access_mode' => 'selected',
                    'allowed_stock_ids' => json_encode(array_slice($allStockIds, 0, 1)),
                    'venue_access_mode' => 'assigned_only',
                    'allowed_event_ids' => json_encode(array_slice($allEventIds, 0, 2))
                ];
            default:
                return [
                    'clearance_level' => 'L1_VENUE_BASIC',
                    'stock_access_mode' => 'none',
                    'allowed_stock_ids' => json_encode([]),
                    'venue_access_mode' => 'assigned_only',
                    'allowed_event_ids' => json_encode(array_slice($allEventIds, 0, 1))
                ];
        }
    }
}

if (!function_exists('ensureBadgesAndAttendanceTables')) {
    function ensureBadgesAndAttendanceTables($conn)
    {
        if (!$conn) {
            return;
        }

        // 1. Create user_badges table
        $conn->query("
            CREATE TABLE IF NOT EXISTS user_badges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                badge_code VARCHAR(32) NOT NULL UNIQUE,
                rfid_uid VARCHAR(64) DEFAULT NULL,
                clearance_level ENUM('L1_VENUE_BASIC', 'L2_TECH_STAGE', 'L3_STOCK_WAREHOUSE', 'L4_ALL_ACCESS') NOT NULL DEFAULT 'L1_VENUE_BASIC',
                stock_access_mode ENUM('none', 'selected', 'all') NOT NULL DEFAULT 'none',
                allowed_stock_ids TEXT DEFAULT NULL,
                venue_access_mode ENUM('none', 'assigned_only', 'selected', 'all') NOT NULL DEFAULT 'assigned_only',
                allowed_event_ids TEXT DEFAULT NULL,
                badge_status ENUM('active', 'suspended', 'revoked', 'expired') NOT NULL DEFAULT 'active',
                emergency_contact VARCHAR(100) DEFAULT '+250 788 000 112',
                blood_group VARCHAR(10) DEFAULT 'O+',
                issued_at DATE NOT NULL,
                expires_at DATE NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 2. Create attendance_logs table
        $conn->query("
            CREATE TABLE IF NOT EXISTS attendance_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                badge_code VARCHAR(32) NOT NULL,
                checkpoint_type ENUM('stock_warehouse', 'event_venue', 'main_office') NOT NULL DEFAULT 'stock_warehouse',
                checkpoint_id INT DEFAULT 0,
                checkpoint_name VARCHAR(150) NOT NULL,
                scan_action ENUM('check_in', 'check_out', 'access_verify') NOT NULL DEFAULT 'check_in',
                access_status ENUM('granted', 'denied') NOT NULL DEFAULT 'granted',
                denial_reason VARCHAR(255) DEFAULT NULL,
                punctuality_status ENUM('on_time', 'late', 'early', 'n/a') NOT NULL DEFAULT 'n/a',
                scanned_by INT DEFAULT NULL,
                notes VARCHAR(255) DEFAULT NULL,
                scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_scanned (user_id, scanned_at),
                INDEX idx_checkpoint (checkpoint_type, checkpoint_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Gather existing stock_locations IDs and events IDs for smart seeding
        $allStockIds = [];
        $stockRes = @$conn->query("SELECT id, name FROM stock_locations WHERE is_active = 1 ORDER BY id ASC");
        $stockList = [];
        if ($stockRes) {
            while ($r = $stockRes->fetch_assoc()) {
                $allStockIds[] = (int)$r['id'];
                $stockList[] = $r;
            }
        }

        $allEventIds = [];
        $eventList = [];
        $evRes = @$conn->query("SELECT id, title, location FROM events ORDER BY id DESC LIMIT 20");
        if ($evRes) {
            while ($r = $evRes->fetch_assoc()) {
                $allEventIds[] = (int)$r['id'];
                $eventList[] = $r;
            }
        }

        // 3. Seed user_badges for any user missing a badge
        $usersRes = @$conn->query("SELECT id, username, full_name, role, is_active FROM users ORDER BY id ASC");
        $bloodGroups = ['O+', 'A+', 'B+', 'O-', 'A-', 'AB+'];
        $seededUsers = [];

        if ($usersRes) {
            $insStmt = $conn->prepare("
                INSERT IGNORE INTO user_badges 
                (user_id, badge_code, rfid_uid, clearance_level, stock_access_mode, allowed_stock_ids, venue_access_mode, allowed_event_ids, badge_status, emergency_contact, blood_group, issued_at, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            while ($u = $usersRes->fetch_assoc()) {
                $uid = (int)$u['id'];
                $seededUsers[] = $u;
                $badgeCode = 'ABL-BDG-' . str_pad((string)$uid, 4, '0', STR_PAD_LEFT);
                $rfidUid = 'RFID-88' . str_pad((string)$uid, 2, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5('ability_badge_' . $uid), 0, 4));
                $spec = getDefaultBadgeSpecForRole($u['role'], $allStockIds, $allEventIds);
                $status = ((int)$u['is_active'] === 1) ? 'active' : 'suspended';
                $emContact = '+250 788 ' . str_pad((string)(100 + $uid), 3, '0', STR_PAD_LEFT) . ' ' . str_pad((string)(200 + $uid * 7), 3, '0', STR_PAD_LEFT);
                $bg = $bloodGroups[$uid % count($bloodGroups)];
                $issuedAt = date('Y-01-01');
                $expiresAt = date('Y-12-31', strtotime('+1 year'));

                if ($insStmt) {
                    $insStmt->bind_param(
                        "issssssssssss",
                        $uid,
                        $badgeCode,
                        $rfidUid,
                        $spec['clearance_level'],
                        $spec['stock_access_mode'],
                        $spec['allowed_stock_ids'],
                        $spec['venue_access_mode'],
                        $spec['allowed_event_ids'],
                        $status,
                        $emContact,
                        $bg,
                        $issuedAt,
                        $expiresAt
                    );
                    @$insStmt->execute();
                }
            }
            if ($insStmt) {
                $insStmt->close();
            }
        }

        // 4. Seed initial sample attendance_logs if table is completely empty
        $cntRes = @$conn->query("SELECT COUNT(*) as cnt FROM attendance_logs");
        $logCount = ($cntRes && ($r = $cntRes->fetch_assoc())) ? (int)$r['cnt'] : 0;

        if ($logCount === 0 && !empty($seededUsers)) {
            $defaultStockId = !empty($stockList) ? (int)$stockList[0]['id'] : 1;
            $defaultStockName = !empty($stockList) ? $stockList[0]['name'] : 'Main Central Warehouse';

            $defaultEventId = !empty($eventList) ? (int)$eventList[0]['id'] : 1;
            $defaultEventName = !empty($eventList)
                ? ($eventList[0]['title'] . (!empty($eventList[0]['location']) ? ' — ' . $eventList[0]['location'] : ''))
                : 'BK Arena — Main Production Stage';

            $secondEventId = isset($eventList[1]) ? (int)$eventList[1]['id'] : $defaultEventId;
            $secondEventName = isset($eventList[1])
                ? ($eventList[1]['title'] . (!empty($eventList[1]['location']) ? ' — ' . $eventList[1]['location'] : ''))
                : 'Kigali Convention Centre — Auditorium';

            $sampleStmt = $conn->prepare("
                INSERT INTO attendance_logs 
                (user_id, badge_code, checkpoint_type, checkpoint_id, checkpoint_name, scan_action, access_status, denial_reason, punctuality_status, scanned_by, notes, scanned_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ($sampleStmt) {
                $operatorId = (int)$seededUsers[0]['id'];
                foreach (array_slice($seededUsers, 0, 5) as $idx => $su) {
                    $suId = (int)$su['id'];
                    $bCode = 'ABL-BDG-' . str_pad((string)$suId, 4, '0', STR_PAD_LEFT);
                    $isStock = ($idx % 2 === 0);
                    $cpType = $isStock ? 'stock_warehouse' : 'event_venue';
                    $cpId = $isStock ? $defaultStockId : ($idx === 1 ? $defaultEventId : $secondEventId);
                    $cpName = $isStock ? $defaultStockName : ($idx === 1 ? $defaultEventName : $secondEventName);
                    $action = 'check_in';
                    $status = 'granted';
                    $denial = null;
                    $punctuality = ($idx === 2) ? 'late' : 'on_time';
                    $notes = $isStock ? 'Badge verified at warehouse dispatch gate' : 'Crew calltime badge check-in';
                    $scanTime = date('Y-m-d H:i:s', strtotime('-' . (45 - $idx * 8) . ' minutes'));

                    $sampleStmt->bind_param(
                        "ississsssiss",
                        $suId,
                        $bCode,
                        $cpType,
                        $cpId,
                        $cpName,
                        $action,
                        $status,
                        $denial,
                        $punctuality,
                        $operatorId,
                        $notes,
                        $scanTime
                    );
                    @$sampleStmt->execute();
                }
                $sampleStmt->close();
            }
        }
    }
}

if (!function_exists('evaluateUserCheckpointAccess')) {
    /**
     * Evaluates whether a user's badge grants access to the selected Stock Warehouse or Event Venue.
     */
    function evaluateUserCheckpointAccess($conn, $user, $badge, $checkpointType, $checkpointId, $stockMap = [], $eventMap = [])
    {
        if (!$user || !$badge) {
            return [
                'granted' => false,
                'reason' => 'Invalid or unrecognized ID Badge code.',
                'punctuality' => 'n/a'
            ];
        }

        if ((int)($user['is_active'] ?? 1) !== 1) {
            return [
                'granted' => false,
                'reason' => 'User account is currently suspended or inactive.',
                'punctuality' => 'n/a'
            ];
        }

        $badgeStatus = strtolower($badge['badge_status'] ?? 'active');
        if ($badgeStatus !== 'active') {
            return [
                'granted' => false,
                'reason' => 'ID Badge status is ' . strtoupper($badgeStatus) . '. Access blocked.',
                'punctuality' => 'n/a'
            ];
        }

        if (!empty($badge['expires_at']) && strtotime($badge['expires_at']) < strtotime(date('Y-m-d'))) {
            return [
                'granted' => false,
                'reason' => 'ID Badge expired on ' . date('M d, Y', strtotime($badge['expires_at'])) . '.',
                'punctuality' => 'n/a'
            ];
        }

        $clearance = $badge['clearance_level'] ?? 'L1_VENUE_BASIC';
        $checkpointId = (int)$checkpointId;

        // 1. Stock Warehouse Evaluation
        if ($checkpointType === 'stock_warehouse') {
            $stockMode = $badge['stock_access_mode'] ?? 'none';
            $allowedStock = json_decode($badge['allowed_stock_ids'] ?? '[]', true);
            if (!is_array($allowedStock)) {
                $allowedStock = [];
            }
            $allowedStock = array_map('intval', $allowedStock);

            $hasAccess = false;
            if ($clearance === 'L4_ALL_ACCESS' || $stockMode === 'all') {
                $hasAccess = true;
            } elseif ($stockMode === 'selected' && in_array($checkpointId, $allowedStock, true)) {
                $hasAccess = true;
            }

            if (!$hasAccess) {
                $locName = $stockMap[$checkpointId]['name'] ?? 'this Stock Warehouse';
                return [
                    'granted' => false,
                    'reason' => "Clearance ({$clearance}) does not authorize entry to {$locName}.",
                    'punctuality' => 'n/a'
                ];
            }

            return [
                'granted' => true,
                'reason' => 'Stock Warehouse clearance verified.',
                'punctuality' => 'on_time'
            ];
        }

        // 2. Event Venue Evaluation
        if ($checkpointType === 'event_venue') {
            $venueMode = $badge['venue_access_mode'] ?? 'assigned_only';
            $allowedEvents = json_decode($badge['allowed_event_ids'] ?? '[]', true);
            if (!is_array($allowedEvents)) {
                $allowedEvents = [];
            }
            $allowedEvents = array_map('intval', $allowedEvents);

            $hasAccess = false;
            if ($clearance === 'L4_ALL_ACCESS' || $venueMode === 'all') {
                $hasAccess = true;
            } elseif (in_array($checkpointId, $allowedEvents, true)) {
                $hasAccess = true;
            } elseif ($venueMode === 'assigned_only') {
                // Check if user is assigned to the event or if allowedEvents is empty (fallback allow for active event crew)
                $ev = $eventMap[$checkpointId] ?? null;
                if ($ev) {
                    $uName = strtolower(trim($user['username'] ?? ''));
                    $fName = strtolower(trim($user['full_name'] ?? ''));
                    $haystack = strtolower(($ev['project_manager'] ?? '') . ' ' . ($ev['tech_lead'] ?? '') . ' ' . ($ev['assigned_technician'] ?? ''));
                    if (($uName && strpos($haystack, $uName) !== false) || ($fName && strpos($haystack, $fName) !== false)) {
                        $hasAccess = true;
                    }
                }
                if (!$hasAccess && in_array($clearance, ['L2_TECH_STAGE', 'L3_STOCK_WAREHOUSE'], true) && empty($allowedEvents)) {
                    $hasAccess = true;
                }
            }

            if (!$hasAccess) {
                $evName = $eventMap[$checkpointId]['title'] ?? 'this Event Venue';
                return [
                    'granted' => false,
                    'reason' => "User is not on the authorized crew list or badge clearance for {$evName}.",
                    'punctuality' => 'n/a'
                ];
            }

            // Evaluate calltime punctuality if call_time is set
            $punctuality = 'on_time';
            $ev = $eventMap[$checkpointId] ?? null;
            if ($ev && !empty($ev['call_time'])) {
                $callTs = strtotime(date('Y-m-d') . ' ' . $ev['call_time']);
                if ($callTs !== false) {
                    if (time() > ($callTs + 900)) { // > 15 mins after calltime
                        $punctuality = 'late';
                    } elseif (time() < ($callTs - 1800)) {
                        $punctuality = 'early';
                    }
                }
            }

            return [
                'granted' => true,
                'reason' => 'Event Venue crew clearance verified.',
                'punctuality' => $punctuality
            ];
        }

        // 3. Main Office / General Checkpoint
        return [
            'granted' => true,
            'reason' => 'Main Office staff badge verified.',
            'punctuality' => 'on_time'
        ];
    }
}
