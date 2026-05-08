<?php
require_once __DIR__ . '/../config/database.php';

function generateRegistrationCode(): string {
    return 'CV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatDate(string $date, string $format = 'd M Y'): string {
    $months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $d = new DateTime($date);
    if ($format === 'd M Y') {
        return $d->format('d') . ' ' . $months[$d->format('n') - 1] . ' ' . $d->format('Y');
    }
    return $d->format($format);
}

function formatDatetime(string $datetime): string {
    return formatDate($datetime, 'd M Y') . ' · ' . (new DateTime($datetime))->format('H:i') . ' WIB';
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    return formatDate($datetime);
}

function getInitials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= strtoupper(mb_substr($word, 0, 1));
    }
    return $initials;
}

function truncate(string $text, int $limit = 100): string {
    if (mb_strlen($text) <= $limit) return $text;
    return mb_substr($text, 0, $limit) . '...';
}

function getQuotaStatus(int $registered, int $quota): array {
    $percent = $quota > 0 ? round(($registered / $quota) * 100) : 100;
    $remaining = max(0, $quota - $registered);
    $status = $percent >= 100 ? 'full' : ($percent >= 75 ? 'almost-full' : 'available');
    return compact('percent', 'remaining', 'status');
}

function uploadPoster(array $file, int $eventId): string|false {
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed)) return false;
    if ($file['size'] > 2 * 1024 * 1024) return false;
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "event_{$eventId}_" . time() . ".{$ext}";
    $dest = __DIR__ . "/../uploads/posters/{$filename}";
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return $filename;
}

function getRecommendedEvents(int $userId, int $limit = 6): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT e.*, c.name as category_name, c.icon as category_icon,
               u.name as organizer_name,
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status != 'cancelled') as registered_count
        FROM events e
        JOIN categories c ON e.category_id = c.id
        JOIN users u ON e.organizer_id = u.id
        WHERE e.status = 'published'
          AND e.registration_deadline > NOW()
          AND e.category_id IN (
              SELECT category_id FROM user_interests WHERE user_id = :uid
          )
          AND e.id NOT IN (
              SELECT event_id FROM registrations WHERE user_id = :uid2 AND status != 'cancelled'
          )
        ORDER BY e.date_start ASC
        LIMIT :lim
    ");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function sendNotification(int $userId, string $title, string $message, string $type = 'sistem', ?int $eventId = null): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, event_id, type, title, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $eventId, $type, $title, $message]);
}

function sendEventNotificationToInterestedUsers(int $eventId, int $categoryId, string $eventTitle): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT user_id FROM user_interests WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $users = $stmt->fetchAll();
    foreach ($users as $user) {
        sendNotification(
            $user['user_id'],
            'Event Baru Untukmu!',
            "Event \"{$eventTitle}\" cocok dengan minatmu. Cek sekarang!",
            'event_baru',
            $eventId
        );
    }
}

function getUnreadNotificationCount(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function getCategoryBadgeClass(string $categoryName): string {
    $map = [
        'Teknologi'            => 'badge-tech',
        'Seni & Budaya'        => 'badge-art',
        'Olahraga'             => 'badge-sport',
        'Kewirausahaan'        => 'badge-business',
        'Penelitian & Akademik'=> 'badge-research',
        'Sosial & Lingkungan'  => 'badge-social',
        'Kesehatan'            => 'badge-health',
        'Musik'                => 'badge-music',
    ];
    return $map[$categoryName] ?? 'badge-research';
}

function isEventRegistered(int $userId, int $eventId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ? AND status != 'cancelled'");
    $stmt->execute([$userId, $eventId]);
    return (bool) $stmt->fetch();
}
