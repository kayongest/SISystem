<?php
// api/events.php - Fixed version with proper field mapping
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off display errors for JSON responses

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    // Get the HTTP method
    $method = $_SERVER['REQUEST_METHOD'];

    // Handle method override for PUT requests via POST
    if ($method === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'PUT') {
        $method = 'PUT';
    }

    // Use getConnection() function from database.php
    $db = getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Image upload directory
    define('UPLOAD_DIR', '../uploads/events/');
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }

    switch ($method) {
        case 'GET':
            // Read events
            $result = $db->query("SELECT * FROM events ORDER BY 
                                  CASE WHEN date IS NULL THEN 1 ELSE 0 END, 
                                  date ASC, 
                                  created_at DESC");

            if (!$result) {
                throw new Exception('Query failed: ' . $db->error);
            }

            $events = [];
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            echo json_encode($events);
            break;

        case 'POST':
            // Create event with image upload
            try {
                error_log("POST data: " . print_r($_POST, true));
                error_log("FILES data: " . print_r($_FILES, true));

                // Validate required fields
                if (empty($_POST['id'])) {
                    throw new Exception('Event ID is required');
                }
                if (empty($_POST['title'])) {
                    throw new Exception('Event title is required');
                }

                // Handle image upload
                $imagePath = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $imagePath = handleImageUpload($_FILES['image']);
                }

                // Prepare statement - using project_manager column
                $stmt = $db->prepare("
                    INSERT INTO events (id, title, date, duration, location, event_image, project_manager, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$stmt) {
                    throw new Exception('Prepare failed: ' . $db->error);
                }

                // Convert empty strings to null
                $date = !empty($_POST['date']) ? $_POST['date'] : null;
                $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
                $location = !empty($_POST['location']) ? $_POST['location'] : null;
                // Map 'manager' from form to 'project_manager' in database
                $manager = !empty($_POST['manager']) ? $_POST['manager'] : null;
                $description = !empty($_POST['description']) ? $_POST['description'] : null;

                error_log("Binding: id={$_POST['id']}, title={$_POST['title']}, date=$date, duration=$duration, location=$location, manager=$manager");

                $stmt->bind_param(
                    "sssissss",
                    $_POST['id'],
                    $_POST['title'],
                    $date,
                    $duration,
                    $location,
                    $imagePath,
                    $manager,  // This goes to project_manager column
                    $description
                );

                if (!$stmt->execute()) {
                    throw new Exception('Execute failed: ' . $stmt->error);
                }

                echo json_encode(['success' => true, 'id' => $_POST['id']]);
            } catch (Exception $e) {
                error_log("POST Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'PUT':
            // Update event
            try {
                // Get PUT data - handle both form data and JSON
                $putData = [];

                if (!empty($_POST)) {
                    $putData = $_POST;
                } else {
                    $input = file_get_contents('php://input');
                    parse_str($input, $putData);
                }

                error_log("PUT data received: " . print_r($putData, true));

                $id = $putData['id'] ?? null;
                if (!$id) throw new Exception('ID required');

                // Handle image upload if present
                $imagePath = $putData['existingImage'] ?? null;

                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    // Delete old image if exists
                    if ($imagePath) {
                        $oldPath = '..' . $imagePath;
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                    $imagePath = handleImageUpload($_FILES['image']);
                }

                // Prepare the update statement with ALL fields
                $stmt = $db->prepare("
                    UPDATE events 
                    SET title = ?, 
                        date = ?, 
                        duration = ?, 
                        location = ?, 
                        event_image = ?, 
                        project_manager = ?, 
                        description = ? 
                    WHERE id = ?
                ");

                if (!$stmt) {
                    throw new Exception('Prepare failed: ' . $db->error);
                }

                // Get values from PUT data
                $title = $putData['title'] ?? '';
                $date = !empty($putData['date']) ? $putData['date'] : null;
                $duration = !empty($putData['duration']) ? (int)$putData['duration'] : null;
                $location = !empty($putData['location']) ? $putData['location'] : null;
                // Map 'manager' from form to 'project_manager' in database
                $manager = !empty($putData['manager']) ? $putData['manager'] : null;
                $description = !empty($putData['description']) ? $putData['description'] : null;

                error_log("Binding values - title: $title, date: $date, duration: $duration, location: $location, manager: $manager, id: $id");

                $stmt->bind_param(
                    "ssisssss",
                    $title,
                    $date,
                    $duration,
                    $location,
                    $imagePath,
                    $manager,  // This goes to project_manager column
                    $description,
                    $id
                );

                if (!$stmt->execute()) {
                    throw new Exception('Execute failed: ' . $stmt->error);
                }

                error_log("Successfully updated " . $stmt->affected_rows . " row(s)");
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                error_log("PUT Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'DELETE':
            // Delete event and associated image
            try {
                $id = $_GET['id'] ?? null;
                if (!$id) throw new Exception('ID required');

                // Get image path before deleting
                $stmt = $db->prepare("SELECT event_image FROM events WHERE id = ?");
                $stmt->bind_param("s", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $event = $result->fetch_assoc();

                if ($event && $event['event_image']) {
                    $imagePath = '..' . $event['event_image'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }

                $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
                $stmt->bind_param("s", $id);

                if (!$stmt->execute()) {
                    throw new Exception('Delete failed: ' . $stmt->error);
                }

                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                error_log("DELETE Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Method not supported']);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// In handleImageUpload function, return a web-accessible path
function handleImageUpload($file)
{
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed: JPEG, PNG, GIF, WEBP');
    }

    // Validate file size (5MB max)
    // Change this line in handleImageUpload function
    if ($file['size'] > 10 * 1024 * 1024) { // Changed from 5MB to 10MB
        throw new Exception('File too large. Maximum size is 10MB');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/ability_app_main/uploads/events/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Return the web-accessible path
        return '/ability_app_main/uploads/events/' . $filename;
    }

    throw new Exception('Failed to upload image. Error: ' . error_get_last()['message']);
}
