# AliCloud SMS 测试环境变量配置

要运行 AliCloudSMS 适配器的测试，需要设置以下环境变量：

## 必需的环境变量

```bash
# 阿里云 Access Key ID
export ALICLOUD_ACCESS_KEY_ID="your_access_key_id"

# 阿里云 Access Key Secret
export ALICLOUD_ACCESS_KEY_SECRET="your_access_key_secret"

# 短信签名名称（已审核通过的签名）
export ALICLOUD_SMS_SIGN_NAME="your_sign_name"

# 短信模板代码（已审核通过的模板）
export ALICLOUD_SMS_TEMPLATE_CODE="SMS_123456789"

# 测试接收短信的手机号码
export ALICLOUD_SMS_TO="13800138000"
```

## 运行测试

设置完环境变量后，可以运行以下命令执行测试：

```bash
# 运行所有测试
./vendor/bin/phpunit tests/Messaging/Adapter/SMS/AliCloudSMSTest.php

# 运行单个测试方法
./vendor/bin/phpunit tests/Messaging/Adapter/SMS/AliCloudSMSTest.php::testSendSMSWithDefaultPlaceholder
```

## 使用示例

```php
// 使用默认占位符 {{token}}
$sms = new AliCloudSMS($keyId, $keySecret, $signName, $templateCode, '{"code":"{{token}}"}');

// 使用自定义占位符 {{content}}
$sms = new AliCloudSMS($keyId, $keySecret, $signName, $templateCode, '{"message":"{{content}}"}', '{{content}}');
```

## 注意事项

1. **签名和模板**：需要在阿里云短信服务控制台创建并审核通过签名和模板
2. **模板参数**：第5个参数 `templateParam` 是包含占位符的JSON字符串，第6个参数 `placeholderParam` 是要替换的占位符（默认为 `{{token}}`）
3. **手机号格式**：支持带区号的手机号（如 `+8613800138000`）和不带区号的手机号（如 `13800138000`）
4. **费用**：测试会发送真实短信，会产生费用

## 示例模板

在阿里云短信服务控制台创建模板时，可以参考以下格式：

```
验证码模板：您的验证码为：${code}，请在5分钟内完成验证。
通知模板：${message}，感谢您的使用。
```

对应的 templateParam：
- 验证码模板：`{"code":"{{token}}"}`
- 通知模板：`{"message":"{{token}}"}`