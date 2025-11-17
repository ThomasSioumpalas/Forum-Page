<?php
require __DIR__ . '/functions/functions.php';
session_start();

if (!isset($username)) {
  $username = '';
}

$authUserId = 1; // TEMP for local dev

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

// collect POST
$title    = trim($_POST['title'] ?? '');
$body     = trim($_POST['body'] ?? '');
$category = trim($_POST['category'] ?? '');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($title === '' || $body === '' || $category === '') {
    $error = 'Title, content and category are required.';
  } else {
    $conn = connectToDb();
    if (!$conn) {
      $error = 'DB connection failed.';
    } else {
      $conn->set_charset('utf8mb4');

      $stmt = $conn->prepare(
        'INSERT INTO posts (user_id, title, content, category) 
        VALUES (?, ?, ?, ?)'
      );

      if ($stmt === false) {
        $error = 'DB prepare failed: ' . $conn->error;
      } else {
        // user_id=int, title,string, content,string, category,string
        $stmt->bind_param('isss', $authUserId, $title, $body, $category);
        if (!$stmt->execute()) {
          $error = 'DB execute failed: ' . $stmt->error;
        } else {
          $stmt->close();
          $conn->close();
          header('Location: /newsPage.php');
          exit;
        }
        $stmt->close();
      }
      $conn->close();
    }
  }
}




?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Simple Forum Box</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="postPage.css">
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
          <a class="nav-link active" aria-current="page" href="/indexPage.php">Search</a>
        </li>
      </ul>
    </div>
  </div>


  <div class="forum">
    <h2>Community Posts</h2>
    <!-- Post form. -->

    <form id="postForm" class="post-form" method="post">
      <input id="title" name="title" type="text" placeholder="Title" required />
      <textarea id="body" name="body" placeholder="Write your post..." required></textarea>
      <select id="category" name="category" required>
        <option value="">-- Choose a category --</option>
        <option value="Tech">Tech</option>
        <option value="Economics">Economics</option>
        <option value="Politics">Politics</option>
      </select>
      <button type="submit">Post</button>
    </form>


    <?php if (!empty($error)): ?>
      <div class="text-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div id="posts" class="posts"></div>
  </div>
  <script src="https://cdn.tiny.cloud/1/ndcr4ifxkhju98x64vkbid2clma6bvke921ubtycnz784e40/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>

  <!-- tinymce starts -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      if (window.tinymce && typeof tinymce.init === 'function') {
        tinymce.init({
          selector: '#body',
          height: 320,
          menubar: false,
          plugins: ['image', 'link', 'lists', 'paste', 'code'],
          toolbar: 'undo redo | bold italic | bullist numlist | link | code',
          branding: false,
          setup: function(editor) {
            editor.on('change input undo redo', function() {
              editor.save(); // sync to textarea
            });
          },
        });
      }
    });

    // timymce stops

    // Form handling with guard for TinyMCE
    const form = document.getElementById('postForm');
    const titleInput = document.getElementById('title');
    const bodyInput = document.getElementById('body');
    const categoryInput = document.getElementById('category');
    const postsBox = document.getElementById('posts');

    form.addEventListener('submit', function(event) {
      if (window.tinymce && typeof tinymce.triggerSave === 'function') {
        tinymce.triggerSave(); // sync editor -> textarea
      }

      const titleValue = titleInput.value.trim();
      const bodyValue = bodyInput.value.trim();
      const categoryValue = categoryInput.value.trim();

      if (!titleValue || !bodyValue || !categoryValue) {
        event.preventDefault();
        postsBox.innerText = "You haven't put the required inputs";
        return;
      }

      postsBox.innerText = new Date().toLocaleString();
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    crossorigin="anonymous"></script>

</body>


</html>
</doctype>