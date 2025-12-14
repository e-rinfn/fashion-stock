<?php
// config/lock_functions.php

/**
 * Cek apakah data sedang terkunci
 */
function isDataLocked($data_type, $data_id)
{
    global $conn;

    // Hapus lock yang sudah expired
    $conn->query("DELETE FROM data_locks WHERE expires_at < NOW()");

    $sql = "SELECT dl.*, u.username 
            FROM data_locks dl 
            LEFT JOIN users u ON dl.user_id = u.id 
            WHERE dl.data_type = ? AND dl.data_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $data_type, $data_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $lock = $result->fetch_assoc();

        // Jika lock masih valid
        if (strtotime($lock['expires_at']) > time()) {
            return [
                'locked' => true,
                'locked_by' => $lock['username'] ?: 'User ID ' . $lock['user_id'],
                'user_id' => $lock['user_id'],
                'locked_at' => $lock['locked_at'],
                'expires_at' => $lock['expires_at']
            ];
        } else {
            // Hapus lock yang sudah expired
            $conn->query("DELETE FROM data_locks WHERE id = " . $lock['id']);
        }
    }

    return ['locked' => false];
}

/**
 * Mengunci data
 */
function lockData($data_type, $data_id, $lock_duration_minutes = 5)
{
    global $conn;

    // Ambil user_id dari session
    $user_id = $_SESSION['user_id'] ?? 0;
    $session_id = session_id();

    if (!$user_id) {
        return false;
    }

    // Hapus lock yang sudah expired
    $conn->query("DELETE FROM data_locks WHERE expires_at < NOW()");

    // Coba untuk lock data
    $expires_at = date('Y-m-d H:i:s', time() + ($lock_duration_minutes * 60));

    try {
        $sql = "INSERT INTO data_locks (data_type, data_id, user_id, session_id, expires_at) 
                VALUES (?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                user_id = VALUES(user_id), 
                session_id = VALUES(session_id), 
                locked_at = NOW(), 
                expires_at = VALUES(expires_at)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siiss", $data_type, $data_id, $user_id, $session_id, $expires_at);

        return $stmt->execute();
    } catch (Exception $e) {
        // Jika ada konflik, berarti data sedang dikunci oleh user lain
        return false;
    }
}

/**
 * Melepaskan lock
 */
function releaseLock($data_type, $data_id)
{
    global $conn;

    $user_id = $_SESSION['user_id'] ?? 0;
    $session_id = session_id();

    $sql = "DELETE FROM data_locks 
            WHERE data_type = ? AND data_id = ? 
            AND user_id = ? AND session_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siis", $data_type, $data_id, $user_id, $session_id);

    return $stmt->execute();
}

/**
 * Force release lock (untuk admin)
 */
function forceReleaseLock($data_type, $data_id)
{
    global $conn;

    $sql = "DELETE FROM data_locks WHERE data_type = ? AND data_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $data_type, $data_id);

    return $stmt->execute();
}

/**
 * Cleanup expired locks
 */
function cleanupExpiredLocks()
{
    global $conn;
    return $conn->query("DELETE FROM data_locks WHERE expires_at < NOW()");
}
