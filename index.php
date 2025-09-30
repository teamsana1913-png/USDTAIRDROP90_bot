<?php

// Load Composer dependencies
require_once __DIR__ . '/vendor/autoload.php';

// --- CONFIGURATION ---
$bot_api_key    = getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_TELEGRAM_BOT_TOKEN';
$bot_username   = getenv('TELEGRAM_BOT_USERNAME') ?: 'YOURBOT'; 
$users_file     = __DIR__ . '/users.json';

// --- DATABASE FUNCTIONS ---
function getUsersData() {
    global $users_file;
    if (!file_exists($users_file)) {
        return [];
    }
    $data = file_get_contents($users_file);
    return json_decode($data, true) ?: [];
}

function saveUserData($users) {
    global $users_file;
    file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT));
}

function getUserState($chat_id) {
    $users = getUsersData();
    return $users[$chat_id]['state'] ?? null;
}

function setUserState($chat_id, $state) {
    $users = getUsersData();
    if (isset($users[$chat_id])) {
        $users[$chat_id]['state'] = $state;
        saveUserData($users);
    }
}

// --- BOT LOGIC ---
try {
    $telegram = new Longman\TelegramBot\Telegram($bot_api_key, $bot_username);

    // Get the input from Telegram
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);

    if (!$update) {
        // Not a Telegram update
        exit;
    }

    // Extract main components
    $chat_id = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
    $message_text = $update['message']['text'] ?? null;
    $callback_data = $update['callback_query']['data'] ?? null;
    $username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? 'user';

    if (!$chat_id) {
        exit;
    }

    // Initialize user if not exists
    $users = getUsersData();
    if (!isset($users[$chat_id])) {
        $unique_code = base64_encode($chat_id);
        $users[$chat_id] = [
            'id' => $chat_id,
            'username' => $username,
            'wallet' => '0x687671b548f5979a52972c05f1759b964805e521',
            'balance' => 0.0,
            'referral_code' => $unique_code,
            'referred_by' => null,
            'total_invites' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'state' => null, // To track multi-step actions
        ];
        saveUserData($users);
    }

    // Get user's unique referral link
    $user_referral_link = "https://t.me/{$bot_username}?start={$users[$chat_id]['referral_code']}";

    // Handle incoming messages and callbacks
    if ($message_text) {
        // Handle /start command
        if (strpos($message_text, '/start') === 0) {
            // Check for referral code
            $parts = explode(' ', $message_text);
            if (isset($parts[1]) && $users[$chat_id]['referred_by'] === null) {
                $ref_code = $parts[1];
                $inviter_id = base64_decode($ref_code);
                if (isset($users[$inviter_id]) && $inviter_id != $chat_id) {
                    $users[$chat_id]['referred_by'] = $inviter_id;
                    $users[$inviter_id]['total_invites']++;
                    $users[$inviter_id]['balance'] += 10.0;
                    saveUserData($users);
                }
            }

            $text = "👋 Welcome!\nJoin our channels to unlock rewards & updates 🚀\n\n👉 @USDTAIRDROPchat\n👉 @OFFLICALUSDTAIRDROP\n👉 @OFFICALUPDATEUSDT\n👉 @NEWPROJECTOFUSDT\n\n✅ After joining, tap Continue to start!";
            Longman\TelegramBot\Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => $text,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [['text' => '✅ Continue', 'callback_data' => 'continue']]
                    ]
                ]
            ]);
        }
        // Handle keyboard button presses
        elseif ($message_text === '🥇 💰 Balance') {
            $balance = $users[$chat_id]['balance'];
            $wallet = $users[$chat_id]['wallet'];
            $text = "💎 My Balance\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n💰 USDT: {$balance} ≈ {$balance}$\n\n💳 Wallet: {$wallet}\n\n👥 Referral Bonus: 🎁 10 USDT (~$10) per friend\n\n🔗 Your Invite Link\n{$user_referral_link}\n\n🔥 The more friends you invite, the more USDT you earn!\n✨ Start sharing now and grow your rewards";
            Longman\TelegramBot\Request::sendMessage(['chat_id' => $chat_id, 'text' => $text]);
        }
        elseif ($message_text === '🥈 🎊 Bonus') {
            $bonus = rand(1000, 1500) / 100; // Random bonus between 10.00 and 15.00
            $users[$chat_id]['balance'] += $bonus;
            saveUserData($users);
            $text = "🎊 Bonus\n\nRandom 10$ se 15$ milega\n\n🎉 Congratulations! You just received {$bonus} USDT 💸\n💎 Keep claiming daily and watch your rewards grow! 🚀";
            Longman\TelegramBot\Request::sendMessage(['chat_id' => $chat_id, 'text' => $text]);
        }
        elseif ($message_text === '🥉 💑 Referral') {
            $invites = $users[$chat_id]['total_invites'];
            $text = "💸 Get 10 USDT (\$10) for Every Friend You Invite!\n\n🎯 How it works: Share your link → Friend joins → You earn \$10 instantly!\n\n✨ The more friends, the bigger your rewards!\n\n📊 Friends Invited: {$invites}\n\n🔗 Your Referral Link: {$user_referral_link}\n\n⚡ Don’t wait! Start sharing now and watch your balance grow! 🚀💎 Instantly.";
            Longman\TelegramBot\Request::sendMessage(['chat_id' => $chat_id, 'text' => $text]);
        }
        elseif ($message_text === '4️⃣ 📤 Withdraw') {
            setUserState($chat_id, 'awaiting_withdraw_amount');
            $text = "❗ Minimum Withdraw Is 100 USDT\n\n💳 Enter the amount you want to withdraw:";
            Longman\TelegramBot\Request::sendMessage(['chat_id' => $chat_id, 'text' => $text]);
        }
        elseif ($message_text === '5️⃣ 💼 Set Wallet') {
            setUserState($chat_id, 'awaiting_wallet_address');
            $current_wallet = $users[$chat_id]['wallet'];
            $text = "💡 Your currently set USDT wallet is: {$current_wallet}\n💹 It will be used for all future withdrawals\n\n✍ Please send your new BEP20 wallet address now.";
            Longman\TelegramBot\Request::sendMessage(['chat_id' => $chat_id, 'text' => $text]);
        }
        // Handle multi-step conversations
        else {
            $state = getUserState($chat_id);
            if ($state === 'awaiting_withdraw_amount') {
                $amount = floatval($message_text);
                $balance = $users[$chat_id]['balance'];
                if ($amount < 100) {
                    $text = "❌ Error: Minimum withdrawal is 100 USDT.";
                } elseif ($amount > $balance) {
                    $text = "❌ Error: Insufficient balance. Your balance is {$balance} USDT.";
                } else {
                    // In a real app, you would save this to a database
                    $text = "✅ Withdrawal request received. Status: PENDING\n⏳ Your withdrawal will be processed within 24 hours.";
                }
                setUserState($chat_id, null); // Reset state
                Longman\TelegramBot\Request::sendMessage(['chat_id' => $chat_id, 'text' => $text]);
            } elseif ($state === 'awaiting_wallet_address') {
                // Validate BEP20 wallet address (0x + 40 hex characters)
                if (preg_match('/^0x[a-fA-F0-9]{40}$/', $message_text)) {
                    $users[$chat_id]['wallet'] = $message_text;
                    saveUserData($users);
                    $text = "✅ Wallet updated to: {$message_text}";
                } else {
                    $text = "❌ Error: Invalid BEP20 wallet address format. Please try again.";
                }
                setUserState($chat_id, null); // Reset state
                Longman\TelegramBot\Request::sendMessage(['chat_id' => $chat_id, 'text' => $text]);
            }
        }
    }
    // Handle "Continue" button click
    elseif ($callback_data === 'continue') {
        // Answer the callback query to remove the "loading" state on the button
        Longman\TelegramBot\Request::answerCallbackQuery(['callback_query_id' => $update['callback_query']['id']]);
        
        $text = "✅ You are now verified!\nChoose an option below ⬇";
        $keyboard = [
            ['🥇 💰 Balance', '🥈 🎊 Bonus'],
            ['🥉 💑 Referral', '4️⃣ 📤 Withdraw'],
            ['5️⃣ 💼 Set Wallet']
        ];

        Longman\TelegramBot\Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => $text,
            'reply_markup' => [
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ]
        ]);
    }

} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    // Log telegram errors
    error_log($e->getMessage());
}