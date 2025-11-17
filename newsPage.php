<?php
require_once __DIR__ . '/functions/functions.php';
startSession();

/* -------- Delete post -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (($_SESSION['loggedUserRole'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
    $id = (int)($_POST['post_id'] ?? 0);
    if ($id <= 0) exit('Invalid post id');

    $conn = connectToDb();
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $conn->close();
    header('Location: newsPage.php');
    exit;
}

/* -------- Add comment (append to posts.comments) -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'comment') {
    $postId   = (int)($_POST['post_id'] ?? 0);
    $content  = trim($_POST['content'] ?? '');
    $username = $_SESSION['loggedUserName'] ?? 'Guest';
    if ($postId > 0 && $content !== '') {
        $conn = connectToDb();
        $stmt = $conn->prepare("
            UPDATE posts
            SET comments = CASE
              WHEN comments IS NULL OR comments = '' THEN ?
              ELSE CONCAT(comments, '\n', ?)
            END
            WHERE id = ?
        ");
        $line = sprintf("%s: %s", $username, $content);
        $stmt->bind_param('ssi', $line, $line, $postId);
        $stmt->execute();
        $conn->close();
    }
    header('Location: newsPage.php');
    exit;
}

/* -------- Delete single comment (admin only) -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_comment') {
    if (($_SESSION['loggedUserRole'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }

    $postId = (int)($_POST['post_id'] ?? 0);
    $index  = (int)($_POST['index'] ?? -1);
    if ($postId > 0 && $index >= 0) {
        $conn = connectToDb();
        $conn->set_charset('utf8mb4');

        // read
        $stmt = $conn->prepare("SELECT comments FROM posts WHERE id = ?");
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            $lines = preg_split("/\r\n|\r|\n/", (string)($row['comments'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            if (isset($lines[$index])) {
                array_splice($lines, 1 * $index, 1);
                $new = implode("\n", $lines);
                $stmt = $conn->prepare("UPDATE posts SET comments = ? WHERE id = ?");
                $stmt->bind_param('si', $new, $postId);
                $stmt->execute();
                $stmt->close();
            }
        }
        $conn->close();
    }
    header('Location: newsPage.php');
    exit;
}

/* -------- Load posts -------- */
$isAdmin  = (($_SESSION['loggedUserRole'] ?? '') === 'admin');
$username = ($_SESSION['loggedUserName'] ?? '');

$conn = connectToDb();

$sql = "SELECT p.id, p.user_id, p.comments, u.username, p.category, p.title, p.content, p.created_at
        FROM posts AS p
        JOIN users AS u ON p.user_id = u.id
        ORDER BY p.created_at DESC";
$result = $conn->query($sql);
$posts = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    $result->free();
}
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>News</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="newsPage.css">
    <script>
        window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
        window.USERNAME = <?= json_encode($username) ?>;
        window.posts = <?= json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>


<body>
    <header class="site-header">
        <?php if (!empty($username)): ?>
            <span>👋 <?= htmlspecialchars($username) ?>, you’re logged in</span>
            <a href="logout.php" style="margin-left:12px">Log out</a>
        <?php else: ?>
            <a href="userCreation.php">Log in</a>
        <?php endif; ?>
    </header>

    <nav class="navbar navbar-dark bg-dark fixed-top site-nav">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Capo News</a>

            <form class="d-flex align-items-center flex-nowrap me-3" id="filterForm" style="gap:0.5rem;">
                <select id="categoryFilter" class="form-select form-select-sm w-auto bg-dark text-light border-secondary">
                    <option value="All">All</option>
                    <option value="Tech">Tech</option>
                    <option value="Politics">Politics</option>
                    <option value="Economics">Economics</option>
                </select>
                <input id="searchFilter" class="form-control form-control-sm bg-dark text-light border-secondary"
                    type="search" placeholder="Search title..." aria-label="Search">
                <button id="resetFilters" class="btn btn-outline-light btn-sm" type="button">↺</button>
            </form>

            <button class="navbar-toggler" type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasDarkNavbar"
                aria-controls="offcanvasDarkNavbar"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>
    <div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1"
        id="offcanvasDarkNavbar" aria-labelledby="offcanvasDarkNavbarLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasDarkNavbarLabel">Navigate Through</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="/indexPage.php">Home</a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Dropdown
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item" href="/postpage.php">Posts Page</a></li>
                        <li><a class="dropdown-item" href="/indexPage.php">Search Page</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>


    <main class="content-wrapper pt-5 px-4 pb-4">
        <section id="newsGrid" class="grid"></section>
        <div id="emptyState" style="display:none;">No posts yet.</div>
    </main>

    <script>
        let posts = window.posts || [];
        const allPosts = posts.slice(); // safe copy once
        const grid = document.getElementById("newsGrid");
        const empty = document.getElementById("emptyState");

        function formatDate(iso) {
            return new Date(iso).toLocaleString();
        }

        function esc(s) {
            return (s || '').replace(/[&<>"']/g, m => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [m]));
        }

        function render() {
            grid.innerHTML = "";
            if (!posts.length) {
                empty.style.display = "block";
                return;
            }
            empty.style.display = "none";

            for (const post of posts) {
                const card = document.createElement("article");
                card.className = "card";
                card.id = `post-${post.id}`;

                const adminControls = window.IS_ADMIN ? `
                <form method="post" action="newsPage.php" style="margin-top:8px">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="post_id" value="${post.id}">
                    <button type="submit" class="danger">🗑️ Delete</button>
                </form>
                ` : "";

                card.innerHTML = `
                    <h3>${post.title}</h3>
                    <p>${post.content}</p>
                    <small>By ${post.username} • ${formatDate(post.created_at)} • #${post.category}</small>
                    ${adminControls}

                    <div class="comments-section" style="margin-top:10px;">
                        <!-- Comments are always visible -->
                        <div class="comment-list mt-2"></div>

                        <!-- Button toggles only the comment form -->
                        <button class="btn btn-sm btn-outline-primary comment-toggle mt-2">💬 Add Comment</button>

                        <!-- This is hidden initially (the form only) -->
                        <div class="comment-box" style="display:none; margin-top:8px;">
                            <form method="post" action="newsPage.php">
                                <input type="hidden" name="action" value="comment">
                                <input type="hidden" name="post_id" value="${post.id}">
                                <textarea name="content" class="form-control mb-2" rows="2" placeholder="Write a comment..."></textarea>
                                <button class="btn btn-sm btn-primary">Post Comment</button>
                            </form>
                        </div>
                    </div>
                `;

                // fill comments
                const list = card.querySelector('.comment-list');
                if (post.comments) {
                    const items = String(post.comments).split('\n').filter(Boolean);
                    list.innerHTML = items.map((line, i) => `
                    <div class="comment bg-light p-2 rounded mb-1 d-flex align-items-start justify-content-between gap-2">
                    <div class="flex-grow-1">${esc(line)}</div>
                    ${window.IS_ADMIN ? `
                        <form method="post" action="newsPage.php" class="d-inline">
                        <input type="hidden" name="action" value="delete_comment">
                        <input type="hidden" name="post_id" value="${post.id}">
                        <input type="hidden" name="index" value="${i}">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete comment">🗑</button>
                        </form>` : ``}
                          </div>
                     `).join('');
                }

                grid.appendChild(card);
            }
        }
        render();

        // bind once, OUTSIDE render()
        grid.addEventListener('click', (e) => {
            if (e.target.classList.contains('comment-toggle')) {
                const box = e.target.closest('.comments-section').querySelector('.comment-box');
                box.style.display = (box.style.display === 'none' || !box.style.display) ? 'block' : 'none';
            }
        });

        const categorySelect = document.getElementById('categoryFilter');
        const searchInput = document.getElementById('searchFilter');
        const resetBtn = document.getElementById('resetFilters');

        categorySelect.addEventListener('change', filterPosts);
        searchInput.addEventListener('input', filterPosts);
        resetBtn.addEventListener('click', () => {
            categorySelect.value = 'All';
            searchInput.value = '';
            posts = allPosts.slice(); // reset
            render();
        });

        function filterPosts() {
            const category = categorySelect.value;
            const search = (searchInput.value || '').toLowerCase();

            posts = allPosts.filter(p => {
                const matchesCategory = category === 'All' || String(p.category) === category;
                const title = (p.title || '').toLowerCase();
                const matchesSearch = title.includes(search);
                return matchesCategory && matchesSearch;
            });

            render();
        }
    </script>
</body>

</html>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    crossorigin="anonymous"></script>



</doctype>