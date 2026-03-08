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
        $botEnabled = ($this->pdo->query("SELECT config_value FROM whatsapp_config WHERE config_key = 'bot_enabled'")->fetchColumn() ?: '1') === '1';
        if (!$botEnabled) return null;

        // Reset bot on "hi", "hello", "start", "menu"
        if (in_array($this->text, ['hi', 'hello', 'start', 'menu', 'reset'])) {
            $this->saveSession('start', []);
            return $this->mainMenu();
        }

        switch ($this->session['step']) {
            case 'start': return $this->handleMainMenu();
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

    private function getT($en, $ml = '', $hi = '') {
        $lang = $this->session['data']['lang'] ?? 'en';
        if ($lang == 'ml' && $ml) return $ml;
        if ($lang == 'hi' && $hi) return $hi;
        return $en;
    }

    private function mainMenu() {
        $this->saveSession('start');
        $lang = $this->session['data']['lang'] ?? null;
        if (!$lang) {
            $this->saveSession('select_lang');
            return "Choose Language / ഭാഷ തിരഞ്ഞെടുക്കുക / भाषा चुनें:\n\n1️⃣ English\n2️⃣ Malayalam (മലയാളം)\n3️⃣ Hindi (हिंदी)";
        }

        return $this->getT("Hello 👋 Welcome to ", "നമസ്കാരം 👋 ", "नमस्ते 👋 ") . SITE_NAME . "!\n\n" .
               $this->getT("How can I help you today?", "ഇന്ന് ഞാൻ എങ്ങനെ സഹായിക്കണം?", "मैं आज आपकी कैसे सहायता कर सकता हूँ?") . "\n\n" .
               "1️⃣ " . $this->getT("View Products", "ഉൽപ്പന്നങ്ങൾ കാണുക", "उत्पाद देखें") . "\n" .
               "2️⃣ " . $this->getT("Search Product", "തിരയുക", "उत्पाद खोजें") . "\n" .
               "3️⃣ " . $this->getT("My Orders", "എന്റെ ഓർഡറുകൾ", "मेरे आदेश") . "\n" .
               "4️⃣ " . $this->getT("Track Order", "ഓർഡർ ട്രാക്ക് ചെയ്യുക", "ऑर्डर ट्रैक करें") . "\n" .
               "5️⃣ " . $this->getT("Talk to Support", "സഹായത്തിനായി", "बात करें") . "\n\n" .
               $this->getT("Reply with the number.", "നമ്പർ ടൈപ്പ് ചെയ്യുക.", "नंबर के साथ उत्तर दें।");
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
        // Simple "AI" - see if text matches any product names
        $stmt = $this->pdo->prepare("SELECT id, name, price FROM products WHERE name LIKE ? AND is_active = 1 LIMIT 1");
        $stmt->execute(['%' . $this->text . '%']);
        $p = $stmt->fetch();

        if ($p) {
            return $this->getT("We have ", "", "") . "*" . $p['name'] . "* " . $this->getT("for ", "", "") . formatPrice($p['price']) . ".\n\n" .
                   $this->getT("Reply PRODUCT ", "", "") . $p['id'] . $this->getT(" to see details or BUY ", "", "") . strtoupper($p['name']) . $this->getT(" to order.", "", "");
        }

        return $this->getT("Sorry, I didn't understand that. Reply MENU to see options.", "ക്ഷമിക്കണം, എനിക്ക് മനസ്സിലായില്ല. MENU എന്ന് ടൈപ്പ് ചെയ്യുക.", "क्षमा करें, मुझे समझ नहीं आया। विकल्प देखने के लिए MENU टाइप करें।");
    }

    private function handleMainMenu() {
        if ($this->text == '1') {
            $stmt = $this->pdo->query("SELECT * FROM categories WHERE is_active = 1 LIMIT 10");
            $cats = $stmt->fetchAll();
            $msg = "📂 *Our Product Categories*\n";
            $map = [];
            foreach ($cats as $i => $cat) {
                $idx = $i + 1;
                $msg .= "\n" . ($idx) . "️⃣ " . $cat['name'];
                $map[$idx] = $cat['id'];
            }
            $this->saveSession('category_list', ['map' => $map]);
            return $msg . "\n\nReply with category number.";
        } elseif ($this->text == '2') {
            $this->saveSession('search_query');
            return "🔍 Please enter the product name you are looking for:";
        } elseif ($this->text == '3') {
            return $this->myOrders();
        } elseif ($this->text == '4') {
            $this->saveSession('start'); // Or specialized step
            return "📍 To track your order, please type:\n*STATUS <OrderNumber>*\nExample: STATUS LUXE1234";
        } elseif ($this->text == '5') {
            return "💬 *Support Team*\n\n📞 +91 11111 22222\n📧 support@example.com\n\nReply MENU for main menu.";
        }
        return $this->mainMenu();
    }

    private function handleCategorySelection() {
        $map = $this->session['data']['map'] ?? [];
        if (isset($map[$this->text])) {
            $catId = $map[$this->text];
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
                $this->pdo->rollBack();
                return "❌ Sorry, an error occurred while placing your order. Please try again or contact support.";
            }
        }
        $this->saveSession('start', []);
        return "❌ Order cancelled. Reply MENU to start again.";
    }
}
