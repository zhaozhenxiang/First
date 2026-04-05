<?php

declare(strict_types=1);

namespace App\Controllers;

use Bin\Request\Request;

class BB
{
    public function test($a)
    {
    }

    public function request(Request $a)
    {
        return json_encode($a->all());
    }
}
