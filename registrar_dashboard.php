<?php
class KASMSSessionHandler implements SessionHandlerInterface {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string {
        $stmt = $this->conn->prepare("SELECT data FROM sessions WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        // Return raw JSON data or empty string if not found
        return $row ? json_decode($row['data'], true) ?: '' : '';
    }

    public function write($id, $data): bool {
        // Ensure $data is a valid JSON string; default to empty object if empty or null
        $jsonData = !empty($data) ? json_encode($data) : '{}';
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback to empty object on JSON encoding failure
            $jsonData = '{}';
        }

        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $stmt = $this->conn->prepare("REPLACE INTO sessions (id, user_id, data, last_accessed) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sis", $id, $user_id, $jsonData);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function destroy($id): bool {
        $stmt = $this->conn->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->bind_param("s", $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function gc($maxlifetime): int|false {
        $stmt = $this->conn->prepare("DELETE FROM sessions WHERE last_accessed < DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->bind_param("i", $maxlifetime);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }
}

function initializeSessionHandler() {
    $conn = mysqli_connect("p:localhost", "root", "0000", "kasms_db");
    if (!$conn) {
        die("Session handler connection failed: " . mysqli_connect_error());
    }
    $handler = new KASMSSessionHandler($conn);
    session_set_save_handler($handler, true);
    register_shutdown_function('session_write_close');
    session_start();
}
?>