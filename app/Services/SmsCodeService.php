<?php

namespace App\Services;

/**
 * 短信发送服务类
 *
 * Class SmsCodeService
 * @package App\Services
 */
class SmsCodeService
{
    /**
     * 获取Redis连接
     *
     * @return \Laravel\Lumen\Application|mixed
     */
    private function redis()
    {
        return app('redis');
    }

    /**
     * 获取缓存key
     *
     * @param string $type 短信用途
     * @param string $mobile 手机号
     * @return string
     */
    private function getKey(string $type, string $mobile)
    {
        return "sms_code:{$type}:{$mobile}";
    }

    /**
     * 检测验证码是否正确
     *
     * @param string $type 发送类型
     * @param string $mobile 手机号
     * @param string $code 验证码
     * @return bool
     */
    public function check(string $type, string $mobile, string $code)
    {
        $sms_code = $this->redis()->get($this->getKey($type, $mobile));
        if (!$sms_code) {
            return false;
        }

        return $sms_code == $code;
    }

    /**
     * 发送验证码
     *
     * @param string $type 类型
     * @param string $mobile 手机号
     * @return array|bool
     */
    public function send(string $type, string $mobile)
    {
        $key = $this->getKey($type, $mobile);

        // 为防止刷短信行为，此处可进行过滤处理
        // ... 省略处理

        if (!$sms_code = $this->getCode($key)) {
            $sms_code = random(6, 'number');
        }

        $this->setCode($key, $sms_code);

        // 调取短信接口，建议异步任务执行 (暂无短信接口，省略处理)

        return [true, ['type' => $type, 'code' => $sms_code]];
    }

    /**
     * 获取缓存的验证码
     *
     * @param string $key
     * @return mixed
     */
    public function getCode(string $key)
    {
        return $this->redis()->get($key);
    }

    /**
     * 设置验证码缓存
     *
     * @param string $key 缓存key
     * @param string $sms_code 验证码
     * @param float|int $exp 过期时间（默认15分钟）
     * @return mixed
     */
    public function setCode(string $key, string $sms_code, $exp = 60 * 15)
    {
        return $this->redis()->setex($key, $exp, $sms_code);
    }

    /**
     * 删除验证码缓存
     *
     * @param string $type 类型
     * @param string $mobile 手机号
     * @return mixed
     */
    public function delCode(string $type, string $mobile)
    {
        return $this->redis()->del($this->getKey($type, $mobile));
    }
}
