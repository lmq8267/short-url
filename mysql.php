<?php
// index.php
define('ADMIN_PATH', '/short');
define('API_PATH', '/api');
define('URL_KEY', 'longUrl');
define('URL_NAME', 'shortCode');
define('SHORT_URL_KEY', 'shorturl');

// 创建数据库连接
function getDbConnection() {
    $servername = "mysql4.serv00.com"; // 改为你的数据库服务器
    $username = "m5728"; // 改为你的数据库用户名
    $password = "Abc123456@"; // 改为你的数据库密码
    $dbname = "m5728_short"; // 改为你的数据库名

    // 创建连接
    $conn = new mysqli($servername, $username, $password, $dbname);

    // 检查连接
    if ($conn->connect_error) {
        die("连接失败: " . $conn->connect_error);
    }

    // 自动创建表
    $sql = "
        CREATE TABLE IF NOT EXISTS shortlinks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            short_code VARCHAR(255) NOT NULL,
            last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            client_ip VARCHAR(255),
            type VARCHAR(50),
            value TEXT,
            password VARCHAR(255),
            expires_at TIMESTAMP NULL DEFAULT NULL,
            burn_after_reading BOOLEAN DEFAULT FALSE
        );

        CREATE TABLE IF NOT EXISTS short_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            total_rules INT DEFAULT 0,
            today_new_rules INT DEFAULT 0,
            total_visits INT DEFAULT 0,
            today_visits INT DEFAULT 0,
            last_rule_update DATE,
            last_visits_update DATE
        );
        
        INSERT INTO short_rules (id) SELECT 1 WHERE NOT EXISTS (SELECT 1 FROM short_rules);
    ";

    if ($conn->multi_query($sql) === TRUE) {
        while ($conn->more_results() && $conn->next_result()) {
            // 清空结果集
            $conn->use_result();
        }
    } else {
        die("Error creating tables: " . $conn->error);
    }

    return $conn;
}

function getChinaTime() {
    $now = new DateTime("now", new DateTimeZone('Asia/Shanghai'));
    return $now->format('Y-m-d H:i:s');
}

function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function getPostData() {
    return json_decode(file_get_contents('php://input'), true);
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$conn = getDbConnection();

if ($path == ADMIN_PATH) {
    // 从数据库获取short_rules数据
    $sql = "SELECT * FROM short_rules WHERE id = 1";
    $result = $conn->query($sql);
    $shortRulesData = $result->fetch_assoc();

    if (!$shortRulesData) {
        $shortRulesData = [
            'total_rules' => '0',
            'today_new_rules' => '0',
            'total_visits' => '0',
            'today_visits' => '0',
        ];
    } else {
        if (!isset($shortRulesData['total_rules'])) {
            $shortRulesData['total_rules'] = '0';
        }
        if (!isset($shortRulesData['today_new_rules'])) {
            $shortRulesData['today_new_rules'] = '0';
        }
        if (!isset($shortRulesData['total_visits'])) {
            $shortRulesData['total_visits'] = '0';
        }
        if (!isset($shortRulesData['today_visits'])) {
            $shortRulesData['today_visits'] = '0';
        }
        if (!isset($shortRulesData['last_rule_update'])) {
            $shortRulesData['last_rule_update'] = '2024';
        }
    }

    // 设置默认值为0
    $totalRules = $shortRulesData['total_rules'];
    $todayNewRules = $shortRulesData['today_new_rules'];
    $totalVisits = $shortRulesData['total_visits'];
    $todayVisits = $shortRulesData['today_visits'];

    ob_start();
    include('template.html');
    $indexWithStats = ob_get_clean();
    echo str_replace(["{{totalRules}}", "{{todayNewRules}}", "{{totalvisits}}", "{{todayvisits}}"], [$totalRules, $todayNewRules, $totalVisits, $todayVisits], $indexWithStats);
    exit;
}

if (strpos($path, API_PATH) === 0) {
    $body = getPostData();
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $short_type = $body['type'] ?? 'link';
    $body[URL_NAME] = $body[URL_NAME] ?? substr(md5(uniqid()), 0, 6);

    if (empty($body[URL_NAME])) {
        $body[URL_NAME] = generateRandomString();
    }

    $sql = "SELECT * FROM shortlinks WHERE short_code = '{$body[URL_NAME]}'";
    $result = $conn->query($sql);
    $existingData = $result->fetch_assoc();
    $isNewRule = !$existingData;

    if ($existingData && $existingData['password'] && $existingData['password'] !== $body['password']) {
        echo json_encode(['error' => '密码错误！该后缀已经被使用，请使用正确的密码修改或使用其他后缀。'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $expiration = (int)$body['expiration'];
    $expiresAt = $expiration > 0 ? (new DateTime("now", new DateTimeZone('Asia/Shanghai')))->modify("+{$expiration} minutes")->format('Y-m-d H:i:s') : null;

    if ($short_type == "link" && strpos($body[URL_KEY], 'http') !== 0) {
        $body[URL_KEY] = 'http://' . $body[URL_KEY];
    }

    $linkData = [
        'lastUpdate' => getChinaTime(),
        'clientIp' => $clientIp,
        'type' => $short_type,
        'value' => $body[URL_KEY],
        'password' => $body['password'],
        'expiresAt' => $expiresAt,
        'burn_after_reading' => $body['burn_after_reading']
    ];

    if ($isNewRule) {
        $sql = "INSERT INTO shortlinks (short_code, last_update, client_ip, type, value, password, expires_at, burn_after_reading) 
                VALUES ('{$body[URL_NAME]}', '{$linkData['lastUpdate']}', '{$linkData['clientIp']}', '{$linkData['type']}', '{$linkData['value']}', '{$linkData['password']}', '{$linkData['expiresAt']}', '{$linkData['burn_after_reading']}')";
        $conn->query($sql);

        $sql = "SELECT COUNT(*) as totalRules FROM shortlinks";
        $result = $conn->query($sql);
        $totalRules = $result->fetch_assoc()['totalRules'];

        $todayNewRules = (int)$shortRulesData['today_new_rules'];
        $lastRuleUpdate = $shortRulesData['last_rule_update'];
        $today = (new DateTime("now", new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');

        if ($lastRuleUpdate !== $today) {
            $todayNewRules = 0;
        }

        $todayNewRules += 1;

        $sql = "UPDATE short_rules SET total_rules = {$totalRules}, today_new_rules = {$todayNewRules}, last_rule_update = '{$today}' WHERE id = 1";
        $conn->query($sql);
    } else {
        $sql = "UPDATE shortlinks SET last_update = '{$linkData['lastUpdate']}', client_ip = '{$linkData['clientIp']}', type = '{$linkData['type']}', value = '{$linkData['value']}', password = '{$linkData['password']}', expires_at = '{$linkData['expiresAt']}', burn_after_reading = '{$linkData['burn_after_reading']}' WHERE short_code = '{$body[URL_NAME]}'";
        $conn->query($sql);
    }

    $responseBody = [
        'type' => $body['type'],
        SHORT_URL_KEY => "http://{$_SERVER['HTTP_HOST']}/{$body[URL_NAME]}",
        URL_NAME => $body[URL_NAME],
    ];

    echo json_encode($responseBody);
    exit;
}

$key = ltrim($path, '/');
$key = urldecode($key);
if ($key !== "") {
    $sql = "SELECT * FROM shortlinks WHERE short_code = '{$key}'";
    $result = $conn->query($sql);
    $link = $result->fetch_assoc();

    if (!$link) {
        header("Location: " . ADMIN_PATH, true, 302);
        exit;
    }

    $expiresAt = isset($link['expires_at']) ? new DateTime($link['expires_at'], new DateTimeZone('Asia/Shanghai')) : null;
    $now = new DateTime("now", new DateTimeZone('Asia/Shanghai'));
    if ($expiresAt && $now >= $expiresAt) {
        echo "链接已过期";
        exit;
    }

    if ($link['burn_after_reading'] == true) {
        $sql = "DELETE FROM shortlinks WHERE short_code = '{$key}'";
        $conn->query($sql);
    }

    $sql = "SELECT * FROM short_rules WHERE id = 1";
    $result = $conn->query($sql);
    $shortRulesData = $result->fetch_assoc();

    $totalVisits = (int)$shortRulesData['total_visits'];
    $todayVisits = (int)$shortRulesData['today_visits'];
    $lastVisitsUpdate = $shortRulesData['last_visits_update'];
    $today = (new DateTime("now", new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');

    if ($lastVisitsUpdate !== $today) {
        $todayVisits = 0;
    }

    $totalVisits += 1;
    $todayVisits += 1;

    $sql = "UPDATE short_rules SET total_visits = {$totalVisits}, today_visits = {$todayVisits}, last_visits_update = '{$today}' WHERE id = 1";
    $conn->query($sql);

    if ($link['type'] === 'link') {
        header("Location: {$link['value']}", true, 302);
        exit;
    }

    if ($link['type'] === 'html') {
        header("Content-Type: text/html; charset=utf-8");
        echo $link['value'];
        exit;
    } else {
        header("Content-Type: text/plain; charset=utf-8");
        echo $link['value'];
        exit;
    }
}

http_response_code(403);
echo "403";
?>
