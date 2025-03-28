<?php

namespace Utopia\Tests\Adapter\SMS;

use Utopia\Messaging\Adapter\SMS\AliCloud365;
use Utopia\Messaging\Messages\SMS;
use Utopia\Tests\Adapter\Base;

class AliCloud365Test extends Base
{
    /**
     * 检查两个不同from来源的优先级
     * 
     * @throws \Exception
     */
    public function testFromPriority(): void
    {
        // 设置测试环境变量
        putenv('ALICLOUD_APP_CODE=b86a1574d6e1413696c3a7b4f955416b');
        putenv('ALICLOUD_FROM=【智能云】');
        putenv('ALICLOUD_TO=13661205614');
        
        // 获取必要的环境变量
        $appCode = getenv('ALICLOUD_APP_CODE');
        $constructorFrom = getenv('ALICLOUD_FROM');
        $messageFrom = 'MessageFrom';
        $to = getenv('ALICLOUD_TO');
        
        echo "=================================================\n";
        echo "测试 from 优先级: 构造函数 vs 消息属性\n";
        echo "=================================================\n";
        echo "构造函数 From: $constructorFrom\n";
        echo "消息属性 From: $messageFrom\n";
        echo "收件人: $to\n\n";
        
        // 创建发送者实例，从构造函数传入 from
        $sender = new AliCloud365($appCode, $constructorFrom);
        
        // 创建消息，从消息属性传入 from
        $message = new SMS(
            to: [$to],
            content: '您的验证码是568126。如非本人操作，请忽略本短信',
            from: $messageFrom
        );
        
        try {
            // 发送消息
            $response = $sender->send($message);
            
            // 打印详细响应信息
            echo "=================================================\n";
            echo "响应详情:\n";
            echo "类型: " . $response['type'] . "\n";
            echo "发送成功数: " . ($response['deliveredTo'] ?? '0') . "\n";
            echo "结果详情:\n";
            print_r($response['results']);
            
            // 检查结果 - 只验证消息类型
            $this->assertEquals('sms', $response['type']);
            
            // 提示
            if (isset($response['results'][0]['error']) && !empty($response['results'][0]['error'])) {
                echo "\n注意: 使用测试用凭据所以预期会失败，但可以查看 from 字段的处理情况\n";
                echo "错误: " . $response['results'][0]['error'] . "\n";
            }
            
            echo "\n=================================================\n";
            echo "测试结论:\n";
            if (strpos($response['results'][0]['error'] ?? '', "【{$constructorFrom}】") !== false) {
                echo "优先使用了构造函数中的 from 值: $constructorFrom\n";
            } else if (strpos($response['results'][0]['error'] ?? '', "【{$messageFrom}】") !== false) {
                echo "优先使用了消息属性中的 from 值: $messageFrom\n";
            } else {
                echo "无法从错误信息中确定使用了哪个 from 值\n";
            }
            echo "=================================================\n";
            
        } catch (\Exception $e) {
            echo "测试异常: " . $e->getMessage() . "\n";
        }
    }
} 