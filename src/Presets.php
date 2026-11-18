<?php
declare(strict_types=1);

namespace AM;

/**
 * 内置 API 供应商预设模板
 */
class Presets
{
    public static function all(): array
    {
        return [
            [
                'id'          => 'openai',
                'name'        => 'OpenAI',
                'base_url'    => 'https://api.openai.com/v1',
                'api_format'  => '/chat/completions',
                'website_url' => 'https://platform.openai.com',
                'models'      => [
                    ['nickname' => 'GPT-4o',      'model_id' => 'gpt-4o',      'context_window' => '128000'],
                    ['nickname' => 'GPT-4o mini', 'model_id' => 'gpt-4o-mini', 'context_window' => '128000'],
                ],
            ],
            [
                'id'          => 'anthropic',
                'name'        => 'Anthropic Claude',
                'base_url'    => 'https://api.anthropic.com/v1',
                'api_format'  => '/messages',
                'website_url' => 'https://console.anthropic.com',
                'models'      => [
                    ['nickname' => 'Claude Sonnet 4',  'model_id' => 'claude-sonnet-4',     'context_window' => '200000'],
                    ['nickname' => 'Claude Haiku 3.5', 'model_id' => 'claude-3-5-haiku',    'context_window' => '200000'],
                ],
            ],
            [
                'id'          => 'glm',
                'name'        => '智谱 GLM',
                'base_url'    => 'https://open.bigmodel.cn/api/paas/v4',
                'api_format'  => '/chat/completions',
                'website_url' => 'https://open.bigmodel.cn',
                'models'      => [
                    ['nickname' => 'GLM-4 Plus',  'model_id' => 'glm-4-plus',  'context_window' => '128000'],
                    ['nickname' => 'GLM-4 Flash', 'model_id' => 'glm-4-flash', 'context_window' => '128000'],
                ],
            ],
            [
                'id'          => 'deepseek',
                'name'        => 'DeepSeek',
                'base_url'    => 'https://api.deepseek.com/v1',
                'api_format'  => '/chat/completions',
                'website_url' => 'https://platform.deepseek.com',
                'models'      => [
                    ['nickname' => 'DeepSeek Chat',     'model_id' => 'deepseek-chat',     'context_window' => '64000'],
                    ['nickname' => 'DeepSeek Reasoner', 'model_id' => 'deepseek-reasoner', 'context_window' => '64000'],
                ],
            ],
            [
                'id'          => 'qwen',
                'name'        => '通义千问',
                'base_url'    => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
                'api_format'  => '/chat/completions',
                'website_url' => 'https://dashscope.console.aliyun.com',
                'models'      => [
                    ['nickname' => 'Qwen Plus',  'model_id' => 'qwen-plus',  'context_window' => '131072'],
                    ['nickname' => 'Qwen Turbo', 'model_id' => 'qwen-turbo', 'context_window' => '131072'],
                ],
            ],
        ];
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $p) {
            if ($p['id'] === $id) {
                return $p;
            }
        }
        return null;
    }
}
