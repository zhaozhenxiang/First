<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Model\User;
use Bin\View\View;

class AA extends BaseController
{
    public function index()
    {
        return View::make('index.php')->with('a', User::select('select * from test', 'data'));
    }

    public function rel()
    {
        return 'rel';
    }

    public function postA()
    {
        return __FILE__ . json_encode(app('Request')->all(), JSON_THROW_ON_ERROR);
    }

    public function middle()
    {
        return 'middle';
    }

    public function pick($a, $b)
    {
        return json_encode(app('Request')->getUrlParam());
    }

    public function pickOne($a)
    {
        return json_encode(app('Request')->getUrlParam());
    }
}
