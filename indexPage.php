<?php

require_once __DIR__ . '/functions/functions.php';
startSession();


$isAdmin = (($_SESSION['loggedUserRole'] ?? '') === 'admin');
$username = ($_SESSION['loggedUserName'] ?? '');

$conn = connectToDb();
$sql = "SELECT username
    FROM users";

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
    <meta charset="UTF-8" />
    <title>Simple Forum Box</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="indexPage.css">
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
                    <a class="nav-link" href="/newsPage.php">News</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="/postPage.php">Post</a>
                </li>
            </ul>
        </div>
    </div>


    <div class="explore-container">
        <h2>Explore Posts</h2>


        <div class="explore-container">
            <h2>Explore Posts</h2>

            <div class="search-box" style="display:flex;gap:.5rem;align-items:center;">
                <input id="searchInput" type="text" placeholder="Search posts, topics, or users..." class="form-control" />
                <button id="clearBtn" type="button" class="btn btn-secondary">Clear</button>
            </div>
            <p id="searchHint" class="text-muted mt-2">Start typing to search the latest posts…</p>
            <div id="results" class="results mt-3"></div>
        </div>

        <script>
            const resultsEl = document.getElementById('results');
            const inputEl = document.getElementById('searchInput');
            const hintEl = document.getElementById('searchHint');
            const clearBtn = document.getElementById('clearBtn');
            const API = 'api-indexPage.php'; 
            const MIN_CHARS = 2; 

      
            resultsEl.style.display = 'none';
            hintEl.textContent = `Type at least ${MIN_CHARS} characters to get recommendations…`;

            function escapeHTML(str) {
                return (str || '').replace(/[&<>"']/g, s => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [s]));
            }

            function formatDate(iso) {
                try {
                    return new Date(iso).toLocaleString();
                } catch {
                    return iso || '';
                }
            }

            function renderPosts(posts) {
                if (!posts.length) {
                    resultsEl.innerHTML = `<div class="text-muted">No matching posts.</div>`;
                    resultsEl.style.display = 'block';
                    return;
                }
                resultsEl.innerHTML = posts.map(p => `
                    <article class="card p-3 mb-2">
                        <h5 class="mb-1">${escapeHTML(p.title)}</h5>
                        <small class="text-secondary d-block mb-2">
                        By ${escapeHTML(p.username)} • ${formatDate(p.created_at)} • #${escapeHTML(String(p.category))}
                        </small>
                        <p class="mb-2">${escapeHTML(p.snippet)}${p.content && p.content.length > (p.snippet||'').length ? '…' : ''}</p>
                        <a class="btn btn-sm btn-primary" href="newsPage.php#post-${p.id}">Open on News page</a>
                    </article>
                    `).join('');
                resultsEl.style.display = 'block';
            }

            let timer = null;

            function debouncedSearch() {
                clearTimeout(timer);
                timer = setTimeout(runSearch, 250);
            }

            async function runSearch() {
                const q = inputEl.value.trim();

                if (q.length < MIN_CHARS) {
                    resultsEl.innerHTML = '';
                    resultsEl.style.display = 'none';
                    hintEl.textContent = q.length ?
                        `Keep typing… need ${MIN_CHARS - q.length} more character${MIN_CHARS - q.length === 1 ? '' : 's'}` :
                        `Type at least ${MIN_CHARS} characters to get recommendations…`;
                    return;
                }

                hintEl.textContent = `Searching for “${q}”…`;

                try {
                    const res = await fetch(`${API}?q=${encodeURIComponent(q)}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const ct = res.headers.get('content-type') || '';
                    if (!res.ok || !ct.includes('application/json')) {
                        throw new Error(`Bad response: ${res.status} ${ct}`);
                    }
                    const data = await res.json();
                    renderPosts(data.posts || []);
                    hintEl.textContent = `Results for “${q}”`;
                } catch (e) {
                    resultsEl.innerHTML = `<div class="text-danger">Error loading results.</div>`;
                    resultsEl.style.display = 'block';
                    console.error(e);
                }
            }

            inputEl.addEventListener('input', debouncedSearch);

            clearBtn.addEventListener('click', () => {
                inputEl.value = '';
                resultsEl.innerHTML = '';
                resultsEl.style.display = 'none';
                hintEl.textContent = `Type at least ${MIN_CHARS} characters to get recommendations…`;
                inputEl.focus();
            });

        </script>

    </div>

</body>

</html>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>