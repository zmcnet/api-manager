<?php
declare(strict_types=1);

namespace AM\Controllers;

use AM\Auth;
use AM\Database;
use AM\Helper;

class HomeController
{
    public static function dashboard(): void
    {
        $user = Auth::requireLogin();

        $providers = Database::fetchAll(
            'SELECT * FROM api_providers WHERE user_id = :uid ORDER BY sort_order ASC, id ASC',
            ['uid' => $user['id']]
        );
        $providerIds = array_column($providers, 'id');
        $modelsByProvider = [];
        if ($providerIds) {
            $in = implode(',', array_fill(0, count($providerIds), '?'));
            $models = Database::fetchAll(
                "SELECT * FROM models WHERE provider_id IN ($in) ORDER BY sort_order ASC, id ASC",
                $providerIds
            );
            foreach ($models as $m) {
                $modelsByProvider[(int) $m['provider_id']][] = $m;
            }
        }

        Helper::view('dashboard', [
            'user'             => $user,
            'providers'        => $providers,
            'modelsByProvider' => $modelsByProvider,
        ]);
    }
}
