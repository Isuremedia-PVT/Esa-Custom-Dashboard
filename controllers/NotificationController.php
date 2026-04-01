<?php
require_once __DIR__ . '/../config/database.php';

class NotificationController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function addNotification($user_id, $actor_id, $type, $message, $reference_id = null) {
        $this->cleanupOldNotifications(15); // Auto-cleanup old notifications here instead
        try {
            $stmt = $this->pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, message, reference_id) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([$user_id, $actor_id, $type, $message, $reference_id]);
            return $result;
        } catch (PDOException $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }

    public function getNotifications($user_id, $limit = 10, $unread_only = false) {
        try {
            $query = "SELECT n.*, u.username as actor_name, u.profile_url as actor_profile 
                       FROM notifications n 
                       JOIN users u ON n.actor_id = u.id 
                       WHERE n.user_id = ?";
            if ($unread_only) {
                $query .= " AND n.is_read = 0";
            }
            $query .= " ORDER BY n.created_at DESC LIMIT ?";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(1, (int)$user_id, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getNotificationsPaginated($user_id, $page = 1, $limit = 15) {
        try {
            $offset = ($page - 1) * $limit;
            
            // Get total
            $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
            $stmtCount->execute([$user_id]);
            $total = (int)$stmtCount->fetchColumn();
            
            // Get data
            $stmtData = $this->pdo->prepare("SELECT n.*, u.username as actor_name, u.profile_url as actor_profile 
                                             FROM notifications n 
                                             JOIN users u ON n.actor_id = u.id 
                                             WHERE n.user_id = ? 
                                             ORDER BY n.created_at DESC 
                                             LIMIT ? OFFSET ?");
            $stmtData->bindValue(1, (int)$user_id, PDO::PARAM_INT);
            $stmtData->bindValue(2, (int)$limit, PDO::PARAM_INT);
            $stmtData->bindValue(3, (int)$offset, PDO::PARAM_INT);
            $stmtData->execute();
            $notifications = $stmtData->fetchAll(PDO::FETCH_ASSOC);
            
            return ['data' => $notifications, 'total' => $total];
        } catch (PDOException $e) {
            return ['data' => [], 'total' => 0];
        }
    }

    public function getUnreadCount($user_id) {
        try {
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function markAsRead($user_id, $notification_id = null) {
        try {
            if ($notification_id) {
                $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                return $stmt->execute([$notification_id, $user_id]);
            } else {
                $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                return $stmt->execute([$user_id]);
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public function cleanupOldNotifications($days = 15) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            return $stmt->execute([(int)$days]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function deleteNotification($user_id, $notification_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notification_id, $user_id]);
        } catch (PDOException $e) {
            return false;
        }
    }
}
