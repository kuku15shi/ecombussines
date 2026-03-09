<?php
// bot_engine.php - Core Logic for WhatsApp Commerce Bot

class WhatsAppBot {
    private $pdo;
    private $from;
    private $text;
    private $session;

    public function __construct($pdo, $from, $text) {
        $this->pdo = $pdo;
        $this->from = $from;
        $this->text = strtolower(trim($text));
        $this->loadSession();
    }

    private function loadSession() {
        $stmt = $this->pdo->prepare("SELECT * FROM bot_sessions WHERE phone = ?");
        $stmt->execute([$this->from]);
        $this->session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->session) {
            $this->pdo->prepare("INSERT INTO bot_sessions (phone, step, data) VALUES (?, 'start', '{}')")->execute([$this->from]);
            $this->session = ['phone' => $this->from, 'step' => 'start', 'data' => []];
        } else {
            $this->session['data'] = json_decode($this->session['data'], true) ?: [];
        }
    }

    private function saveSession($step, $data = null) {
        $this->session['step'] = $step;
        if ($data !== null) $this->session['data'] = $data;
        $json = json_encode($this->session['data']);
        $stmt = $this->pdo->prepare("UPDATE bot_sessions SET step = ?, data = ? WHERE phone = ?");
        $stmt->execute([$step, $json, $this->from]);
    }

    public function process() {
        // Check if bot is enabled globally
        $config = $this->pdo->query("SELECT config_key, config_value FROM whatsapp_config WHERE config_key IN ('bot_enabled', 'bot_welcome_msg', 'bot_fallback_msg')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $botEnabled = ($config['bot_enabled'] ?? '1') === '1';
        if (!$botEnabled) return null;

        $this->welcomeMsg = $config['bot_welcome_msg'] ?? "👋 Hello! Welcome to *" . SITE_NAME . "*\nHow can I help you today?";
        $this->fallbackMsg = $config['bot_fallback_msg'] ?? "Sorry, I didn't understand that. Reply MENU to see options.";

        // Reset bot on "hi", "hello", "start", "menu"
        if (in_array($this->text, ['hi', 'hello', 'start', 'menu', 'reset'])) {
            $this->saveSession('start', []);
            return $this->mainMenu();
        }

        // Help Desk Menu
        if ($this->text === 'help' || $this->text === 'options') {
            return $this->helpMenu();
        }

        switch ($this->session['step']) {
            case 'start': return $this->handleMainMenu();
            case 'track_order_id': return $this->handleTrackOrderId();
            case 'category_list': return $this->handleCategorySelection();
            case 'product_list': return $this->handleProductSelection();
            case 'product_details': return $this->handleProductAction();
            case 'search_query': return $this->handleSearch();
            
            // Ordering Flow
            case 'order_name': return $this->handleOrderName();
            case 'order_address': return $this->handleOrderAddress();
            case 'order_payment': return $this->handleOrderPayment();
            case 'order_confirm': return $this->handleOrderConfirm();
            case 'select_lang': return $this->handleLangSelection();
            
            default:
                return $this->aiFallback();
        }
    }

    /**
     * Handle Button Replies from Interactive Messages
     */
    public function handleButton($id) {
        // Main menu button handlers
        if ($id === 'menu_track') {
            $this->saveSession('track_order_id');
            return "📦 Please enter your *Order ID*\n\nExample: _ORD12345_\n\nYou can find your Order ID in your confirmation email or SMS.";
        }
        if ($id === 'menu_support') {
            return "👨‍💻 *Customer Support*\n\nPlease describe your issue and our team will reply shortly.\n\nOr call us at our support number.\n\nType MENU to return to the main menu.";
        }
        if ($id === 'menu_browse') {
            $cats = $this->pdo->query("SELECT id, name FROM categories WHERE is_active = 1 LIMIT 8")->fetchAll();
            $map = [];
            $msg = "🛒 *Browse Our Collections*\n\nReply with a number to explore:\n";
            foreach($cats as $i => $c) {
                $idx = $i + 1;
                $msg .= "\n$idx. " . $c['name'];
                $map[$idx] = $c['id'];
            }
            $data = $this->session['data'];
            $data['map'] = $map;
            $this->saveSession('category_list', $data);
            return $msg . "\n\nOr type any product name to search.";
        }

        if ($id === 'support') return $this->handleMainMenu('4');
        
        if (strpos($id, 'track_') === 0) {
            $orderId = substr($id, 6);
            return trackOrderOnWhatsApp($orderId, $this->from);
        }
        
        if (strpos($id, 'del_yes_') === 0) {
            $orderId = substr($id, 8);
            $this->pdo->prepare("UPDATE orders SET whatsapp_confirmed = 'confirmed' WHERE id = ?")->execute([$orderId]);
            return "✅ Thank you! Your delivery is confirmed for today. Our agent will reach out soon.";
        }
        
        if (strpos($id, 'del_no_') === 0) {
            $orderId = substr($id, 7);
            $this->pdo->prepare("UPDATE orders SET whatsapp_confirmed = 'reschedule' WHERE id = ?")->execute([$orderId]);
            return "📅 No problem. We have marked your request for rescheduling. Our team will contact you for a new date.";
        }
        
        if (strpos($id, 'del_cancel_') === 0) {
            $orderId = substr($id, 11);
            $this->pdo->prepare("UPDATE orders SET whatsapp_confirmed = 'cancelled', order_status = 'cancelled' WHERE id = ?")->execute([$orderId]);
            return "❌ Order cancelled as per your request. If this was a mistake, please contact support.";
        }

        if (strpos($id, 'cod_confirm_') === 0) {
            $orderId = substr($id, 12);
            $this->pdo->prepare("UPDATE orders SET whatsapp_confirmed = 'confirmed' WHERE id = ?")->execute([$orderId]);
            return "✅ Thank you for confirming! Your COD order is now being processed and will be shipped soon.";
        }

        if (strpos($id, 'cod_cancel_') === 0) {
            $orderId = substr($id, 11);
            $this->pdo->prepare("UPDATE orders SET whatsapp_confirmed = 'cancelled', order_status = 'cancelled' WHERE id = ?")->execute([$orderId]);
            return "❌ Your order has been cancelled. Hope to see you again!";
        }

        return $this->process();
    }

    private function helpMenu() {
        return "🛠️ *Customer Support Menu*\n\n" .
               "1️⃣ Track Order\n" .
               "2️⃣ Cancel Order\n" .
               "3️⃣ Return Product\n" .
               "4️⃣ Talk to Support Agent\n\n" .
               "Reply with the number or type MENU.";
    }


    private function getT($en, $ml = '', $hi = '') {
        $lang = $this->session['data']['lang'] ?? 'en';
        if ($lang == 'ml' && $ml) return $ml;
        if ($lang == 'hi' && $hi) return $hi;
        return $en;
    }

    private function mainMenu() {
        $this->saveSession('start');
        
        // Dynamic Categories for text body
        $cats = $this->pdo->query("SELECT id, name FROM categories WHERE is_active = 1 LIMIT 5")->fetchAll();
        $catList = "";
        $map = [];
        if($cats) {
            $catList = "\n\n🛍️ *Shop by Category:*\n";
            foreach($cats as $i => $c) {
                $idx = $i + 5;
                $catList .= "$idx. " . $c['name'] . "\n";
                $map[$idx] = $c['id'];
            }
            $data = $this->session['data'];
            $data['map'] = $map;
            $this->saveSession('start', $data);
        }

        $bodyText = "👋 *Welcome to " . SITE_NAME . "!*\n\nHow can I help you today?\n\n" .
                    "📦 Track Order\n🛍️ Browse Products\n💬 Talk to Support" .
                    $catList . "\nOr type a product name to search.";

        // Return interactive button message
        return [
            '_type' => 'interactive',
            '_payload' => [
                "type" => "button",
                "body" => ["text" => $bodyText],
                "footer" => ["text" => SITE_NAME . " • Reply anytime"],
                "action" => [
                    "buttons" => [
                        ["type" => "reply", "reply" => ["id" => "menu_track", "title" => "📦 Track Order"]],
                        ["type" => "reply", "reply" => ["id" => "menu_browse", "title" => "🛍️ Browse Shop"]],
                        ["type" => "reply", "reply" => ["id" => "menu_support", "title" => "💬 Support"]]
                    ]
                ]
            ]
        ];
    }

    private function handleLangSelection() {
        $l = 'en';
        if ($this->text == '2') $l = 'ml';
        if ($this->text == '3') $l = 'hi';
        $data = $this->session['data'];
        $data['lang'] = $l;
        $this->saveSession('start', $data);
        return $this->mainMenu();
    }

    private function aiFallback() {
        // 1. Check FAQ Table
        $stmt = $this->pdo->prepare("SELECT answer FROM bot_faqs WHERE is_active = 1 AND (FIND_IN_SET(?, keywords) OR keywords LIKE ?) LIMIT 1");
        $stmt->execute([$this->text, '%' . $this->text . '%']);
        $faq = $stmt->fetchColumn();
        if ($faq) return $faq;

        // 2. Simple Product Search
        $stmt = $this->pdo->prepare("SELECT id, name, price FROM products WHERE name LIKE ? AND is_active = 1 LIMIT 1");
        $stmt->execute(['%' . $this->text . '%']);
        $p = $stmt->fetch();

        if ($p) {
            return $this->getT("We have ", "", "") . "*" . $p['name'] . "* " . $this->getT("for ", "", "") . formatPrice($p['price']) . ".\n\n" .
                   $this->getT("Reply PRODUCT ", "", "") . $p['id'] . $this->getT(" to see details or BUY ", "", "") . strtoupper($p['name']) . $this->getT(" to order.", "", "");
        }

        return $this->fallbackMsg;
    }

    private function handleMainMenu($forcedText = null) {
        $input = $forcedText ?: $this->text;
        $map = $this->session['data']['map'] ?? [];

        if (in_array($input, ['1', '2'])) {
            $this->saveSession('track_order_id');
            return "📦 Please enter your Order ID\nExample: ORD12345";
        } elseif ($input == '3') {
            return $this->myOrders();
        } elseif ($input == '4') {
            return "👨‍💻 *Customer Support*\n\nPlease describe your issue.\nOur team will reply shortly.\n\nType MENU to return.";
        } elseif (isset($map[$input])) {
            // Redirect to category flow
            $this->saveSession('category_list', $this->session['data']);
            return $this->handleCategorySelection($input);
        }

        // Search logic if not a number
        if (!is_numeric($input) && strlen($input) > 2) {
            return $this->handleSearch();
        }

        return $this->mainMenu();
    }

    private function handleTrackOrderId() {
        $orderId = strtoupper($this->text);
        $result = trackOrderOnWhatsApp($orderId, $this->from);
        
        if (strpos($result, 'Order Details') !== false) {
             // If found, we can reset to start or keep it here
             $this->saveSession('start');
             return $result . "\n\nType MENU for more options.";
        } else {
             // Not found, maybe they want to try again or go back
             return "❌ " . $result . "\n\nPlease check your Order ID and try again, or type MENU.";
        }
    }


    private function handleCategorySelection($forcedChoice = null) {
        $choice = $forcedChoice ?: $this->text;
        $map = $this->session['data']['map'] ?? [];
        if (isset($map[$choice])) {
            $catId = $map[$choice];
            $stmt = $this->pdo->prepare("SELECT id, name, price FROM products WHERE category_id = ? AND is_active = 1 LIMIT 10");
            $stmt->execute([$catId]);
            $prods = $stmt->fetchAll();
            
            if (!$prods) return "No products in this category. Reply MENU for main menu.";

            $msg = "🛒 *Products List*\n";
            $pMap = [];
            foreach ($prods as $i => $p) {
                $idx = $i + 1;
                $msg .= "\n" . ($idx) . "️⃣ " . $p['name'] . " – " . formatPrice($p['price']);
                $pMap[$idx] = $p['id'];
            }
            $this->saveSession('product_list', ['pMap' => $pMap]);
            return $msg . "\n\nReply with product number to see details.";
        }
        return "Invalid selection. Please reply with a number from the list.";
    }

    private function handleProductSelection() {
        $pMap = $this->session['data']['pMap'] ?? [];
        if (isset($pMap[$this->text])) {
            $pId = $pMap[$this->text];
            $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$pId]);
            $p = $stmt->fetch();

            if ($p) {
                $msg = "✨ *" . $p['name'] . "*\n\n" .
                       "💰 Price: " . formatPrice($p['price']) . "\n" .
                       "⚖️ Weight: " . ($p['weight'] ? $p['weight'] . 'g' : 'N/A') . "\n" .
                       "📦 Stock: " . ($p['stock'] > 0 ? 'Available' : 'Out of Stock') . "\n\n" .
                       "--- Description ---\n" . ($p['short_description'] ?: 'No description available') . "\n\n" .
                       "1️⃣ Buy Now\n" .
                       "2️⃣ Back to Categories";
                $this->saveSession('product_details', ['pId' => $pId]);
                return $msg;
            }
        }
        return "Invalid selection.";
    }

    private function handleProductAction() {
        if ($this->text == '1') {
            $this->saveSession('order_name', ['pId' => $this->session['data']['pId']]);
            return "👤 Step 1/3: Please enter your *Full Name* for the delivery:";
        }
        return $this->mainMenu();
    }

    private function handleSearch() {
        $stmt = $this->pdo->prepare("SELECT id, name, price FROM products WHERE name LIKE ? AND is_active = 1 LIMIT 10");
        $stmt->execute(['%' . $this->text . '%']);
        $prods = $stmt->fetchAll();

        if ($prods) {
            $msg = "🔍 *Search Results for '" . $this->text . "'*\n";
            $pMap = [];
            foreach ($prods as $i => $p) {
                $idx = $i + 1;
                $msg .= "\n" . ($idx) . "️⃣ " . $p['name'] . " – " . formatPrice($p['price']);
                $pMap[$idx] = $p['id'];
            }
            $this->saveSession('product_list', ['pMap' => $pMap]);
            return $msg . "\n\nReply with product number to see details.";
        }
        return "❌ No products found matching '" . $this->text . "'. Try another keyword or reply MENU.";
    }

    private function myOrders() {
        $stmt = $this->pdo->prepare("SELECT order_number, total, order_status FROM orders WHERE phone LIKE ? ORDER BY created_at DESC LIMIT 5");
        $phoneParam = "%" . substr($this->from, -10) . "%";
        $stmt->execute([$phoneParam]);
        $orders = $stmt->fetchAll();

        if ($orders) {
            $msg = "📋 *Your Recent Orders*\n";
            foreach($orders as $o) {
                $msg .= "\n#" . $o['order_number'] . " – " . formatPrice($o['total']) . " – *" . ucfirst($o['order_status']) . "*";
            }
            return $msg . "\n\nReply MENU for main menu.";
        }
        return "You haven't placed any orders yet. Reply MENU to browse our catalog!";
    }

    // --- Ordering Flow ---

    private function handleOrderName() {
        $data = $this->session['data'];
        $data['name'] = $this->text;
        $this->saveSession('order_address', $data);
        return "📍 Step 2/3: Enter your *Full Address* (including City and Pincode):";
    }

    private function handleOrderAddress() {
        $data = $this->session['data'];
        $data['address'] = $this->text;
        $this->saveSession('order_payment', $data);
        return "💳 Step 3/3: Choose Payment Method:\n\n1️⃣ Cash on Delivery (COD)\n2️⃣ Online Payment";
    }

    private function handleOrderPayment() {
        $data = $this->session['data'];
        if ($this->text == '1') $data['payment'] = 'cod';
        elseif ($this->text == '2') $data['payment'] = 'online';
        else return "Invalid choice. Reply 1 for COD or 2 for Online Payment.";

        // Prepare Summary
        $pId = $data['pId'];
        $stmt = $this->pdo->prepare("SELECT name, price FROM products WHERE id = ?");
        $stmt->execute([$pId]);
        $p = $stmt->fetch();

        $shipping = SHIPPING_CHARGE;
        $codFee = ($data['payment'] == 'cod') ? COD_CHARGE : 0;
        $total = $p['price'] + $shipping + $codFee;
        
        $data['total'] = $total;
        $data['pName'] = $p['name'];
        $data['pPrice'] = $p['price'];
        $data['shipping'] = $shipping;
        $data['cod_fee'] = $codFee;

        $msg = "📝 *Order Summary*\n\n" .
               "Product: " . $p['name'] . "\n" .
               "Price: " . formatPrice($p['price']) . "\n" .
               "Shipping: " . formatPrice($shipping) . "\n" .
               ($codFee > 0 ? "COD Fee: " . formatPrice($codFee) . "\n" : "") .
               "Total: *" . formatPrice($total) . "*\n\n" .
               "Method: " . ($data['payment'] == 'cod' ? 'Cash on Delivery' : 'Online Payment') . "\n\n" .
               "1️⃣ *Confirm Order*\n2️⃣ Cancel";
        
        $this->saveSession('order_confirm', $data);
        return $msg;
    }

    private function handleOrderConfirm() {
        if ($this->text == '1' || $this->text == 'confirm') {
            $data = $this->session['data'];
            $orderNum = 'WA' . strtoupper(substr(md5(uniqid()), 0, 7));
            $initialStatus = ($data['payment'] == 'cod') ? 'pending' : 'pending'; // Start as pending
            
            try {
                $this->pdo->beginTransaction();
                
                // Simplified order insertion
                $stmt = $this->pdo->prepare("INSERT INTO orders (order_number, name, phone, address, subtotal, shipping, cod_fee, total, payment_method, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$orderNum, $data['name'], $this->from, $data['address'], $data['pPrice'], $data['shipping'], $data['cod_fee'], $data['total'], $data['payment'], $initialStatus]);
                $orderId = $this->pdo->lastInsertId();

                // Order Items
                $stmtItem = $this->pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total) VALUES (?, ?, ?, 1, ?, ?)");
                $stmtItem->execute([$orderId, $data['pId'], $data['pName'], $data['pPrice'], $data['pPrice']]);

                $this->pdo->commit();

                $this->saveSession('start', []); // Clear session

                if ($data['payment'] == 'cod') {
                    return "✅ *Success!* Your order #$orderNum has been placed.\nOur team will contact you soon for verification. Delivery in 2-4 days.";
                } else {
                    $payLink = SITE_URL . "/pay/" . $orderNum;
                    return "✅ Order #$orderNum created!\n\n💳 *Pay Securely*: $payLink\n\nYour order will be processed after payment confirmation.";
                }
            } catch (Exception $e) {
                try { $this->pdo->rollBack(); } catch(Exception $rb) {}
                error_log("WhatsApp Order Error: " . $e->getMessage() . " | Data: " . json_encode($data));
                return "❌ Sorry, an error occurred while placing your order.\n\nError: " . $e->getMessage() . "\n\nPlease contact support or visit our website to place your order.";
            }
        }
        $this->saveSession('start', []);
        return "❌ Order cancelled. Reply MENU to start again.";
    }
}
