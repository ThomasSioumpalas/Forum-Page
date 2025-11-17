<?php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/functions/functions.php';

$conn = connectToDb();

$q = trim($_GET['q'] ?? '');
$limit = 20;

$sql = "
  SELECT 
    p.id, p.title, p.content, p.category, p.created_at,
    u.username
  FROM posts p
  JOIN users u ON u.id = p.user_id
  WHERE (? = '' 
     OR p.title    LIKE CONCAT('%', ?, '%')
     OR p.content  LIKE CONCAT('%', ?, '%')
     OR u.username LIKE CONCAT('%', ?, '%')
     OR p.category LIKE CONCAT('%', ?, '%'))
  ORDER BY p.created_at DESC
  LIMIT ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare statement']);
    exit;
}

$stmt->bind_param('sssssi', $q, $q, $q, $q, $q, $limit);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to execute statement']);
    exit;
}

$res = $stmt->get_result();
$rows = [];

while ($row = $res->fetch_assoc()) {
    $snippet = function_exists('mb_substr')
        ? mb_substr($row['content'] ?? '', 0, 180)
        : substr($row['content'] ?? '', 0, 180);

    $rows[] = [
        'id'         => (int)($row['id'] ?? 0),
        'title'      => (string)($row['title'] ?? ''),
        'content'    => (string)($row['content'] ?? ''),
        'snippet'    => (string)$snippet,
        'category'   => (string)($row['category'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'username'   => (string)($row['username'] ?? ''),
    ];
}

echo json_encode(['posts' => $rows], JSON_UNESCAPED_UNICODE);
exit;
