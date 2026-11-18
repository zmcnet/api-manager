<?php
declare(strict_types=1);

namespace AM\Controllers;

use AM\Auth;
use AM\Database;
use AM\Helper;

class ModelController
{
    public static function store(array $params): void
    {
        $user = Auth::requireLogin();
        $pid = (int) ($params['pid'] ?? 0);
        ProviderController::ownProvider($pid, (int) $user['id']);

        $nickname = trim((string) Helper::input('nickname', ''));
        $modelId  = trim((string) Helper::input('model_id', ''));
        $context  = trim((string) Helper::input('context_window', '')) ?: null;

        if ($nickname === '' || $modelId === '') {
            Helper::json(['ok' => false, 'message' => '昵称与模型 ID 必填'], 422);
        }

        $row = Database::fetch(
            'SELECT IFNULL(MAX(sort_order), 0) AS m FROM models WHERE provider_id = :pid',
            ['pid' => $pid]
        );
        $sort = ((int) ($row['m'] ?? 0)) + 1;

        $id = Database::insert('models', [
            'provider_id'    => $pid,
            'nickname'       => $nickname,
            'model_id'       => $modelId,
            'context_window' => $context,
            'sort_order'     => $sort,
        ]);
        Helper::json(['ok' => true, 'id' => $id]);
    }

    public static function update(array $params): void
    {
        $user = Auth::requireLogin();
        $id = (int) ($params['id'] ?? 0);
        $m = Database::fetch(
            'SELECT m.* FROM models m INNER JOIN api_providers p ON m.provider_id = p.id
             WHERE m.id = :id AND p.user_id = :uid',
            ['id' => $id, 'uid' => $user['id']]
        );
        if (!$m) {
            Helper::json(['ok' => false, 'message' => '资源不存在或无权访问'], 404);
        }
        $upd = [];
        $nickname = Helper::input('nickname');
        $modelId  = Helper::input('model_id');
        $context  = Helper::input('context_window');
        if ($nickname !== null) $upd['nickname'] = trim($nickname);
        if ($modelId  !== null) $upd['model_id'] = trim($modelId);
        if ($context  !== null) $upd['context_window'] = trim($context) ?: null;
        if ($upd) {
            Database::update('models', $upd, 'id = :id', ['id' => $id]);
        }
        Helper::json(['ok' => true]);
    }

    public static function destroy(array $params): void
    {
        $user = Auth::requireLogin();
        $id = (int) ($params['id'] ?? 0);
        Database::query(
            'DELETE m FROM models m INNER JOIN api_providers p ON m.provider_id = p.id
             WHERE m.id = :id AND p.user_id = :uid',
            ['id' => $id, 'uid' => $user['id']]
        );
        Helper::json(['ok' => true]);
    }

    public static function sort(array $params): void
    {
        $user = Auth::requireLogin();
        $pid = (int) ($params['pid'] ?? 0);
        ProviderController::ownProvider($pid, (int) $user['id']);

        $ids = Helper::jsonInput()['ids'] ?? [];
        if (!is_array($ids)) {
            Helper::json(['ok' => false, 'message' => '参数错误'], 400);
        }
        Database::transaction(function () use ($ids, $pid) {
            $i = 1;
            foreach ($ids as $id) {
                Database::update('models', ['sort_order' => $i],
                    'id = :id AND provider_id = :pid',
                    ['id' => (int) $id, 'pid' => $pid]
                );
                $i++;
            }
        });
        Helper::json(['ok' => true]);
    }
}
