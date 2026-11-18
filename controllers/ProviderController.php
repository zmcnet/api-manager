<?php
declare(strict_types=1);

namespace AM\Controllers;

use AM\Auth;
use AM\Crypto;
use AM\Database;
use AM\Helper;
use AM\Presets;

class ProviderController
{
    public static function create(): void
    {
        Auth::requireLogin();
        Helper::view('provider_form', [
            'provider' => null,
            'models'   => [],
            'presets'  => Presets::all(),
        ]);
    }

    public static function edit(array $params): void
    {
        $user = Auth::requireLogin();
        $id = (int) ($params['id'] ?? 0);
        $provider = self::ownProvider($id, (int) $user['id']);
        $models = Database::fetchAll(
            'SELECT * FROM models WHERE provider_id = :pid ORDER BY sort_order ASC, id ASC',
            ['pid' => $id]
        );
        Helper::view('provider_form', [
            'provider' => $provider,
            'models'   => $models,
            'presets'  => Presets::all(),
        ]);
    }

    public static function store(): void
    {
        $user = Auth::requireLogin();
        [$data, $models] = self::parseInput();

        $errors = self::validate($data);
        if ($errors) {
            Helper::json(['ok' => false, 'message' => implode('；', $errors)], 422);
        }

        $apiKey = (string) ($data['api_key'] ?? '');
        unset($data['api_key']);

        $providerId = Database::transaction(function () use ($user, $data, $apiKey, $models) {
            // 计算最大 sort_order
            $row = Database::fetch(
                'SELECT IFNULL(MAX(sort_order), 0) AS m FROM api_providers WHERE user_id = :uid',
                ['uid' => $user['id']]
            );
            $sortOrder = ((int) ($row['m'] ?? 0)) + 1;

            $id = Database::insert('api_providers', [
                'user_id'           => (int) $user['id'],
                'name'              => $data['name'],
                'api_key_encrypted' => Crypto::encrypt($apiKey, $user['encryption_key']),
                'base_url'          => $data['base_url'],
                'api_format'        => $data['api_format'],
                'website_url'       => $data['website_url'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'sort_order'        => $sortOrder,
            ]);
            self::saveModels($id, $models);
            return $id;
        });

        Helper::json(['ok' => true, 'id' => $providerId, 'redirect' => '/dashboard']);
    }

    public static function update(array $params): void
    {
        $user = Auth::requireLogin();
        $id = (int) ($params['id'] ?? 0);
        $provider = self::ownProvider($id, (int) $user['id']);

        [$data, $models] = self::parseInput();
        $errors = self::validate($data, isUpdate: true);
        if ($errors) {
            Helper::json(['ok' => false, 'message' => implode('；', $errors)], 422);
        }

        $update = [
            'name'        => $data['name'],
            'base_url'    => $data['base_url'],
            'api_format'  => $data['api_format'],
            'website_url' => $data['website_url'] ?? null,
            'notes'       => $data['notes'] ?? null,
        ];
        // 如果传了 api_key 才更新（避免误清空）
        if (isset($data['api_key']) && $data['api_key'] !== '') {
            $update['api_key_encrypted'] = Crypto::encrypt((string) $data['api_key'], $user['encryption_key']);
        }

        Database::transaction(function () use ($id, $update, $models) {
            Database::update('api_providers', $update, 'id = :id', ['id' => $id]);
            // 替换式保存模型
            Database::delete('models', 'provider_id = :pid', ['pid' => $id]);
            self::saveModels($id, $models);
        });

        Helper::json(['ok' => true, 'redirect' => '/dashboard']);
    }

    public static function destroy(array $params): void
    {
        $user = Auth::requireLogin();
        $id = (int) ($params['id'] ?? 0);
        self::ownProvider($id, (int) $user['id']);
        Database::delete('api_providers', 'id = :id', ['id' => $id]);
        Helper::json(['ok' => true]);
    }

    /** 仅在用户主动请求时返回解密的 API Key（用于「复制 API Key」） */
    public static function getApiKey(array $params): void
    {
        $user = Auth::requireLogin();
        $id = (int) ($params['id'] ?? 0);
        $p = self::ownProvider($id, (int) $user['id']);
        $key = Crypto::decrypt($p['api_key_encrypted'], $user['encryption_key']);
        if ($key === null) {
            Helper::json(['ok' => false, 'message' => 'API Key 解密失败'], 500);
        }
        Helper::json(['ok' => true, 'api_key' => $key]);
    }

    /** 返回完整 JSON 配置（含解密 Key + 全部模型） */
    public static function fullJson(array $params): void
    {
        $user = Auth::requireLogin();
        $id = (int) ($params['id'] ?? 0);
        $p = self::ownProvider($id, (int) $user['id']);
        $key = Crypto::decrypt($p['api_key_encrypted'], $user['encryption_key']);
        $models = Database::fetchAll(
            'SELECT nickname, model_id, context_window FROM models
             WHERE provider_id = :pid ORDER BY sort_order, id',
            ['pid' => $id]
        );

        $payload = [
            'name'        => $p['name'],
            'base_url'    => $p['base_url'],
            'api_format'  => $p['api_format'],
            'full_url'    => rtrim($p['base_url'], '/') . '/' . ltrim($p['api_format'], '/'),
            'api_key'     => $key,
            'website_url' => $p['website_url'],
            'notes'       => $p['notes'],
            'models'      => $models,
        ];
        Helper::json(['ok' => true, 'data' => $payload]);
    }

    public static function sort(): void
    {
        $user = Auth::requireLogin();
        $ids = Helper::jsonInput()['ids'] ?? [];
        if (!is_array($ids)) {
            Helper::json(['ok' => false, 'message' => '参数错误'], 400);
        }
        Database::transaction(function () use ($ids, $user) {
            $i = 1;
            foreach ($ids as $id) {
                $id = (int) $id;
                Database::update(
                    'api_providers', ['sort_order' => $i],
                    'id = :id AND user_id = :uid',
                    ['id' => $id, 'uid' => $user['id']]
                );
                $i++;
            }
        });
        Helper::json(['ok' => true]);
    }

    public static function listPresets(): void
    {
        Auth::requireLogin();
        Helper::json(['ok' => true, 'data' => Presets::all()]);
    }

    /** 从预设创建（用户填一个空 Key 即可，自动填充其他字段） */
    public static function fromPreset(): void
    {
        $user = Auth::requireLogin();
        $presetId = Helper::input('preset_id', '') ?? '';
        $apiKey   = (string) Helper::input('api_key', '');

        $preset = Presets::find($presetId);
        if (!$preset) {
            Helper::json(['ok' => false, 'message' => '预设不存在'], 404);
        }

        $providerId = Database::transaction(function () use ($user, $preset, $apiKey) {
            $row = Database::fetch(
                'SELECT IFNULL(MAX(sort_order), 0) AS m FROM api_providers WHERE user_id = :uid',
                ['uid' => $user['id']]
            );
            $sortOrder = ((int) ($row['m'] ?? 0)) + 1;
            $id = Database::insert('api_providers', [
                'user_id'           => (int) $user['id'],
                'name'              => $preset['name'],
                'api_key_encrypted' => Crypto::encrypt($apiKey, $user['encryption_key']),
                'base_url'          => $preset['base_url'],
                'api_format'        => $preset['api_format'],
                'website_url'       => $preset['website_url'] ?? null,
                'notes'             => null,
                'sort_order'        => $sortOrder,
            ]);
            $i = 1;
            foreach ($preset['models'] as $m) {
                Database::insert('models', [
                    'provider_id'    => $id,
                    'nickname'       => $m['nickname'],
                    'model_id'       => $m['model_id'],
                    'context_window' => $m['context_window'] ?? null,
                    'sort_order'     => $i++,
                ]);
            }
            return $id;
        });

        Helper::json(['ok' => true, 'id' => $providerId, 'redirect' => '/providers/' . $providerId . '/edit']);
    }

    // ----------------- 内部方法 -----------------

    /** 保证 provider 属于当前用户，否则 403 */
    public static function ownProvider(int $id, int $userId): array
    {
        $p = Database::fetch(
            'SELECT * FROM api_providers WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $userId]
        );
        if (!$p) {
            Helper::json(['ok' => false, 'message' => '资源不存在或无权访问'], 404);
        }
        return $p;
    }

    /** 解析 JSON 或表单输入 */
    private static function parseInput(): array
    {
        $json = Helper::jsonInput();
        if ($json) {
            $data = $json;
            $models = $json['models'] ?? [];
        } else {
            $data = $_POST;
            $models = [];
            if (!empty($_POST['models']) && is_array($_POST['models'])) {
                $models = $_POST['models'];
            }
        }
        $clean = [
            'name'        => trim((string) ($data['name'] ?? '')),
            'api_key'     => (string) ($data['api_key'] ?? ''),
            'base_url'    => trim((string) ($data['base_url'] ?? '')),
            'api_format'  => trim((string) ($data['api_format'] ?? '')),
            'website_url' => trim((string) ($data['website_url'] ?? '')) ?: null,
            'notes'       => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
        return [$clean, $models];
    }

    private static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];
        if ($data['name'] === '') $errors[] = '名称必填';
        if ($data['base_url'] === '') $errors[] = 'Base URL 必填';
        if ($data['api_format'] === '') $errors[] = 'API 格式后缀必填';
        if (!$isUpdate && $data['api_key'] === '') $errors[] = 'API Key 必填';
        if ($data['base_url'] !== '' && !preg_match('#^https?://#i', $data['base_url'])) {
            $errors[] = 'Base URL 需以 http(s):// 开头';
        }
        if ($data['website_url'] && !preg_match('#^https?://#i', $data['website_url'])) {
            $errors[] = '官网链接需以 http(s):// 开头';
        }
        return $errors;
    }

    /** 批量插入模型 */
    private static function saveModels(int $providerId, array $models): void
    {
        $i = 1;
        foreach ($models as $m) {
            if (!is_array($m)) continue;
            $nickname = trim((string) ($m['nickname'] ?? ''));
            $modelId  = trim((string) ($m['model_id'] ?? ''));
            if ($nickname === '' || $modelId === '') continue;
            Database::insert('models', [
                'provider_id'    => $providerId,
                'nickname'       => mb_substr($nickname, 0, 100),
                'model_id'       => mb_substr($modelId, 0, 100),
                'context_window' => trim((string) ($m['context_window'] ?? '')) ?: null,
                'is_default'     => !empty($m['is_default']) ? 1 : 0,
                'sort_order'     => $i++,
            ]);
        }
    }
}
