<?php
// 加载配置文件
$config = require __DIR__ . '/config.php';

// 加载配置文件
$config_file = __DIR__.'/config.php';
if (!file_exists($config_file)) {
    die('请创建配置文件config.php，参考config.example.php');
}

$config = require $config_file;

// 检查必要配置项
$required_keys = ['api_key', 'api_url', 'temp_dir'];
foreach ($required_keys as $key) {
    if (!isset($config[$key])) {
        die("配置文件中缺少必要的键: $key");
    }
}

// 处理图片上传和识别
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    try {
        // 验证上传文件
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('上传失败: ' . $file['error']);
        }

        // 验证文件类型
        // $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        // if (!in_array($file['type'], $allowed_types)) {
        //     throw new Exception('只支持JPEG、PNG和GIF格式的图片');
        // }

        // 保存临时文件
        // 确保临时目录存在
        if (!is_dir($config['temp_dir']) && !mkdir($config['temp_dir'], 0755, true)) {
            throw new Exception('无法创建临时目录: ' . $config['temp_dir']);
        }
        $temp_path = $config['temp_dir'] . uniqid() . '_' . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $temp_path)) {
            throw new Exception('无法保存上传的文件');
        }

        // 调用通义千问API
        $response = callQwenAPI($temp_path, $config);
        file_put_contents('api_response.log', $response); // 记录API响应

        // 解析响应
        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('API响应解析失败: ' . json_last_error_msg());
        }
        
        $device_name = extractDeviceName($result);
        
        // 删除临时文件
        if (file_exists($temp_path)) {
            unlink($temp_path);
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 调用通义千问API
function callQwenAPI($image_path, $config) {
    try {
        // 读取图片文件并编码为base64
        $image_data = file_get_contents($image_path);
        if ($image_data === false) {
            throw new Exception("无法读取图片文件: $image_path");
        }
        
        $base64_image = base64_encode($image_data);
        $mime_type = mime_content_type($image_path);
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: ' . $config['api_key']
        ];

        $data = [
            'model' => 'qwen-vl-max',
            'input' => [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'image' => "data:$mime_type;base64,$base64_image"
                            ],
                            [
                                'text' => '这张图片中的设备是什么？请按以下格式回答：
设备名称：[名称]
巡检项目：
1. [检查项1]
2. [检查项2]
...
请严格按此格式返回，不要额外解释。'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $requestData = json_encode($data, JSON_UNESCAPED_SLASHES);
        file_put_contents('api_request.log', "API请求数据:\n$requestData\n", FILE_APPEND);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['api_url']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 临时解决方案：禁用SSL验证（仅用于测试）
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception('API请求失败: ' . curl_error($ch));
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            throw new Exception("API返回错误状态码: $http_code");
        }
        
        curl_close($ch);

        file_put_contents('api_response.log', "API响应: $response\n", FILE_APPEND);
        return $response;
    } catch (Exception $e) {
        file_put_contents('error.log', "callQwenAPI错误: " . $e->getMessage() . "\n", FILE_APPEND);
        throw $e;
    }
}

// 从API响应中提取设备名称和巡检项
function extractDeviceName($api_response) {
    if (!isset($api_response['output']['choices'][0]['message']['content'][0]['text'])) {
        return ['设备名称' => '无法识别设备', '巡检项目' => []];
    }
    
    $text = $api_response['output']['choices'][0]['message']['content'][0]['text'];
    $result = ['设备名称' => '', '巡检项目' => []];
    
    $lines = explode("\n", $text);
    foreach ($lines as $line) {
        if (strpos($line, '设备名称：') === 0) {
            $result['设备名称'] = trim(str_replace('设备名称：', '', $line));
        } elseif (preg_match('/^\d+\.\s*(.+)/', $line, $matches)) {
            $result['巡检项目'][] = $matches[1];
        }
    }
    
    return $result;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设备识别系统</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        .upload-form { margin-bottom: 20px; }
        .result { margin-top: 20px; padding: 10px; border: 1px solid #ddd; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>设备识别系统</h1>
    <form class="upload-form" method="post" enctype="multipart/form-data">
        <input type="file" name="image" accept="image/*" required>
        <button type="submit">上传并识别</button>
    </form>

    <?php if (isset($error)): ?>
        <div class="error">错误: <?php echo htmlspecialchars($error); ?></div>
    <?php elseif (isset($device_name)): ?>
        <div class="result">
            <h3>识别结果</h3>
            <p><strong>设备名称:</strong> <?php echo htmlspecialchars($device_name['设备名称']); ?></p>
            <?php if (!empty($device_name['巡检项目'])): ?>
                <h4>巡检项目:</h4>
                <ul>
                    <?php foreach ($device_name['巡检项目'] as $item): ?>
                        <li><?php echo htmlspecialchars($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>