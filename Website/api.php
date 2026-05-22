<?php
/**
 * ParkNova Comprehensive API Endpoint
 * Demonstrates full integration with all 13 database tables for faculty review.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$host = "localhost";
$db_name = "parknova_db";
$username = "root";
$password = ""; // Default XAMPP password

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["error" => "Database Connection error: " . $exception->getMessage()]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

try {
    switch($action) {
        case 'get_all_data':
            // 1. Fetch from Independent Tables
            $users = $conn->query("SELECT * FROM User")->fetchAll(PDO::FETCH_ASSOC);
            $categories = $conn->query("SELECT * FROM Vehicle_Category")->fetchAll(PDO::FETCH_ASSOC);
            $areas = $conn->query("SELECT * FROM Parking_Area")->fetchAll(PDO::FETCH_ASSOC);

            // 2. Fetch from 1st Level Dependencies
            $slots = $conn->query("SELECT * FROM Slot")->fetchAll(PDO::FETCH_ASSOC);
            $vehicles = $conn->query("SELECT * FROM Vehicles")->fetchAll(PDO::FETCH_ASSOC);
            $slot_catalogs = $conn->query("SELECT * FROM Slot_Catalog")->fetchAll(PDO::FETCH_ASSOC);

            // 3. Fetch from 2nd Level Dependencies
            $sessions = $conn->query("SELECT * FROM Session")->fetchAll(PDO::FETCH_ASSOC);
            $session_slots = $conn->query("SELECT * FROM Session_Slot")->fetchAll(PDO::FETCH_ASSOC);

            // 4. Fetch from 3rd Level Dependencies (Logs, Billing, Auditing)
            $payments = $conn->query("SELECT * FROM Payment")->fetchAll(PDO::FETCH_ASSOC);
            $violations = $conn->query("SELECT * FROM Violations")->fetchAll(PDO::FETCH_ASSOC);
            $blacklists = $conn->query("SELECT * FROM Blacklist")->fetchAll(PDO::FETCH_ASSOC);
            $reviews = $conn->query("SELECT * FROM Review")->fetchAll(PDO::FETCH_ASSOC);
            $logs = $conn->query("SELECT * FROM SYS_ACT_Log")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "message" => "Successfully fetched data from all 13 tables.",
                "data" => [
                    "User" => $users,
                    "Vehicle_Category" => $categories,
                    "Parking_Area" => $areas,
                    "Slot" => $slots,
                    "Vehicles" => $vehicles,
                    "Slot_Catalog" => $slot_catalogs,
                    "Session" => $sessions,
                    "Session_Slot" => $session_slots,
                    "Payment" => $payments,
                    "Violations" => $violations,
                    "Blacklist" => $blacklists,
                    "Review" => $reviews,
                    "SYS_ACT_Log" => $logs
                ]
            ]);
            break;

        case 'simulate_parking_flow':
            // This endpoint simulates a complete vehicle lifecycle, touching EVERY single table
            $conn->beginTransaction();

            // --- INDEPENDENT TABLES ---
            // 1. User
            $conn->exec("INSERT IGNORE INTO User (password, username, user_type, activity_frequency) VALUES ('hashed_pwd', 'demo_user', 'Customer', 1.5)");
            $userId = $conn->lastInsertId() ?: 1; // Fallback if already exists

            // 2. Vehicle_Category
            $conn->exec("INSERT IGNORE INTO Vehicle_Category (cat_type) VALUES ('SUV')");
            $catId = $conn->lastInsertId() ?: 1;

            // 3. Parking_Area
            $conn->exec("INSERT IGNORE INTO Parking_Area (zone_name, capacity) VALUES ('Premium Zone A', 100)");
            $zoneId = $conn->lastInsertId() ?: 1;

            // --- 1ST LEVEL DEPENDENCIES ---
            // 4. Slot
            $conn->exec("INSERT IGNORE INTO Slot (zone_id, floor_level, occupancy, slot_type, available_predict) VALUES ($zoneId, 1, 1, 'VIP', 92.5)");
            $slotId = $conn->lastInsertId() ?: 1;

            // 5. Slot_Catalog (M:N Mapping)
            $conn->exec("INSERT IGNORE INTO Slot_Catalog (slot_id, cat_id) VALUES ($slotId, $catId)");

            // 6. Vehicles
            $licensePlate = "XYZ-" . rand(1000, 9999);
            $conn->exec("INSERT INTO Vehicles (license_plate, user_id, cat_id, reg_status, vehicle_type, risk_score) VALUES ('$licensePlate', $userId, $catId, 'Registered', 'Standard', 12.5)");

            // --- 2ND LEVEL DEPENDENCIES ---
            // 7. Session
            $conn->exec("INSERT INTO Session (license_plate, entry_time) VALUES ('$licensePlate', NOW())");
            $sessionId = $conn->lastInsertId();

            // 8. Session_Slot (M:N Mapping)
            $conn->exec("INSERT INTO Session_Slot (session_id, slot_id) VALUES ($sessionId, $slotId)");

            // --- 3RD LEVEL DEPENDENCIES ---
            // 9. Payment
            $baseRate = 15.00;
            $predictedCost = 18.50;
            $stmt = $conn->prepare("INSERT INTO Payment (session_id, base_rate, predicted_cost, final_fee, suggestions) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sessionId, $baseRate, $predictedCost, 20.00, 'Try parking during off-peak hours to save 15%']);
            $slipId = $conn->lastInsertId();

            // 10. Violations (Customer sped in the parking lot)
            $stmt = $conn->prepare("INSERT INTO Violations (vehicleNumber, slip_id, violation_type, distance_score) VALUES (?, ?, ?, ?)");
            $stmt->execute([$licensePlate, $slipId, 'Speeding Limit Exceeded', 85.4]);
            $violationId = $conn->lastInsertId();

            // 11. Blacklist (Adding to blacklist due to violation)
            $stmt = $conn->prepare("INSERT INTO Blacklist (violation_id, date_added, reason) VALUES (?, NOW(), ?)");
            $stmt->execute([$violationId, 'Reckless driving inside Premium Zone A']);

            // 12. Review
            $stmt = $conn->prepare("INSERT INTO Review (user_id, text, submit_date, sentiment_score, fake_flag, rating_score) VALUES (?, ?, NOW(), ?, ?, ?)");
            $stmt->execute([$userId, 'Great spot but the AI flagged me for speeding!', 0.45, 0, 3]);

            // 13. SYS_ACT_Log
            $stmt = $conn->prepare("INSERT INTO SYS_ACT_Log (user_id, actions, anomaly_flag, timestamp) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$userId, 'System processed complete AI parking simulation for vehicle ' . $licensePlate, 1]);

            $conn->commit();

            echo json_encode([
                "success" => true, 
                "message" => "Simulated end-to-step flow successfully.",
                "details" => "Interacted sequentially with all 13 AI architecture tables."
            ]);
            break;

        case 'get_dashboard_stats':
            // Complex JOIN query across multiple levels to show off to faculty
            $query = "
                SELECT 
                    v.license_plate,
                    u.user_type,
                    c.cat_type,
                    s.entry_time,
                    p.final_fee,
                    p.predicted_cost,
                    p.suggestions
                FROM Session s
                JOIN Vehicles v ON s.license_plate = v.license_plate
                JOIN User u ON v.user_id = u.user_id
                JOIN Vehicle_Category c ON v.cat_id = c.cat_id
                LEFT JOIN Payment p ON s.session_id = p.session_id
                ORDER BY s.entry_time DESC LIMIT 10
            ";
            $stats = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(["success" => true, "data" => $stats]);
            break;

        default:
            echo json_encode(["error" => "Invalid or missing 'action' parameter. Use ?action=get_all_data or ?action=simulate_parking_flow"]);
    }
} catch(Exception $e) {
    if($conn->inTransaction()) { $conn->rollBack(); }
    echo json_encode(["error" => "Transaction Failed: " . $e->getMessage()]);
}
?>
