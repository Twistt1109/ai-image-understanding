# 设备识别与巡检系统

基于通义千问API实现的设备识别与巡检项提取系统。

## 安装运行

1. 复制配置文件：
   ```bash
   cp config.example.php config.php
   ```

2. 编辑配置文件：
   ```php
   return [
       'api_key' => '您的API密钥',
       'api_url' => 'https://dashscope.aliyuncs.com/api/...',
       'temp_dir' => 'temp/'
   ];
   ```

3. 启动开发服务器：
   ```bash
   php -S localhost:8000
   ```

## 使用说明

1. 访问 `http://localhost:8000`
2. 上传设备图片
3. 系统将返回：
   - 设备识别结果
   - 相关巡检项目
   - 检查要点

## 注意事项

⚠️ **重要安全提示**
- 不要提交`config.php`到版本控制
- 保护您的API密钥
- 定期检查临时目录文件

🔧 **系统要求**
- PHP 7.4+
- 启用curl扩展
- temp目录可写权限

## 常见问题

Q: 上传图片失败？
A: 检查temp目录是否存在且有写入权限

Q: API请求失败？
A: 检查config.php中的API密钥和URL是否正确