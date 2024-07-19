<?php
// index.php
define('ADMIN_PATH', '/short');
define('API_PATH', '/api');
define('URL_KEY', 'longUrl');
define('URL_NAME', 'shortCode');
define('SHORT_URL_KEY', 'shorturl');

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

if ($path == ADMIN_PATH) {
    // 从shortlink获取short_rules数据
    $shortRulesData = json_decode(file_get_contents('short_rules.json'), true);
    if (!$shortRulesData) {
        $shortRulesData = [
            'total_rules' => '0',
            'today_new_rules' => '0',
            'total_visits' => '0',
            'today_visits' => '0',
        ];
        file_put_contents('short_rules.json', json_encode($shortRulesData));
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
                if (!isset($shortRulesData['last_rule_update'])) {
                        $shortRulesData['last_rule_update'] = '2024';
                }
        file_put_contents('short_rules.json', json_encode($shortRulesData));
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
    // 从请求体中获取数据
$body = json_decode(file_get_contents('php://input'), true);

// 如果没有提供后缀，生成一个随机后缀
if (empty($body[URL_NAME])) {
    $body[URL_NAME] = generateRandomString();
}
    if ($body[URL_NAME] === 'api') {
    echo json_encode(['error' => '错误！该后缀为API调用，请使用其他后缀。'], JSON_UNESCAPED_UNICODE);
    exit;
    }
    // 检查文件夹是否存在，如果不存在则创建
    $folderPath = "shortlinks";
    if (!file_exists($folderPath)) {
    mkdir($folderPath, 0777, true); // 创建文件夹，0777 是文件夹的权限设置，true 表示递归创建所有需要的文件夹
    }

    $existingData = file_exists("shortlinks/{$body[URL_NAME]}.json") ? json_decode(file_get_contents("shortlinks/{$body[URL_NAME]}.json"), true) : null;
    $isNewRule = !$existingData;

    if ($existingData && $existingData['password'] && $existingData['password'] !== $body['password']) {
        echo json_encode(['error' => '密码错误！该后缀已经被使用，请使用正确的密码修改或使用其他后缀。'], JSON_UNESCAPED_UNICODE);        exit;
    }

    $expiration = (int)$body['expiration'];
    $expiresAt = $expiration > 0 ? (new DateTime("now", new DateTimeZone('Asia/Shanghai')))->modify("+{$expiration} minutes")->format('Y-m-d H:i:s') : null;
        
        // 假设 $body 是一个包含链接数据的数组
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

    $jsonString = json_encode($linkData, JSON_UNESCAPED_UNICODE);
file_put_contents("shortlinks/{$body[URL_NAME]}.json", $jsonString);
    if ($isNewRule) {
        $shortRulesData = json_decode(file_get_contents('short_rules.json'), true);
        // 获取 shortlinks 文件夹中的 .json 文件数量
        $files = glob('shortlinks/*.json');
        $totalRules = count($files);
        $todayNewRules = (int)$shortRulesData['today_new_rules'];
        $lastRuleUpdate = $shortRulesData['last_rule_update'];
        $today = (new DateTime("now", new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');

        if ($lastRuleUpdate !== $today) {
            $todayNewRules = 0;
        }

        
        $todayNewRules += 1;

        $shortRulesData['total_rules'] = (string)$totalRules;
        $shortRulesData['today_new_rules'] = (string)$todayNewRules;
        $shortRulesData['last_rule_update'] = $today;

        file_put_contents('short_rules.json', json_encode($shortRulesData));
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
if ($key !== "" && !file_exists("shortlinks/{$key}.json")) {
    header("Location: " . ADMIN_PATH, true, 302);
    exit;
}

if ($key === "") {
    echo json_encode(['error' => '空页面。'], JSON_UNESCAPED_UNICODE);        exit;
}

$link = json_decode(file_get_contents("shortlinks/{$key}.json"), true);
if ($link) {
    $expiresAt = isset($link['expiresAt']) ? new DateTime($link['expiresAt'], new DateTimeZone('Asia/Shanghai')) : null;
    $now = new DateTime("now", new DateTimeZone('Asia/Shanghai'));
    if ($expiresAt && $now >= $expiresAt) {
        echo "链接已过期";
        unlink("shortlinks/{$key}.json");
        exit;
    }

    if ($link['burn_after_reading'] === "true") {
    unlink("shortlinks/{$key}.json");
    }

        $shortRulesData = json_decode(file_get_contents('short_rules.json'), true);
        $totalVisits = (int)$shortRulesData['total_visits'];
        $todayVisits = (int)$shortRulesData['today_visits'];
        $lastVisitsUpdate = $shortRulesData['last_visits_update'];
        $today = (new DateTime("now", new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');

        if ($lastVisitsUpdate !== $today) {
            $todayVisits = 0;
        }

        $totalVisits += 1;
        $todayVisits += 1;

        $shortRulesData['total_visits'] = (string)$totalVisits;
        $shortRulesData['today_visits'] = (string)$todayVisits;
        $shortRulesData['last_visits_update'] = $today;

        file_put_contents('short_rules.json', json_encode($shortRulesData));

    if ($link['type'] === 'link') {
        header("Location: {$link['value']}", true, 302);
        exit;
    }

    if ($link['type'] === 'html') {
        header("Content-Type: text/html; charset=utf-8");
        echo $link['value'];
        exit;
    } else {
        header("Content-Type: text/html; charset=utf-8");
        echo "
<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f0f8ff; /* 淡蓝色背景 */
       }

       #container {
            width: 92%; /* 宽度占据88% */
            height: 92%; /* 高度占据88% */
            overflow: auto; /* 支持滚动 */
            padding: 5px; /* 内边距 */
            box-sizing: border-box; /* 边框包含在宽度和高度内 */
            /* 绝对定位 */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        #content {
            font-size: 15px; /* 固定大小的字体，可以根据需要调整像素大小 */
            font-weight: 900;
            text-align: left; /* 左对齐 */
            color: black; /* 黑色字体 */
            word-break: break-word; /* 自动换行 */
            white-space: pre-wrap; /* 保留原文换行 */
            margin: 0; /* 去除外边距 */
            padding: 0; /* 去除内边距 */
        }
        .floating-menu {
            position: fixed;
            top: 50%;
            right: 10px;
            z-index: 1000;
            transform: translateY(-50%);
        }
        .floating-menu button {
            display: block;
            width: 30px;
            height: 30px;
            margin-bottom: 8px;
            cursor: pointer;
            background-color: #fff;
            border: 1px solid #ccc;
            text-align: center;
            font-weight: bold; /* 加粗文本 */
        }
        #color-picker {
            position: fixed;
            top: 50%;
            right: -200px;
            width: 150px;
            padding: 10px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            transition: right 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        #color-picker label {
            margin: 5px 0;
        }
        #color-picker.show {
            right: 0px;
        }
    </style>
</head>
<body>
    <div id='container'>
        <div id='content'>
             {$link['value']}
        </div>
    </div>

    <div class='floating-menu'>
        <button onclick='toggleColorPicker()'>🎨</button>
        <button onclick='adjustFontSize('+')'>+</button>
        <button onclick='adjustFontSize('-')'>-</button>
    </div>

    <div id='color-picker'>
        <label for='bg-color'>选择背景色:</label>
        <input type='color' id='bg-color'>
        <label for='font-color'>选择字体色:</label>
        <input type='color' id='font-color'>
    </div>

    <script>
        // Function to handle font size adjustment
        function adjustFontSize(direction) {
            var content = document.getElementById('content');
            var currentFontSize = parseInt(window.getComputedStyle(content).fontSize);
            var newFontSize = currentFontSize;

            if (direction === '+') {
                newFontSize += 2;
            } else if (direction === '-') {
                newFontSize -= 2;
            }

            content.style.fontSize = newFontSize + 'px';
        }

        // Function to toggle the color picker visibility
        function toggleColorPicker() {
            var colorPicker = document.getElementById('color-picker');
            colorPicker.classList.toggle('show');
        }

        // Function to hide the color picker when clicking outside
        document.addEventListener('click', function(event) {
            var colorPicker = document.getElementById('color-picker');
            var target = event.target;

            if (!colorPicker.contains(target) && target !== document.querySelector('.floating-menu button:first-child')) {
                colorPicker.classList.remove('show');
            }
        });

        // Function to handle color changes
        document.getElementById('bg-color').addEventListener('input', function() {
            document.getElementById('container').style.backgroundColor = this.value;
        });

        document.getElementById('font-color').addEventListener('input', function() {
            document.getElementById('content').style.color = this.value;
        });
    </script>
</body>
</html>";
exit;
    }
}

http_response_code(403);
echo "403";
?>
