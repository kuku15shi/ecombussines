<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$likedVideos = $_SESSION['liked_videos'] ?? [];
$videos = [];
try {
  // Fetch real videos from DB
  $stmt = $pdo->query("SELECT v.*, p.name as product_name, p.price, p.discount_percent, p.images, p.slug as product_slug 
                         FROM videos v 
                         LEFT JOIN products p ON v.product_id = p.id 
                         WHERE v.is_active = 1 
                         ORDER BY v.created_at DESC");
  $videos = $stmt->fetchAll();
} catch (Exception $e) {
  // Fallback to demo data if DB table missing
}

// Ensure we have some data
if (empty($videos)) {
  $videos = [
    [
      'id' => 1,
      'title' => '🔥 Premium Urban Sneakers Unboxing',
      'description' => 'The most comfortable sneakers of 2026. Get yours before they run out! #streetwear #sneakers #fashion',
      'video_url' => 'https://www.w3schools.com/html/mov_bbb.mp4',
      'product_name' => 'Urban Classic Runner',
      'price' => '4999',
      'offer_price' => '2999',
      'product_image' => 'demo_sneaker.jpg',
      'product_slug' => 'urban-classic',
      'likes' => 1245,
      'comments' => 45,
      'shares' => 12
    ]
  ];
}

// ENVIRONMENT CLEANUP: Fix hardcoded localhost URLs for production
foreach ($videos as &$v) {
    if (isset($v['video_url']) && strpos($v['video_url'], 'localhost') !== false) {
        $parts = explode('/uploads/', $v['video_url']);
        if (count($parts) > 1) {
            $v['video_url'] = SITE_URL . '/uploads/' . $parts[1];
        }
    }
}
unset($v);

$currentUser = getCurrentUser($pdo);
// Only getting required components for bottom nav
$cartCount = getCartCount($pdo);
$wishlistCount = getWishlistCount($pdo);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Videos & Reels - MIZ MAX</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
  <style>
    /* Reset & Full Screen for Reels */
    body,
    html {
      margin: 0;
      padding: 0;
      height: 100vh;
      overflow: hidden;
      background: #000;
      color: #fff;
      font-family: 'Inter', sans-serif;
    }

    /* Hide desktop header */
    nav.navbar {
      display: none !important;
    }

    .mobile-header {
      display: none !important;
    }

    /* Video Feed Container */
    .video-feed {
      height: 100vh;
      overflow-y: scroll;
      scroll-snap-type: y mandatory;
      scrollbar-width: none;
      /* Firefox */
      position: relative;
    }

    .video-feed::-webkit-scrollbar {
      display: none;
    }

    .video-container {
      height: 100vh;
      width: 100vw;
      scroll-snap-align: start;
      position: relative;
      background: #000;
    }

    video {
      width: 100%;
      height: 100%;
      object-fit: cover;
      /* Fill mobile screen perfectly */
      position: absolute;
      top: 0;
      left: 0;
      z-index: 1;
    }

    /* Top Navigation Elements in video */
    .video-top-nav {
      position: absolute;
      top: 11px;
      left: 0;
      right: 0;
      z-index: 10;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 20px;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.8);
    }

    .top-left-title {
      font-size: 1.3rem;
      font-weight: 700;
      letter-spacing: -0.5px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .top-right-icons i {
      font-size: 1.6rem;
      stroke-width: 1px;
      color: #fff;
    }

    /* Right Sidebar Controls */
    .video-sidebar {
      position: absolute;
      right: 15px;
      bottom: 130px;
      /* Safely above the bottom navigation bar */
      z-index: 10;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
    }

    .action-button {
      background: transparent;
      border: none;
      color: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2px;
      cursor: pointer;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.8);
      transition: transform 0.2s;
    }

    .action-button:active {
      transform: scale(0.9);
    }

    .action-button i {
      font-size: 1.8rem;
      filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.5));
      stroke-width: 1px;
    }

    .action-button span {
      font-size: 0.75rem;
      font-weight: 600;
      margin-top: -2px;
      letter-spacing: -0.2px;
    }

    .heart-active {
      color: #fe2c55 !important;
    }

    /* Audio Album Art */
    .audio-album {
      width: 28px;
      height: 28px;
      border-radius: 4px;
      border: 2px solid #fff;
      margin-top: 10px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
      background: #333;
      animation: spin 5s linear infinite;
    }

    @keyframes spin {
      100% {
        transform: rotate(360deg);
      }
    }

    /* User Profile Pic Small */
    .profile-pic-small {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
      object-fit: cover;
    }

    /* Bottom Info overlay */
    .video-info {
      position: absolute;
      bottom: 10%;
      left: 15px;
      width: 80%;
      z-index: 10;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.8);
      padding-bottom: 0px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .username-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 2px;
    }

    .username {
      font-weight: 600;
      font-size: 0.95rem;
      color: #fff;
    }

    .follow-btn {
      background: transparent;
      color: #fff;
      border: none;
      padding: 0;
      font-size: 0.9rem;
      font-weight: 600;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.8);
      cursor: pointer;
    }

    .follow-dot {
      font-size: 0.6rem;
      color: #fff;
    }

    .description {
      font-size: 0.9rem;
      font-weight: 400;
      line-height: 1.3;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      margin-bottom: 4px;
    }

    /* Audio Track Marquee */
    .audio-track {
      font-size: 0.8rem;
      font-weight: 400;
      display: flex;
      align-items: center;
      gap: 5px;
      color: #eee;
    }

    /* Product Showcase Card */
    .product-showcase {
      background: #ffffff;
      border-radius: 12px;
      padding: 10px 12px;
      margin-top: 5px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      z-index: 20;
      color: #000;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      width: 100%;
      text-decoration: none;
      box-sizing: border-box;
    }

    .ps-img {
      width: 45px;
      height: 45px;
      border-radius: 6px;
      object-fit: cover;
      background: #eee;
    }

    .ps-info {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .ps-title {
      font-weight: 600;
      font-size: 0.8rem;
      line-height: 1.2;
      margin-bottom: 2px;
      color: #333;
      text-shadow: none;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 140px;
    }

    .ps-price {
      font-weight: 800;
      font-size: 0.9rem;
      color: #000;
      text-shadow: none;
    }

    .ps-btn {
      background: #000;
      color: #fff;
      border: none;
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.65rem;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Gradients for text readability */
    .video-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 50%;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 0%, rgba(0, 0, 0, 0.4) 40%, rgba(0, 0, 0, 0) 100%);
      z-index: 2;
      pointer-events: none;
    }

    /* Pause Icon Animation */
    .play-pause-icon {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(2);
      font-size: 4rem;
      color: rgba(255, 255, 255, 0.7);
      z-index: 5;
      opacity: 0;
      pointer-events: none;
      transition: all 0.3s ease;
    }

    .play-pause-icon.show {
      opacity: 1;
      transform: translate(-50%, -50%) scale(1);
    }

    /* Modify bottom nav to fit dark theme video feed seamlessly */
    .bottom-nav {
      background: rgba(0, 0, 0, 0.9) !important;
      border-top: 1px solid #222 !important;
    }

    .bottom-nav-item {
      color: #888 !important;
    }

    .bottom-nav-item.active {
      color: #fff !important;
    }

    /* PC Desktop View: Simulate mobile phone screen layout */
    @media (min-width: 768px) {
      body {
        background: #111;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
      }

      .video-feed {
        width: 400px;
        height: 90vh;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);
        margin: auto;
      }

      .video-container {
        border-radius: 20px;
        overflow: hidden;
        width: 100% !important;
        height: 100% !important;
        position: relative;
      }

      .bottom-nav {
        display: flex !important;
        /* Override d-md-none */
        width: 400px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        bottom: auto !important;
        top: calc(50vh + 45vh - 60px) !important;
        /* Move to bottom of the 90vh container */
        border-bottom-left-radius: 20px;
        border-bottom-right-radius: 20px;
      }

      /* On PC, make sure the text and sidebar sit nicely above the navigation bar */
      .video-info {
        bottom: 85px;
      }

      .video-sidebar {
        bottom: 85px;
        right: 15px;
      }
    }

    /* Comments Offcanvas Styles */
    .comments-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      z-index: 1000;
      display: none;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .comments-panel {
      position: fixed;
      left: 0;
      right: 0;
      bottom: -100%;
      height: 70vh;
      background: #111;
      z-index: 1001;
      border-top-left-radius: 20px;
      border-top-right-radius: 20px;
      transition: bottom 0.3s cubic-bezier(0.1, 0.82, 0.25, 1);
      display: flex;
      flex-direction: column;
      color: #fff;
      box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.5);
    }

    .comments-panel.open {
      bottom: 0;
    }

    .comments-overlay.open {
      display: block;
      opacity: 1;
    }

    .comments-header {
      padding: 15px;
      border-bottom: 1px solid #333;
      text-align: center;
      font-weight: 700;
      position: relative;
    }

    .close-comments {
      position: absolute;
      right: 15px;
      top: 15px;
      background: none;
      border: none;
      color: #fff;
      font-size: 1.2rem;
      cursor: pointer;
    }

    .comments-body {
      flex: 1;
      overflow-y: auto;
      padding: 15px;
    }

    .comment-item {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }

    .comment-avatar {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: #333;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.8rem;
    }

    .comment-text {
      font-size: 0.9rem;
      line-height: 1.4;
    }

    .comment-user {
      font-size: 0.8rem;
      font-weight: 700;
      color: #aaa;
      margin-bottom: 2px;
    }

    .comments-footer {
      padding: 10px 15px;
      border-top: 1px solid #333;
      display: flex;
      gap: 10px;
      background: #000;
    }

    .comments-input {
      flex: 1;
      padding: 10px 15px;
      border-radius: 20px;
      border: none;
      background: #222;
      color: #fff;
      outline: none;
    }

    .comments-submit {
      background: #fe2c55;
      color: #fff;
      border: none;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    @media (min-width: 768px) {
      .comments-panel {
        width: 400px;
        left: 50%;
        transform: translateX(-50%);
        border-radius: 20px;
        height: 60vh;
      }

      .comments-panel.open {
        bottom: calc(50vh - 30vh);
      }
    }
  </style>
</head>

<body>

  <div class="video-feed" id="videoFeed">
    <?php foreach ($videos as $index => $v): ?>
      <div class="video-container" data-id="<?= $v['id'] ?>">

        <div class="video-overlay"></div>

        <video src="<?= htmlspecialchars($v['video_url']) ?>" loop muted playsinline <?= $index === 0 ? 'autoplay' : '' ?>
          onclick="togglePlayPause(this)"></video>

        <i class="bi bi-play-fill play-pause-icon"></i>

        <div class="video-top-nav">
          <div class="top-left-title">Reels <i class="bi bi-chevron-down" style="font-size:1rem; margin-top:3px;"></i>
          </div>
        </div>

        <div class="video-sidebar">
          <?php $hasLiked = in_array($v['id'], $likedVideos); ?>
          <button class="action-button" onclick="toggleLike(this, <?= $v['id'] ?>)">
            <i class="bi <?= $hasLiked ? 'bi-heart-fill heart-active' : 'bi-heart' ?>" <?= $hasLiked ? 'style="color:#fe2c55"' : '' ?>></i>
            <span class="likes-count"><?= number_format($v['likes']) ?></span>
          </button>

          <button class="action-button" onclick="openComments(<?= $v['id'] ?>)">
            <i class="bi bi-chat"></i>
            <span id="comment-count-<?= $v['id'] ?>"><?= number_format($v['comments']) ?></span>
          </button>

          <button class="action-button"
            onclick="shareVideo(<?= $v['id'] ?>, '<?= addslashes(htmlspecialchars($v['title'])) ?>')">
            <i class="bi bi-send"></i>
            <span class="shares-count" id="share-count-<?= $v['id'] ?>"><?= number_format($v['shares'] ?? 0) ?></span>
          </button>

          <!-- <button class="action-button">
          <i class="bi bi-bookmark"></i>
          <span>12.5K</span>
        </button>
        
        <button class="action-button" style="margin-top:5px;">
          <i class="bi bi-three-dots"></i>
        </button> -->

          <img src="<?= SITE_URL ?>/assets/images/logo.png"
            onerror="this.src='https://placehold.co/100x100/111/fff?text=♫'" class="audio-album">
        </div>

        <div class="video-info">
          <!-- <div class="username-row">
            <img src="https://ui-avatars.com/api/?name=MIZ MAX&background=random" class="profile-pic-small" alt="Profile">
            <div class="username">MIZ MAX_official</div>
            <i class="bi bi-patch-check-fill" style="color:#38bdf8; font-size:0.8rem;"></i>
            <span class="follow-dot">•</span>
            <button class="follow-btn">Follow</button>
        </div> -->
          <div class="description"><?= htmlspecialchars($v['description']) ?></div>



          <?php
          if ($v['product_name']):
            $product_image = 'demo.jpg';
            if (!empty($v['images'])) {
              $imgs = json_decode($v['images'], true) ?? explode(',', $v['images']);
              $product_image = is_array($imgs) && count($imgs) > 0 ? $imgs[0] : $v['images'];
            }
            $offer_price = $v['price'];
            if (isset($v['discount_percent']) && $v['discount_percent'] > 0) {
              $offer_price = $v['price'] - ($v['price'] * $v['discount_percent'] / 100);
            }
            ?>
            <a href="<?= SITE_URL ?>/product.php?slug=<?= $v['product_slug'] ?>" class="product-showcase">
              <div style="display:flex; gap:10px; align-items:center;">
                <img src="<?= SITE_URL . '/uploads/' . $product_image ?>"
                  onerror="this.src='https://placehold.co/100x100?text=Product'" class="ps-img">
                <div class="ps-info">
                  <div class="ps-title"><?= htmlspecialchars($v['product_name']) ?></div>
                  <div class="ps-price"><?= formatPrice($offer_price) ?></div>
                </div>
              </div>
              <div class="ps-btn">Add to Cart</div>
            </a>
          <?php endif; ?>
          <div class="audio-track"><i class="bi bi-music-note"></i> Original Audio - MIZRIMAX_official</div>
        </div>

      </div>
    <?php endforeach; ?>
  </div>

  <?php include 'includes/bottom_nav.php'; ?>

  <!-- Comments Offcanvas Container -->
  <div class="comments-overlay" id="commentsOverlay" onclick="closeComments()"></div>
  <div class="comments-panel" id="commentsPanel">
    <div class="comments-header">
      Comments
      <button class="close-comments" onclick="closeComments()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="comments-body" id="commentsList">
      <!-- Real comments will be fetched via AJAX -->
      <div style="text-align:center; padding: 20px; color:#555;">Loading comments...</div>
    </div>
    <div class="comments-footer">
      <?php if ($currentUser): ?>
        <input type="text" class="comments-input" id="newComment" placeholder="Add a comment...">
        <button class="comments-submit" onclick="postComment()"><i class="bi bi-send-fill"></i></button>
      <?php else: ?>
        <div style="width: 100%; text-align: center; padding: 10px;">
          <a href="<?= SITE_URL ?>/login.php"
            style="color: #fe2c55; font-weight: bold; text-decoration: none; font-size: 0.95rem;">Login to add a
            comment</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Intersection Observer to autoplay videos when they come into view
    const videos = document.querySelectorAll('video');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        const video = entry.target;
        if (entry.isIntersecting) {
          video.play().catch(e => console.log('Autoplay blocked:', e));
        } else {
          video.pause();
          video.currentTime = 0; // Reset video when out of view
        }
      });
    }, {
      threshold: 0.6 // Trigger when 60% of the video is visible
    });

    videos.forEach(video => {
      observer.observe(video);
    });

    function togglePlayPause(videoElement) {
      const container = videoElement.closest('.video-container');
      const icon = container.querySelector('.play-pause-icon');

      // Modern Reels behavior: first interaction unmutes
      if (videoElement.muted) {
        videoElement.muted = false;
      }

      if (videoElement.paused) {
        videoElement.play();
        icon.classList.remove('bi-pause-fill');
        icon.classList.add('bi-play-fill');
        icon.classList.remove('show');
      } else {
        videoElement.pause();
        icon.classList.remove('bi-play-fill');
        icon.classList.add('bi-pause-fill');
        icon.classList.add('show');
        setTimeout(() => icon.classList.remove('show'), 1000);
      }
    }

    function toggleLike(btn, videoId) {
      const icon = btn.querySelector('i');
      const countSpan = btn.querySelector('.likes-count');
      let count = parseInt(countSpan.innerText.replace(/,/g, ''));

      let action = 'like';
      if (icon.classList.contains('heart-active')) {
        action = 'unlike';
        icon.classList.remove('heart-active', 'bi-heart-fill');
        icon.classList.add('bi-heart');
        icon.style.color = '#fff';
        countSpan.innerText = (count - 1).toLocaleString();
      } else {
        icon.classList.add('heart-active', 'bi-heart-fill');
        icon.classList.remove('bi-heart');
        icon.style.transform = 'scale(1.3)';
        setTimeout(() => icon.style.transform = 'scale(1)', 200);
        countSpan.innerText = (count + 1).toLocaleString();
      }

      fetch('ajax/video_interaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${action}&video_id=${videoId}`
      })
        .then(r => r.json())
        .then(data => { if (data.success) countSpan.innerText = Number(data.counts.likes).toLocaleString(); });
    }

    // Share API
    function shareVideo(videoId, title) {
      const recordShare = () => {
        fetch('ajax/video_interaction.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=share&video_id=${videoId}`
        }).then(r => r.json()).then(data => {
          if (data.success) document.getElementById('share-count-' + videoId).innerText = Number(data.counts.shares).toLocaleString();
        });
      };

      if (navigator.share) {
        navigator.share({
          title: title,
          text: 'Check out this awesome video on MIZ MAX!',
          url: window.location.origin + window.location.pathname + '?v=' + videoId
        }).then(recordShare).catch(err => console.log('Share dismissed'));
      } else {
        navigator.clipboard.writeText(window.location.origin + window.location.pathname + '?v=' + videoId);
        recordShare();
        alert('Video Link Copied successfully to your clipboard!');
      }
    }

    // Comments Offcanvas Logic
    let activeCommentVideoId = null;
    function openComments(videoId) {
      activeCommentVideoId = videoId;
      document.getElementById('commentsOverlay').classList.add('open');
      document.getElementById('commentsPanel').classList.add('open');

      const list = document.getElementById('commentsList');
      list.innerHTML = '<div style="text-align:center; padding: 20px; color:#555;">Loading comments...</div>';

      fetch('ajax/video_interaction.php?action=get_comments&video_id=' + videoId)
        .then(r => r.json())
        .then(data => {
          if (data.success && data.comments.length > 0) {
            list.innerHTML = '';
            data.comments.forEach(c => {
              list.innerHTML += `
                    <div class="comment-item">
                      <div class="comment-avatar" style="background:var(--primary); color:#fff;">G</div>
                      <div>
                        <div class="comment-user">${c.user_name}</div>
                        <div class="comment-text">${c.comment}</div>
                      </div>
                    </div>
                 `;
            });
          } else {
            list.innerHTML = '<div style="text-align:center; padding: 20px; color:#555;">No comments yet. Be the first!</div>';
          }
        }).catch(e => {
          list.innerHTML = '<div class="comment-item">Could not load comments.</div>';
        });
    }

    function closeComments() {
      document.getElementById('commentsOverlay').classList.remove('open');
      document.getElementById('commentsPanel').classList.remove('open');
      activeCommentVideoId = null;
    }

    function postComment() {
      const input = document.getElementById('newComment');
      const val = input.value.trim();
      if (!val || !activeCommentVideoId) return;

      // Remove 'no comments yet' if it exists
      const list = document.getElementById('commentsList');
      if (list.innerHTML.includes('No comments yet')) list.innerHTML = '';

      const box = document.createElement('div');
      box.className = 'comment-item';
      box.innerHTML = `
        <div class="comment-avatar" style="background:#fff; color:#000;">Y</div>
        <div>
          <div class="comment-user">You</div>
          <div class="comment-text">${val}</div>
        </div>
      `;

      list.prepend(box);
      input.value = '';

      // Update Database
      fetch('ajax/video_interaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=comment&video_id=${activeCommentVideoId}&comment_text=${encodeURIComponent(val)}`
      })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            const countSpan = document.getElementById('comment-count-' + activeCommentVideoId);
            if (countSpan) countSpan.innerText = Number(data.counts.comments).toLocaleString();
          }
        });
    }
  </script>
</body>

</html>