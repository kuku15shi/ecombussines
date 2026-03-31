<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

// Fetch the currently active live stream
$liveStream = null;
try {
  $stmt = $pdo->query("SELECT ls.*, p.name as product_name, p.price, p.discount_percent, p.images, p.slug as product_slug 
                         FROM live_streams ls 
                         LEFT JOIN products p ON ls.pinned_product_id = p.id 
                         WHERE ls.status = 'live' 
                         ORDER BY ls.start_time DESC LIMIT 1");
  $liveStream = $stmt->fetch();
} catch (Exception $e) {
  // Handling missing tables
}

// Fallback to Demo Live Stream UI if no stream is active
if (!$liveStream) {
  $liveStream = [
    'id' => 1,
    'title' => 'Mega Electronics Sale LIVE 🎉',
    'viewers_count' => 12450,
    'product_name' => 'Classic Linen Green Shirt',
    'price' => '49',
    'discount_percent' => '0',
    'images' => 'demo_shirt.jpg',
    'product_slug' => 'classic-linen-green-shirt'
  ];
}

$currentUser = getCurrentUser($pdo);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Live Shopping - MIZ MAX</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body,
    html {
      margin: 0;
      padding: 0;
      height: 100vh;
      background: #000;
      color: #fff;
      font-family: 'Inter', sans-serif;
      overflow: hidden;
    }

    .live-container {
      position: relative;
      height: 100vh;
      width: 100vw;
    }

    /* Simulate Live Video Feed */
    .live-video {
      width: 100%;
      height: 100%;
      object-fit: cover;
      /* Using a placeholder background gradient for demo purposes */
      background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
    }

    /* Top Overlay */
    .top-overlay {
      position: absolute;
      top: 15px;
      left: 15px;
      right: 15px;
      display: flex;
      justify-content: space-between;
      align-items: start;
      z-index: 10;
    }

    .back-btn {
      color: #fff;
      font-size: 1.5rem;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.8);
    }

    .status-badges {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .viewers-badge {
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      padding: 4px 10px;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 4px;
      text-shadow: none;
    }

    .live-badge {
      background: #ff0000;
      padding: 4px 10px;
      border-radius: 4px;
      font-weight: 700;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
      box-shadow: 0 2px 4px rgba(255, 0, 0, 0.4);
      text-transform: uppercase;
    }

    /* Pinned Product Layer Container (Bottom) */
    .product-showcase {
      position: absolute;
      bottom: 85px;
      left: 50%;
      transform: translateX(-50%);
      width: 85%;
      background: #ffffff;
      border-radius: 12px;
      padding: 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      z-index: 20;
      color: #000;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .ps-img {
      width: 60px;
      height: 60px;
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
      font-size: 0.85rem;
      line-height: 1.2;
      margin-bottom: 4px;
      color: #333;
    }

    .ps-price {
      font-weight: 800;
      font-size: 0.95rem;
      color: #000;
    }

    .ps-btn {
      background: #000;
      color: #fff;
      border: none;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.7rem;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Right Sidebar Controls */
    .live-sidebar {
      position: absolute;
      right: 15px;
      bottom: 180px;
      z-index: 10;
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    .ls-btn {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(8px);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      cursor: pointer;
      transition: background 0.2s;
    }

    .ls-btn:active {
      background: rgba(0, 0, 0, 0.6);
    }

    /* Live Chat Popups */
    .chat-container {
      position: absolute;
      bottom: 170px;
      left: 15px;
      width: 60%;
      height: 200px;
      z-index: 5;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      overflow: hidden;
      mask-image: linear-gradient(to top, black 50%, transparent 100%);
      -webkit-mask-image: linear-gradient(to top, black 50%, transparent 100%);
    }

    .chat-messages {
      display: flex;
      flex-direction: column;
      gap: 8px;
      justify-content: flex-end;
      padding-bottom: 5px;
    }

    .chat-bubble {
      background: rgba(255, 255, 255, 0.9);
      color: #000;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      line-height: 1.3;
      align-self: flex-start;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
      animation: popIn 0.3s ease-out forwards;
      max-width: 100%;
      word-wrap: break-word;
      font-weight: 500;
    }

    .chat-user {
      font-weight: 800;
      margin-right: 4px;
    }

    @keyframes popIn {
      0% {
        opacity: 0;
        transform: translateY(10px) scale(0.95);
      }

      100% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    /* Input Field */
    .chat-input-wrapper {
      position: absolute;
      bottom: 20px;
      left: 15px;
      right: 15px;
      z-index: 10;
    }

    .chat-pill {
      background: rgba(0, 0, 0, 0.3);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 30px;
      display: flex;
      align-items: center;
      padding: 4px;
      padding-left: 15px;
    }

    .chat-input {
      flex: 1;
      background: transparent;
      border: none;
      color: #fff;
      outline: none;
      font-size: 0.9rem;
    }

    .chat-input::placeholder {
      color: rgba(255, 255, 255, 0.6);
    }

    .send-icon-btn {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: transparent;
      color: #fff;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      cursor: pointer;
    }

    /* PC Desktop View: Simulate mobile phone screen layout */
    @media (min-width: 768px) {
      body {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #111;
      }

      .live-container {
        width: 400px;
        height: 90vh;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);
        overflow: hidden;
      }
    }
  </style>
</head>

<body>

  <div class="live-container">

    <video class="live-video" src="https://www.w3schools.com/html/mov_bbb.mp4" autoplay loop muted playsinline></video>

    <div class="top-overlay">
      <a href="index.php" class="back-btn"><i class="bi bi-chevron-left"></i></a>
      <div class="status-badges">
        <div class="viewers-badge"><i class="bi bi-eye"></i> <?= number_format($liveStream['viewers_count']) ?></div>
        <div class="live-badge">LIVE</div>
      </div>
    </div>

    <div class="live-sidebar">
      <button class="ls-btn"><i class="bi bi-box-arrow-up"></i></button>
      <button class="ls-btn"><i class="bi bi-volume-up"></i></button>
      <button class="ls-btn"><i class="bi bi-cart3"></i></button>
    </div>

    <div class="chat-container">
      <div class="chat-messages" id="chatBox">
        <!-- Simulated Chat -->
        <div class="chat-bubble"><span class="chat-user">Rahul89</span> Is it waterproof?</div>
        <div class="chat-bubble"><span class="chat-user">Jane Doe</span> Lovely Collection ❤️</div>
        <div class="chat-bubble"><span class="chat-user">TechGuru</span> The price is amazing rn</div>
      </div>
    </div>

    <?php if ($liveStream['product_name']):
      $product_image = 'demo.jpg';
      if (!empty($liveStream['images'])) {
        $imgs = json_decode($liveStream['images'], true) ?? explode(',', $liveStream['images']);
        $product_image = is_array($imgs) && count($imgs) > 0 ? $imgs[0] : $liveStream['images'];
      }
      $offer_price = $liveStream['price'];
      if (isset($liveStream['discount_percent']) && $liveStream['discount_percent'] > 0) {
        $offer_price = $liveStream['price'] - ($liveStream['price'] * $liveStream['discount_percent'] / 100);
      }
      ?>
      <div class="product-showcase">
        <div style="display:flex; gap:12px; align-items:center;">
          <img src="<?= SITE_URL . '/uploads/' . $product_image ?>"
            onerror="this.src='https://placehold.co/100x100?text=Product'" class="ps-img">
          <div class="ps-info">
            <div class="ps-title"><?= htmlspecialchars($liveStream['product_name']) ?></div>
            <div class="ps-price"><?= formatPrice($offer_price) ?></div>
          </div>
        </div>
        <button class="ps-btn" onclick="window.location.href='product?slug=<?= $liveStream['product_slug'] ?>'">Add to
          Cart</button>
      </div>
    <?php endif; ?>

    <div class="chat-input-wrapper">
      <div class="chat-pill">
        <input type="text" class="chat-input" id="chatInput" placeholder="Comment">
        <button class="send-icon-btn" onclick="sendChat()"><i class="bi bi-send"></i></button>
      </div>
    </div>

  </div>

  <script>
    function spawnHeart() {
      const heart = document.createElement('i');
      heart.className = 'bi bi-suit-heart-fill floating-heart';
      // Random side displacement
      const drift = Math.random() * 40 - 20;
      heart.style.left = `calc(100% - 40px + ${drift}px)`;
      document.body.appendChild(heart);
      setTimeout(() => heart.remove(), 2000);
    }

    function sendChat() {
      const input = document.getElementById('chatInput');
      const msg = input.value.trim();
      if (msg) {
        const chatBox = document.getElementById('chatBox');
        const newMsg = document.createElement('div');
        newMsg.className = 'chat-bubble';
        newMsg.innerHTML = `<span class="chat-user">You</span> ${msg}`;
        chatBox.appendChild(newMsg);
        input.value = '';

        // Auto-scroll logic: keep a max number of nodes for performance
        if (chatBox.childNodes.length > 20) { chatBox.removeChild(chatBox.firstChild); }
      }
    }

    // Simulate incoming chat messages
    const demoChats = ["Wow!", "Can you show the back?", "Just bought it 🔥", "Is COD available?", "Nice explanation", "Lovely Collection ❤️"];
    setInterval(() => {
      if (Math.random() > 0.4) {
        const chatBox = document.getElementById('chatBox');
        const newMsg = document.createElement('div');
        newMsg.className = 'chat-bubble';
        const user = "User" + Math.floor(Math.random() * 999);
        const text = demoChats[Math.floor(Math.random() * demoChats.length)];
        newMsg.innerHTML = `<span class="chat-user">${user}</span> ${text}`;
        chatBox.appendChild(newMsg);
        if (chatBox.childNodes.length > 20) { chatBox.removeChild(chatBox.firstChild); }
      }
    }, 2300);
  </script>

</body>

</html>